<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory\AreProductsSalableCondition;

use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use ScandiPWA\Performance\Api\Inventory\GetReservationsQuantitiesInterface;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsDataInterface;
use ScandiPWA\Performance\Api\Inventory\AreProductsSalableInterface;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsConfigurationsInterface;

class AreSalableWithReservationsCondition implements AreProductsSalableInterface
{
    /**
     * @var GetStockItemsConfigurationsInterface
     */
    protected GetStockItemsConfigurationsInterface $getStockItemsConfigurations;

    /**
     * @var GetProductTypesBySkusInterface
     */
    protected GetProductTypesBySkusInterface $getProductTypesBySkus;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface
     */
    protected IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType;

    /**
     * @var GetStockItemsDataInterface
     */
    protected GetStockItemsDataInterface $getStockItemsData;

    /**
     * @var GetReservationsQuantitiesInterface
     */
    protected GetReservationsQuantitiesInterface $getReservationsQuantities;

    /**
     * @var GetStockItemConfigurationInterface
     */
    protected GetStockItemConfigurationInterface $getStockItemConfiguration;

    /**
     * @param GetStockItemsConfigurationsInterface $getStockItemsConfigurations
     * @param GetStockItemsDataInterface $getStockItemsData
     * @param GetReservationsQuantitiesInterface $getReservationsQuantities
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     */
    public function __construct(
        GetStockItemsConfigurationsInterface $getStockItemsConfigurations,
        GetStockItemsDataInterface $getStockItemsData,
        GetReservationsQuantitiesInterface $getReservationsQuantities,
        IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowedForProductType,
        GetProductTypesBySkusInterface $getProductTypesBySkus
    ) {
        $this->getStockItemsConfigurations = $getStockItemsConfigurations;
        $this->getStockItemsData = $getStockItemsData;
        $this->getReservationsQuantities = $getReservationsQuantities;
        $this->isSourceItemManagementAllowedForProductType = $isSourceItemManagementAllowedForProductType;
        $this->getProductTypesBySkus = $getProductTypesBySkus;
    }

    /**
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $result = [];
        $skusToCheck = array_flip($skuArray);

        $stockItemDataArray = $this->getStockItemsData->execute($skuArray, $stockId);

        foreach ($stockItemDataArray as $sku => $stockItemData) {
            if (null === $stockItemData) {
                // Sku is not assigned to Stock
                $result[$sku] = false;
                unset($skusToCheck[$sku]);
                continue;
            }

            // these values will be taken from cache, so can be executed individually
            $productType = $this->getProductTypesBySkus->execute([$sku])[$sku];

            // source item management not active for product type, do not check reservations
            if (false === $this->isSourceItemManagementAllowedForProductType->execute($productType)) {
                $result[$sku] = (bool)$stockItemData[GetStockItemsDataInterface::IS_SALABLE];
                unset($skusToCheck[$sku]);
            }
        }

        if (count($skusToCheck)) {
            // need to check reservations for the remaining skus
            $stockItemConfigurations = $this->getStockItemsConfigurations->execute(array_keys($skusToCheck), $stockId);
            $reservationQtys = $this->getReservationsQuantities->execute(array_keys($skusToCheck), $stockId);

            foreach ($stockItemConfigurations as $sku => $stockItemConfiguration) {
                $qtyWithReservation = $stockItemDataArray[$sku][GetStockItemsDataInterface::QUANTITY] + $reservationQtys[$sku];
                $result[$sku] = $qtyWithReservation > $stockItemConfiguration->getMinQty();
            }
        }

        return $result;
    }
}
