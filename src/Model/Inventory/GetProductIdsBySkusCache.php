<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\InventoryCatalog\Model\Cache\ProductIdsBySkusStorage;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

class GetProductIdsBySkusCache implements GetProductIdsBySkusInterface
{
    /**
     * @var GetProductIdsBySkus
     */
    protected GetProductIdsBySkus $getProductIdsBySkus;

    /**
     * @var ProductIdsBySkusStorage
     */
    protected ProductIdsBySkusStorage $cache;

    /**
     * @param GetProductIdsBySkus $getProductIdsBySkus
     * @param ProductIdsBySkusStorage $cache
     */
    public function __construct(
        GetProductIdsBySkus $getProductIdsBySkus,
        ProductIdsBySkusStorage $cache
    ) {
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->cache = $cache;
    }

    /**
     * Compared to core M2 - instead of throwing an exception, returns null for when sku does not exist
     * @param array $skus
     * @return array
     */
    public function execute(array $skus): array
    {
        $idsBySkus = [];
        $loadSkus = [];

        foreach ($skus as $sku) {
            $id = $this->cache->get((string) $sku);

            if ($id !== null) {
                $idsBySkus[$sku] = $id;
            } else {
                $loadSkus[] = $sku;
                $idsBySkus[$sku] = null;
            }
        }

        if (count($loadSkus)) {
            $loadedIdsBySkus = $this->getProductIdsBySkus->execute($loadSkus);

            foreach ($loadedIdsBySkus as $sku => $id) {
                $idsBySkus[$sku] = (int) $id;
                $this->cache->set((string) $sku, (int) $id);
            }
        }

        return $idsBySkus;
    }
}
