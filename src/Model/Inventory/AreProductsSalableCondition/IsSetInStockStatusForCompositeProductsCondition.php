<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory\AreProductsSalableCondition;

use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;
use ScandiPWA\Performance\Api\Inventory\AreProductsSalableInterface;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsConfigurationsInterface;

class IsSetInStockStatusForCompositeProductsCondition implements AreProductsSalableInterface
{
    /**
     * @var GetStockItemsConfigurationsInterface
     */
    protected GetStockItemsConfigurationsInterface $getStockItemsConfigurations;

    /**
     * @var IsSourceItemManagementAllowedForSkuInterface
     */
    protected IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku;

    /**
     * @param IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku
     * @param GetStockItemsConfigurationsInterface $getStockItemsConfigurations
     */
    public function __construct(
        IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku,
        GetStockItemsConfigurationsInterface $getStockItemsConfigurations
    ) {
        $this->getStockItemsConfigurations = $getStockItemsConfigurations;
        $this->isSourceItemManagementAllowedForSku = $isSourceItemManagementAllowedForSku;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $result = [];
        $skusToCheck = array_flip($skuArray);

        foreach ($skuArray as $sku) {
            if ($this->isSourceItemManagementAllowedForSku->execute((string)$sku)) {
                $result[$sku] = true;
                unset($skusToCheck[$sku]);
            }
        }

        $stockItemsConfigurations = $this->getStockItemsConfigurations->execute(array_keys($skusToCheck), $stockId);

        foreach ($stockItemsConfigurations as $sku => $stockItemConfiguration) {
            if ($stockItemConfiguration) {
                $result[$sku] = $stockItemConfiguration->getExtensionAttributes()->getIsInStock();
            } else {
                $result[$sku] = false;
            }
        }

        return $result;
    }
}
