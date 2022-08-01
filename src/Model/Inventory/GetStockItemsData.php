<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsDataInterface;

class GetStockItemsData implements GetStockItemsDataInterface
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resource;

    /**
     * @var StockIndexTableNameResolverInterface
     */
    protected StockIndexTableNameResolverInterface $stockIndexTableNameResolver;

    /**
     * @var DefaultStockProviderInterface
     */
    protected DefaultStockProviderInterface $defaultStockProvider;

    /**
     * @var GetProductIdsBySkus
     */
    protected GetProductIdsBySkus $getProductIdsBySkus;

    /**
     * @var IsSingleSourceModeInterface
     */
    protected IsSingleSourceModeInterface $isSingleSourceMode;

    /**
     * @var IsSourceItemManagementAllowedForSkuInterface
     */
    protected IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku;

    /**
     * Cached results
     * @var array
     */
    protected array $stockItemDatasByStockAndSku = [];

    /**
     * @param ResourceConnection $resource
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param GetProductIdsBySkus $getProductIdsBySkus
     * @param IsSingleSourceModeInterface|null $isSingleSourceMode
     * @param IsSourceItemManagementAllowedForSkuInterface|null $isSourceItemManagementAllowedForSku
     */
    public function __construct(
        ResourceConnection $resource,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver,
        DefaultStockProviderInterface $defaultStockProvider,
        GetProductIdsBySkus $getProductIdsBySkus,
        IsSingleSourceModeInterface $isSingleSourceMode,
        IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku
    ) {
        $this->resource = $resource;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->isSingleSourceMode = $isSingleSourceMode;
        $this->isSourceItemManagementAllowedForSku = $isSourceItemManagementAllowedForSku;
    }

    /**
     * Get stock item entities by sku array and stock
     * Cache wrapper
     *
     * @param array $skuArray
     * @param int $stockId
     * @return array
     * @throws LocalizedException
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $resultsBySku = [];
        $loadSkus = [];

        foreach ($skuArray as $sku) {
            if (isset($this->stockItemDatasByStockAndSku[$stockId][$sku])) {
                $resultsBySku[$sku] = $this->stockItemDatasByStockAndSku[$stockId][$sku];
            } else {
                $loadSkus[] = $sku;
                $resultsBySku[$sku] = null;
            }
        }

        if (count($loadSkus)) {
            $stockItemDatas = $this->getStockItems($loadSkus, $stockId);

            foreach ($stockItemDatas as $sku => $stockItemData) {
                $this->stockItemDatasByStockAndSku[$stockId][$sku] = $stockItemData;
                $resultsBySku[$sku] = $stockItemData;
            }
        }

        return $resultsBySku;
    }

    /**
     * Get stock item entities by skus and stock
     *
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function getStockItems(array $skuArray, int $stockId): array
    {
        $result = [];

        foreach ($skuArray as $sku) {
            $result[$sku] = null;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select();

        if ($this->defaultStockProvider->getId() === $stockId) {
            $productIds = $this->getProductIdsBySkus->execute($skuArray);

            foreach ($productIds as $sku => $productId) {
                if ($productId === null) {
                    $result[$sku] = null;
                    unset($productIds[$sku]);
                }
            }

            $productSkusById = array_flip($productIds);

            $select->from(
                $this->resource->getTableName('cataloginventory_stock_status'),
                [
                    GetStockItemsDataInterface::QUANTITY => 'qty',
                    GetStockItemsDataInterface::IS_SALABLE => 'stock_status',
                    'product_id'
                ]
            )->where(
                'product_id IN (?)',
                $productIds
            );
        } else {
            $select->from(
                $this->stockIndexTableNameResolver->execute($stockId),
                [
                    GetStockItemsDataInterface::QUANTITY => IndexStructure::QUANTITY,
                    GetStockItemsDataInterface::IS_SALABLE => IndexStructure::IS_SALABLE,
                    IndexStructure::SKU
                ]
            )->where(
                IndexStructure::SKU . ' IN (?)',
                $skuArray
            );
        }

        try {
            foreach ($connection->fetchAll($select) as $row) {
                if ($this->defaultStockProvider->getId() === $stockId) {
                    $result[$productSkusById[$row['product_id']]] = [
                        GetStockItemsDataInterface::QUANTITY => $row[GetStockItemsDataInterface::QUANTITY],
                        GetStockItemsDataInterface::IS_SALABLE => $row[GetStockItemsDataInterface::IS_SALABLE]
                    ];
                } else {
                    $result[$row[IndexStructure::SKU]] = [
                        GetStockItemsDataInterface::QUANTITY => $row[GetStockItemsDataInterface::QUANTITY],
                        GetStockItemsDataInterface::IS_SALABLE => $row[GetStockItemsDataInterface::IS_SALABLE]
                    ];
                }
            }
        } catch (Exception $e) {
            throw new LocalizedException(__('Could not receive Stock Item data'), $e);
        }

        /**
         * Fallback to the legacy cataloginventory_stock_item table.
         * Caused by data absence in legacy cataloginventory_stock_status table
         * for disabled products assigned to the default stock.
         */
        $missingStockItemSkus = [];

        foreach ($skuArray as $sku) {
            if ($result[$sku] === null) {
                $missingStockItemSkus[] = $sku;
            }
        }

        if (count($missingStockItemSkus)) {
            $missingStockItemData = $this->getStockItemDataFromStockItemTable($missingStockItemSkus, $stockId);

            foreach ($missingStockItemData as $sku => $row) {
                $result[$sku] = $row;
            }
        }

        return $result;
    }

    /**
     * Retrieve stock item data for products assigned to the default stock.
     *
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    protected function getStockItemDataFromStockItemTable(array $skuArray, int $stockId): array
    {
        $result = [];

        foreach ($skuArray as $sku) {
            $result[$sku] = null;
        }

        $skusToCheck = array_flip($skuArray);

        foreach ($skuArray as $sku) {
            if ($this->defaultStockProvider->getId() !== $stockId
                || $this->isSingleSourceMode->execute()
                || !$this->isSourceItemManagementAllowedForSku->execute((string)$sku)
            ) {
                $result[$sku] = null;
                unset($skusToCheck[$sku]);
            }
        }

        $productIds = $this->getProductIdsBySkus->execute(array_keys($skusToCheck));

        foreach ($productIds as $sku => $productId) {
            if ($productId === null) {
                $result[$sku] = null;
                unset($skusToCheck[$sku]);
                unset($productIds[$sku]);
            }
        }

        $productSkusById = array_flip($productIds);

        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            $this->resource->getTableName('cataloginventory_stock_item'),
            [
                GetStockItemsDataInterface::QUANTITY => 'qty',
                GetStockItemsDataInterface::IS_SALABLE => 'is_in_stock',
                'product_id'
            ]
        )->where(
            'product_id IN (?)',
            $productIds
        );

        foreach ($connection->fetchAll($select) as $row) {
            $result[$productSkusById[$row['product_id']]] = [
                GetStockItemsDataInterface::QUANTITY => $row[GetStockItemsDataInterface::QUANTITY],
                GetStockItemsDataInterface::IS_SALABLE => $row[GetStockItemsDataInterface::IS_SALABLE]
            ];
        }

        return $result;
    }
}
