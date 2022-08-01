<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory\AreProductsSalableCondition;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use ScandiPWA\Performance\Api\Inventory\AreProductsSalableInterface;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsConfigurationsInterface;

class ManageStockCondition implements AreProductsSalableInterface
{
    /**
     * @var StockConfigurationInterface
     */
    protected StockConfigurationInterface $configuration;

    /**
     * @var GetStockItemsConfigurationsInterface
     */
    protected GetStockItemsConfigurationsInterface $getStockItemsConfigurations;

    /**
     * @param StockConfigurationInterface $configuration
     * @param GetStockItemsConfigurationsInterface $getStockItemsConfigurations
     */
    public function __construct(
        StockConfigurationInterface $configuration,
        GetStockItemsConfigurationsInterface $getStockItemsConfigurations
    ) {
        $this->getStockItemsConfigurations = $getStockItemsConfigurations;
        $this->configuration = $configuration;
    }

    /**
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $result = [];

        $stockItemConfigurations = $this->getStockItemsConfigurations->execute($skuArray, $stockId);

        foreach ($stockItemConfigurations as $sku => $stockItemConfiguration) {
            if (!$stockItemConfiguration) {
                $result[$sku] = false;

                continue;
            }

            if ($stockItemConfiguration->isUseConfigManageStock()) {
                $result[$sku] = $this->configuration->getManageStock() !== 1;
            } else {
                $result[$sku] = !$stockItemConfiguration->isManageStock();
            }
        }

        return $result;
    }
}
