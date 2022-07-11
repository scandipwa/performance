<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory\AreProductsSalableCondition;

use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use ScandiPWA\Performance\Api\Inventory\AreProductsSalableInterface;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsConfigurationsInterface;

class BackOrderCondition implements AreProductsSalableInterface
{
    /**
     * @var GetStockItemsConfigurationsInterface
     */
    protected GetStockItemsConfigurationsInterface $getStockItemsConfigurations;

    /**
     * @param GetStockItemsConfigurationsInterface $getStockItemsConfigurations
     */
    public function __construct(
        GetStockItemsConfigurationsInterface $getStockItemsConfigurations
    ) {
        $this->getStockItemsConfigurations = $getStockItemsConfigurations;
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

            if ($stockItemConfiguration->getBackorders() !== StockItemConfigurationInterface::BACKORDERS_NO
                && $stockItemConfiguration->getMinQty() >= 0) {
                $result[$sku] = true;
            } else {
                $result[$sku] = false;
            }
        }

        return $result;
    }
}
