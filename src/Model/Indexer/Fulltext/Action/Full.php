<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Raivis Dejus <info@scandiweb.com>
 * @copyright   Copyright (c) 2020 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Indexer\Fulltext\Action;

/**
 * PWA does not use "Catalog Search" ("catalogsearch_fulltext") index
 * Index rebuild for a store will do nothing to avoid unnecessary DB load
 */
class Full extends \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\Full
{
    public function rebuildStoreIndex($storeId, $productIds = null)
    {
        return; yield;
    }
}
