<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Aleksandrs Mokans <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Plugin\CatalogInventory\Model\ResourceModel\Stock\Status;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryCatalog\Model\ResourceModel\AddIsInStockFilterToCollection;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;

/**
 * Adapt adding is in stock filter to collection for multi stocks.
 */
class AdaptAddIsInStockFilterToCollectionPlugin
{
    /**
     * @var GetStockIdForCurrentWebsite
     */
    private $getStockIdForCurrentWebsite;

    /**
     * @var AddIsInStockFilterToCollection
     */
    private $addIsInStockFilterToCollection;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param AddIsInStockFilterToCollection $addIsInStockFilterToCollection
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        AddIsInStockFilterToCollection $addIsInStockFilterToCollection,
        DefaultStockProviderInterface $defaultStockProvider
    ) {
        $this->getStockIdForCurrentWebsite = $getStockIdForCurrentWebsite;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->addIsInStockFilterToCollection = $addIsInStockFilterToCollection;
    }

    /**
     * Not joining inventory_stock_ view if the default stock provider is used
     * @param Status $stockStatus
     * @param callable $proceed
     * @param Collection $collection
     * @return Status
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAddIsInStockFilterToCollection(
        Status $stockStatus,
        callable $proceed,
        $collection
    ) {
        $stockId = $this->getStockIdForCurrentWebsite->execute();
        if ($stockId === $this->defaultStockProvider->getId()) {
            $proceed($collection);
        } else {
            $this->addIsInStockFilterToCollection->execute($collection, $stockId);
        }

        return $stockStatus;
    }
}
