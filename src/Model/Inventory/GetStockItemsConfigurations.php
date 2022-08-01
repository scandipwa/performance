<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;
use Magento\InventoryConfiguration\Model\StockItemConfigurationFactory;
use ScandiPWA\Performance\Api\Inventory\GetStockItemsConfigurationsInterface;
use ScandiPWA\Performance\Api\Inventory\AreProductsAssignedToStockInterface;

class GetStockItemsConfigurations implements GetStockItemsConfigurationsInterface
{
    /**
     * @var GetLegacyStockItems
     */
    protected GetLegacyStockItems $getLegacyStockItems;

    /**
     * @var StockItemConfigurationFactory
     */
    protected StockItemConfigurationFactory $stockItemConfigurationFactory;

    /**
     * @var DefaultStockProviderInterface
     */
    protected DefaultStockProviderInterface $defaultStockProvider;

    /**
     * @var IsSourceItemManagementAllowedForSkuInterface
     */
    protected IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku;

    /**
     * @var GetProductTypesBySkusInterface
     */
    protected GetProductTypesBySkusInterface $getProductTypesBySkus;

    /**
     * @var AreProductsAssignedToStockInterface
     */
    protected AreProductsAssignedToStockInterface $areProductsAssignedToStock;

    /**
     * @param GetLegacyStockItems $getLegacyStockItems
     * @param StockItemConfigurationFactory $stockItemConfigurationFactory
     * @param AreProductsAssignedToStockInterface $areProductsAssignedToStock
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     */
    public function __construct(
        GetLegacyStockItems $getLegacyStockItems,
        StockItemConfigurationFactory $stockItemConfigurationFactory,
        AreProductsAssignedToStockInterface $areProductsAssignedToStock,
        DefaultStockProviderInterface $defaultStockProvider,
        IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku,
        GetProductTypesBySkusInterface $getProductTypesBySkus
    ) {
        $this->getLegacyStockItems = $getLegacyStockItems;
        $this->stockItemConfigurationFactory = $stockItemConfigurationFactory;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->isSourceItemManagementAllowedForSku = $isSourceItemManagementAllowedForSku;
        $this->getProductTypesBySkus = $getProductTypesBySkus;
        $this->areProductsAssignedToStock = $areProductsAssignedToStock;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $skuArray, int $stockId): array
    {
        // this will load at once & cache the product types for all skus, is used in multiple operations later
        $this->getProductTypesBySkus->execute($skuArray);
        $areProductsAssignedToStock = $this->areProductsAssignedToStock->execute($skuArray, $stockId);

        $result = [];
        $skusToCheck = array_flip($skuArray);

        foreach ($skuArray as $sku) {
            if ($this->defaultStockProvider->getId() !== $stockId
                && true === $this->isSourceItemManagementAllowedForSku->execute((string)$sku)
                && false === $areProductsAssignedToStock[$sku]) {
                // used instead of SkuIsNotAssignedToStockException in core
                $result[$sku] = false;

                unset($skusToCheck[$sku]);
            }
        }

        $stockItems = $this->getLegacyStockItems->execute(array_keys($skusToCheck));

        foreach ($stockItems as $sku => $stockItem) {
            $result[$sku] = $this->stockItemConfigurationFactory->create(
                [
                    'stockItem' => $stockItem
                ]
            );

            // logic from LoadIsInStockPlugin
            $extensionAttributes = $result[$sku]->getExtensionAttributes();
            $extensionAttributes->setIsInStock((bool)(int)$stockItem->getIsInStock());
            $result[$sku]->setExtensionAttributes($extensionAttributes);
        }

        return $result;
    }
}
