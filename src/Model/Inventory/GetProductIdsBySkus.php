<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

class GetProductIdsBySkus implements GetProductIdsBySkusInterface
{
    /**
     * @var ProductResourceModel
     */
    protected ProductResourceModel $productResource;

    /**
     * @param ProductResourceModel $productResource
     */
    public function __construct(
        ProductResourceModel $productResource
    ) {
        $this->productResource = $productResource;
    }

    /**
     * @param array $skus
     * @return array
     */
    public function execute(array $skus): array
    {
        $idsBySkus = $this->productResource->getProductsIdsBySkus($skus);
        $notFoundSkus = array_diff($skus, array_keys($idsBySkus));

        foreach ($notFoundSkus as $sku) {
            // Rewrite: as opposed to throwing NoSuchEntityException
            $idsBySkus[$sku] = null;
        }

        return $idsBySkus;
    }
}
