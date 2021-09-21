<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

use Magento\CatalogInventory\Model\Configuration;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use ScandiPWA\Performance\Api\ProductsDataPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;

/**
 * Class Images
 * @package ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor
 */
class Stocks implements ProductsDataPostProcessorInterface
{
    use ResolveInfoFieldsTrait;

    const ONLY_X_LEFT_IN_STOCK = 'only_x_left_in_stock';

    const STOCK_STATUS = 'stock_status';

    const IN_STOCK = 'IN_STOCK';

    const OUT_OF_STOCK = 'OUT_OF_STOCK';

    /**
     * @var SourceItemRepositoryInterface
     */
    protected $stockRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var GetStockSourceLinksInterface
     */
    private $getStockSourceLinks;

    /**
     * @var GetStockIdForCurrentWebsite
     */
    private $getStockIdForCurrentWebsite;

    /**
     * @var GetProductSalableQtyInterface
     */
    private $getProductSalableQty;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * Stocks constructor.
     * @param SourceItemRepositoryInterface $stockRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        SourceItemRepositoryInterface $stockRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ScopeConfigInterface $scopeConfig,
        GetStockSourceLinksInterface $getStockSourceLinks,
        GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        GetProductSalableQtyInterface $getProductSalableQty
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->stockRepository = $stockRepository;
        $this->scopeConfig = $scopeConfig;
        $this->getStockSourceLinks = $getStockSourceLinks;
        $this->getStockIdForCurrentWebsite = $getStockIdForCurrentWebsite;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->getProductSalableQty = $getProductSalableQty;
    }

    /**
     * @param $node
     * @return string[]
     */
    protected function getFieldContent($node)
    {
        $stocks = [];
        $validFields = [
            self::ONLY_X_LEFT_IN_STOCK,
            self::STOCK_STATUS
        ];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            };

            $name = $selection->name->value;

            if (in_array($name, $validFields)) {
                $stocks[] = $name;
            }
        }

        return $stocks;
    }

    /**
     * @inheritDoc
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        $graphqlResolveInfo,
        ?array $processorOptions = []
    ): callable {
        $productStocks = [];

        $fields = $this->getFieldsFromProductInfo(
            $graphqlResolveInfo,
            $graphqlResolvePath
        );

        if (!count($fields)) {
            return function (&$productData) {
            };
        }

        $stockId = $this->getStockIdForCurrentWebsite->execute();

        if(!$stockId) {
            return function (&$productData) {
            };
        }

        $productSKUs = array_map(function ($product) {
            return $product->getSku();
        }, $products);

        $thresholdQty = 0;

        if (in_array(self::ONLY_X_LEFT_IN_STOCK, $fields)) {
            $thresholdQty = (float) $this->scopeConfig->getValue(
                Configuration::XML_PATH_STOCK_THRESHOLD_QTY,
                ScopeInterface::SCOPE_STORE
            );
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter(StockSourceLinkInterface::STOCK_ID, $stockId)
            ->create();

        $sourceLinks = $this->getStockSourceLinks->execute($criteria)->getItems();

        if (!count($sourceLinks)) {
            return function (&$productData) {
            };
        }

        $sourceCodes = array_map(function ($sourceLink) {
            return $sourceLink->getSourceCode();
        }, $sourceLinks);

        $criteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $productSKUs, 'in')
            ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCodes, 'in')
            ->create();

        $stockItems = $this->stockRepository->getList($criteria)->getItems();

        if (!count($stockItems)) {
            return function (&$productData) {
            };
        }

        $formattedStocks = [];

        foreach ($stockItems as $stockItem) {
            $leftInStock = null;
            $qty = $stockItem->getQuantity();
            $sku = $stockItem->getSku();

            $inStock = (($stockItem->getStatus() === SourceItemInterface::STATUS_IN_STOCK)
                        and $qty > 0)? true : false;

            if ($inStock) {
                $productSalableQty = $this->getProductSalableQty->execute($sku, $stockId);

                if ($productSalableQty > 0) {
                    $stockItemConfiguration = $this->getStockItemConfiguration->execute($sku, $stockId);
                    $minQty = $stockItemConfiguration->getMinQty();
                    if ($productSalableQty >= $minQty){
                        $stockLeft = $productSalableQty - $minQty;
                        $thresholdQty = $stockItemConfiguration->getStockThresholdQty();
                        if ($thresholdQty !== 0) {
                            $leftInStock = $stockLeft <= $thresholdQty ? (float)$stockLeft : null;
                        }
                    } else {
                        $inStock = false;
                    }
                } else {
                    $inStock = false;
                }

            }
            if (!isset($formattedStocks[$stockItem->getSku()])) {
                $formattedStocks[$stockItem->getSku()] = [self::STOCK_STATUS => self::OUT_OF_STOCK, self::ONLY_X_LEFT_IN_STOCK => 0];
            }

            if(isset($formattedStocks[$sku])
            && $formattedStocks[$sku][self::STOCK_STATUS] == self::IN_STOCK) {
                continue;
            }

            $formattedStocks[$stockItem->getSku()][self::STOCK_STATUS] = $formattedStocks[$stockItem->getSku()][self::STOCK_STATUS] || $inStock ? self::IN_STOCK : self::OUT_OF_STOCK;
            $formattedStocks[$stockItem->getSku()][self::ONLY_X_LEFT_IN_STOCK] = $formattedStocks[$stockItem->getSku()][self::ONLY_X_LEFT_IN_STOCK] + $leftInStock;
        }

        foreach ($products as $product) {
            $productId = $product->getId();
            $productSku = $product->getSku();

            if (isset($formattedStocks[$productSku])) {
                $productStocks[$productId] = $formattedStocks[$productSku];
            }
        }

        return function (&$productData) use ($productStocks) {
            if (!isset($productData['entity_id'])) {
                return;
            }

            $productId = $productData['entity_id'];

            if (!isset($productStocks[$productId])) {
                return;
            }

            foreach ($productStocks[$productId] as $stockType => $stockData) {
                $productData[$stockType] = $stockData;
            }
        };
    }
}
