<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\Inventory\Model\ResourceModel\StockSourceLink;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use ScandiPWA\Performance\Api\Inventory\AreProductsAssignedToStockInterface;

class AreProductsAssignedToStock implements AreProductsAssignedToStockInterface
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resource;

    /**
     * @var array
     */
    protected $resultsByStockAndSku = [];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * Cache wrapper for actual loading
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $resultsBySku = [];
        $loadSkus = [];

        foreach ($skuArray as $sku) {
            if (isset($this->resultsByStockAndSku[$stockId][$sku])) {
                $resultsBySku[$sku] = $this->resultsByStockAndSku[$stockId][$sku];
            } else {
                $loadSkus[] = $sku;
                $resultsBySku[$sku] = null;
            }
        }

        if (count($loadSkus)) {
            $results = $this->getAreProductsAssignedToStock($loadSkus, $stockId);

            foreach ($results as $sku => $result) {
                $this->resultsByStockAndSku[$stockId][$sku] = $result;
                $resultsBySku[$sku] = $result;
            }
        }

        return $resultsBySku;
    }

    /**
     * Checks if products are assigned to stock, by sku list and stock id
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function getAreProductsAssignedToStock(array $skuArray, int $stockId): array
    {
        $finalResults = [];

        foreach ($skuArray as $sku) {
            $finalResults[$sku] = false;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['stock_source_link' => $this->resource->getTableName(StockSourceLink::TABLE_NAME_STOCK_SOURCE_LINK)]
            )->join(
                ['inventory_source_item' => $this->resource->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM)],
                'inventory_source_item.' . SourceItemInterface::SOURCE_CODE . '
                = stock_source_link.' . SourceItemInterface::SOURCE_CODE,
                ['inventory_source_item.sku']
            )->where(
                'stock_source_link.' . StockSourceLinkInterface::STOCK_ID . ' = ?',
                $stockId
            )->where(
                'inventory_source_item.' . SourceItemInterface::SKU . ' IN (?)',
                $skuArray
            )->group('inventory_source_item.' . SourceItemInterface::SKU);

        $results = $connection->fetchAll($select);

        foreach ($results as $result) {
            if (isset($result['sku'])) {
                $finalResults[$result['sku']] = true;
            }
        }

        return $finalResults;
    }
}
