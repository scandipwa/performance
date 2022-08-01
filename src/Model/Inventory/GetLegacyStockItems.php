<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterfaceFactory;
use Magento\CatalogInventory\Model\Stock;

class GetLegacyStockItems
{
    /**
     * @var StockItemInterfaceFactory
     */
    protected StockItemInterfaceFactory $stockItemFactory;

    /**
     * @var StockItemCriteriaInterfaceFactory
     */
    protected StockItemCriteriaInterfaceFactory $legacyStockItemCriteriaFactory;

    /**
     * @var StockItemRepositoryInterface
     */
    protected StockItemRepositoryInterface $legacyStockItemRepository;

    /**
     * @var GetProductIdsBySkus
     */
    protected GetProductIdsBySkus $getProductIdsBySkus;

    /**
     * Cached results
     * @var array
     */
    protected array $legacyStockItemsBySku = [];

    /**
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockItemCriteriaInterfaceFactory $legacyStockItemCriteriaFactory
     * @param StockItemRepositoryInterface $legacyStockItemRepository
     * @param GetProductIdsBySkus $getProductIdsBySkus
     */
    public function __construct(
        StockItemInterfaceFactory $stockItemFactory,
        StockItemCriteriaInterfaceFactory $legacyStockItemCriteriaFactory,
        StockItemRepositoryInterface $legacyStockItemRepository,
        GetProductIdsBySkus $getProductIdsBySkus
    ) {
        $this->stockItemFactory = $stockItemFactory;
        $this->legacyStockItemCriteriaFactory = $legacyStockItemCriteriaFactory;
        $this->legacyStockItemRepository = $legacyStockItemRepository;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
    }

    /**
     * Get legacy stock item entity by sku.
     *
     * @param array $skuArray
     * @return StockItemInterface[]
     */
    public function execute(array $skuArray): array
    {
        $resultsBySku = [];
        $loadSkus = [];

        foreach ($skuArray as $sku) {
            if (isset($this->legacyStockItemsBySku[$sku])) {
                $resultsBySku[$sku] = $this->legacyStockItemsBySku[$sku];
            } else {
                $loadSkus[] = $sku;
                $resultsBySku[$sku] = null;
            }
        }

        if (count($loadSkus)) {
            $legacyStockItems = $this->getLegacyStockItems($loadSkus);

            foreach ($legacyStockItems as $sku => $legacyStockItem) {
                $this->legacyStockItemsBySku[$sku] = $legacyStockItem;
                $resultsBySku[$sku] = $legacyStockItem;
            }
        }

        return $resultsBySku;
    }

    /**
     * Get legacy stock item entities by skus
     *
     * @param array $skuArray
     * @return StockItemInterface[]
     */
    public function getLegacyStockItems(array $skuArray): array
    {
        $results = [];
        $productIds = $this->getProductIdsBySkus->execute($skuArray);

        foreach ($productIds as $sku => $productId) {
            if ($productId === null) {
                $stockItem = $this->stockItemFactory->create();
                // Make possible to Manage Stock for Products removed from Catalog
                $stockItem->setManageStock(true);
                $results[$sku] = $stockItem;
                unset($productIds[$sku]);
            }
        }

        $productSkusById = array_flip($productIds);

        $searchCriteria = $this->legacyStockItemCriteriaFactory->create();
        $searchCriteria->setProductsFilter($productIds);

        // Stock::DEFAULT_STOCK_ID is used until we have proper multi-stock item configuration
        $searchCriteria->addFilter(StockItemInterface::STOCK_ID, StockItemInterface::STOCK_ID, Stock::DEFAULT_STOCK_ID);
        $stockItemCollection = $this->legacyStockItemRepository->getList($searchCriteria);

        $stockItems = $stockItemCollection->getItems();

        foreach ($stockItems as $stockItem) {
            $results[$productSkusById[$stockItem->getProductId()]] = $stockItem;
        }

        foreach ($skuArray as $sku) {
            if (!isset($results[$sku])) {
                $results[$sku] = $this->stockItemFactory->create();
            }
        }

        return $results;
    }
}
