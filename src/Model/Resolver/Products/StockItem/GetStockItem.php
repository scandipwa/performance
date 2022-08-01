<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\StockItem;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use ScandiPWA\Performance\Model\Inventory\GetLegacyStockItems;

class GetStockItem
{
    /**
     * @var GetStockIdForCurrentWebsite
     */
    protected GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite;

    /**
     * @var GetLegacyStockItems
     */
    protected GetLegacyStockItems $getLegacyStockItems;

    /**
     * @var array
     */
    protected array $unprocessedSkuQueue = [];

    /**
     * @var StockItemInterface[]
     */
    protected array $processedResults = [];

    /**
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetLegacyStockItems $getLegacyStockItems
     */
    public function __construct(
        GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        GetLegacyStockItems $getLegacyStockItems
    ) {
        $this->getStockIdForCurrentWebsite = $getStockIdForCurrentWebsite;
        $this->getLegacyStockItems = $getLegacyStockItems;
    }

    /**
     * @param ProductCriteria[] $criteriaList
     * @return IsProductSalableResultInterface[]
     */
    public function execute(array $criteriaList): array
    {
        if (count($this->unprocessedSkuQueue)) {
            $this->processQueue();
        }

        $skuArray = $finalResults = [];

        foreach ($criteriaList as $productCriteria) {
            $skuArray[] = $productCriteria->getSku();
        }

        // mapping a request index to specific sku
        // single SKU request may have more than one index, in case the criteria for current batch
        // somehow repeats the same sku multiple times
        $skuIndexes = [];

        foreach ($skuArray as $index => $sku) {
            $skuIndexes[$sku][] = $index;
        }

        foreach ($skuArray as $sku) {
            foreach ($skuIndexes[$sku] as $index) {
                $finalResults[$index] = $this->processedResults[$sku];
            }
        }

        return $finalResults;
    }

    /**
     * @return void
     */
    public function processQueue(): void
    {
        $skuArray = $this->unprocessedSkuQueue;
        $stockId = $this->getStockIdForCurrentWebsite->execute();

        // note: getLegacyStockItems caches its results, and is also used in stock_status fetch
        $results = $this->getLegacyStockItems->execute($skuArray);

        foreach ($results as $sku => $stockItem) {
            $this->processedResults[$sku] = $stockItem;
        }

        $this->unprocessedSkuQueue = [];
    }

    /**
     * Adds SKU to a queue
     * Will skip it if already processed
     * This is implemented, because the execution flow looks like this:
     * 1. StockStatus::convertToServiceArgument is run many times
     * but within individual BatchContractResolverWrapper instances per product type
     * 2. AreProductsSalable::execute is called for one BatchContractResolverWrapper instance & its request at a time
     * 3. Variants / bundles are creating their own BatchContractResolverWrapper instances and are building arguments
     * after the parent item requests resolve
     * This mechanism of adding skus to queue will resolve them for all product types at once, for parent products
     * @param string $sku
     */
    public function addSkuToQueue(string $sku): void
    {
        if (!isset($this->processedResults[$sku]) && !in_array($sku, $this->unprocessedSkuQueue)) {
            $this->unprocessedSkuQueue[] = $sku;
        }
    }
}
