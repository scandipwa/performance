<?php
/**
 * ScandiPWA_Performance
 *
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      <info@scandiweb.com>
 * @copyright   Copyright (c) 2021 Scandiweb, Ltd (https://scandiweb.com)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

use Exception;
use Magento\Catalog\Helper\Data as TaxHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\Discount;
use Magento\CatalogGraphQl\Model\Resolver\Product\Price\ProviderPool as PriceProviderPool;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\Performance\Api\ProductsDataPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

/**
 * Class PriceRange
 * @package ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor
 */
class PriceRange implements ProductsDataPostProcessorInterface
{
    use ResolveInfoFieldsTrait;

    const PRICE_RANGE_FIELD = 's_price_range';

    const XML_PRICE_INCLUDES_TAX = 'tax/calculation/price_includes_tax';
    const FINAL_PRICE = 'final_price';

    /**
     * @var float
     */
    protected $zeroThreshold = 0.0001;

    /**
     * @var Discount
     */
    protected $discount;

    /**
     * @var PriceProviderPool
     */
    protected $priceProviderPool;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * PriceRange constructor
     *
     * @param PriceProviderPool $priceProviderPool
     * @param Discount $discount
     * @param PriceCurrencyInterface $priceCurrency
     * @param ScopeConfigInterface $scopeConfig
     * @param TaxHelper $taxHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PriceProviderPool $priceProviderPool,
        Discount $discount,
        PriceCurrencyInterface $priceCurrency,
        ScopeConfigInterface $scopeConfig,
        TaxHelper $taxHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->priceProviderPool = $priceProviderPool;
        $this->discount = $discount;
        $this->priceCurrency = $priceCurrency;
        $this->scopeConfig = $scopeConfig;
        $this->taxHelper = $taxHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    protected function getFieldContent($node): array
    {
        $attributes = [];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            }

            if ($selection->name->value === self::PRICE_RANGE_FIELD) {
                $attributes = $selection->selectionSet->selections;
                break;
            }
        }

        $fieldNames = [];

        if (is_iterable($attributes)) {
            foreach ($attributes as $attribute) {
                $fieldNames[] = $attribute->name->value;
            }
        }

        return $fieldNames;
    }

    /**
     * Get formatted minimum product price
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return array
     */
    protected function getMinimumProductPrice(Product $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());

        $regularPrice = $priceProvider->getMinimalRegularPrice($product)->getValue();

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $finalPrice = $product->getPriceInfo()->getPrice(self::FINAL_PRICE)->getValue();
        } else {
            $finalPrice = $priceProvider->getMinimalFinalPrice($product)->getValue();
        }

        $regularPriceExclTax = $priceProvider->getMinimalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = $priceProvider->getMinimalFinalPrice($product)->getBaseAmount();

        $defaultPrices = $this->getDefaultPrices($product, $priceProvider);

        $discount = $defaultPrices['discount'] ?? $this->calculateDiscount($product, $regularPrice, $finalPrice);

        $defaultRegularPrice = $defaultPrices['defaultRegularPrice'] ?? 0;
        $defaultFinalPrice = $defaultPrices['defaultFinalPrice'] ?? 0;
        $defaultFinalPriceExclTax = $defaultPrices['defaultFinalPriceExclTax'] ?? 0;


        $minPriceArray = $this->formatPrice(
            $regularPrice, $regularPriceExclTax, $finalPrice, $finalPriceExclTax,
            $defaultRegularPrice, $defaultFinalPrice, $defaultFinalPriceExclTax, $discount, $store
        );
        $minPriceArray['model'] = $product;
        return $minPriceArray;
    }

    /**
     * Get formatted maximum product price
     *
     * @param Product $product
     * @param StoreInterface $store
     * @return array
     */
    protected function getMaximumProductPrice(Product $product, StoreInterface $store): array
    {
        $priceProvider = $this->priceProviderPool->getProviderByProductType($product->getTypeId());

        $regularPrice = $priceProvider->getMaximalRegularPrice($product)->getValue();
        $finalPrice = $priceProvider->getMaximalFinalPrice($product)->getValue();

        $regularPriceExclTax = $priceProvider->getMaximalRegularPrice($product)->getBaseAmount();
        $finalPriceExclTax = $priceProvider->getMaximalFinalPrice($product)->getBaseAmount();

        $defaultPrices = $this->getDefaultPrices($product, $priceProvider);

        $discount = $defaultPrices['discount'] ?? $this->calculateDiscount($product, $regularPrice, $finalPrice);

        $defaultRegularPrice = $defaultPrices['defaultRegularPrice'] ?? 0;
        $defaultFinalPrice = $defaultPrices['defaultFinalPrice'] ?? 0;
        $defaultFinalPriceExclTax = $defaultPrices['defaultFinalPriceExclTax'] ?? 0;

        $maxPriceArray = $this->formatPrice(
            $regularPrice, $regularPriceExclTax, $finalPrice, $finalPriceExclTax,
            $defaultRegularPrice, $defaultFinalPrice, $defaultFinalPriceExclTax, $discount, $store
        );
        $maxPriceArray['model'] = $product;
        return $maxPriceArray;
    }

    /**
     * @param Product $product
     * @param $priceProvider
     * @return array
     */
    public function getDefaultPrices(Product $product, $priceProvider) {
        if($product->getTypeId() == ProductType::TYPE_SIMPLE) {
            $priceInfo = $product->getPriceInfo();
            $defaultRegularPrice = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue();
            $defaultFinalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();

            return [
                'defaultRegularPrice' => $defaultRegularPrice,
                'defaultFinalPrice' => $defaultFinalPrice,
                'defaultFinalPriceExclTax' => $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount(),
                'discount' => $this->calculateDiscount($product, $defaultRegularPrice, $defaultFinalPrice)
            ];
        } else {
            return [
                'defaultRegularPrice' => $this->taxHelper->getTaxPrice($product, $product->getPrice(), $this->isPriceIncludesTax()),
                'defaultFinalPrice' => round($priceProvider->getRegularPrice($product)->getValue(), 2),
                'defaultFinalPriceExclTax' => $priceProvider->getRegularPrice($product)->getBaseAmount()
            ];
        }
    }

    /**
     * Format price for GraphQl output
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @param StoreInterface $store
     * @return array
     */
    protected function formatPrice(
        float $regularPrice,
        float $regularPriceExclTax,
        float $finalPrice,
        float $finalPriceExclTax,
        float $defaultRegularPrice,
        float $defaultFinalPrice,
        float $defaultFinalPriceExclTax,
        array $discount,
        StoreInterface $store
    ): array {
        $currency = $store->getCurrentCurrencyCode();

        return [
            'regular_price' => [
                'value' => $regularPrice,
                'currency' => $currency
            ],
            'regular_price_excl_tax' => [
                'value' => $regularPriceExclTax,
                'currency' => $currency
            ],
            'final_price' => [
                'value' => $finalPrice,
                'currency' => $currency
            ],
            'final_price_excl_tax' => [
                'value' => $finalPriceExclTax,
                'currency' => $currency
            ],
            'default_price' => [
                'value' => $defaultRegularPrice,
                'currency' => $currency
            ],
            'default_final_price' => [
                'value' => $defaultFinalPrice,
                'currency' => $currency
            ],
            'default_final_price_excl_tax' => [
                'value' => $defaultFinalPriceExclTax,
                'currency' => $currency
            ],
            'discount' => $discount
        ];
    }

    /**
     * Calculates correct discount amount
     * - Bundle items can contain $regularPrice and $finalFrice from two different
     * - product instances, thus we are intersted in BE set special price procentage.
     *
     * @param Product $product
     * @param float $regularPrice
     * @param float $finalPrice
     * @return array
     */
    protected function calculateDiscount(Product $product, float $regularPrice, float $finalPrice) : array
    {
        if ($product->getTypeId() !== 'bundle') {
            return [
                'amount_off' => $this->getPriceDifferenceAsValue($regularPrice, $finalPrice),
                'percent_off' => $this->getPriceDifferenceAsPercent($regularPrice, $finalPrice)
            ];
        }

        // Bundle products have special price set in % (percents)
        $specialPricePrecentage = $this->getSpecialProductPrice($product);
        $percentOff = is_null($specialPricePrecentage) ? 0 : 100 - $specialPricePrecentage;

        return [
            'amount_off' => $regularPrice * ($percentOff / 100),
            'percent_off' => $percentOff
        ];
    }

    /**
     * Get value difference between two prices
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @return float
     */
    protected function getPriceDifferenceAsValue(float $regularPrice, float $finalPrice)
    {
        $difference = $regularPrice - $finalPrice;

        if ($difference <= $this->zeroThreshold) {
            return 0;
        }

        return round($difference, 2);
    }

    /**
     * Get percent difference between two prices
     *
     * @param float $regularPrice
     * @param float $finalPrice
     * @return float
     */
    protected function getPriceDifferenceAsPercent(float $regularPrice, float $finalPrice)
    {
        $difference = $this->getPriceDifferenceAsValue($regularPrice, $finalPrice);

        if ($difference <= $this->zeroThreshold || $regularPrice <= $this->zeroThreshold) {
            return 0;
        }

        return round(($difference / $regularPrice) * 100, 8);
    }

    /**
     * Gets [active] special price value
     *
     * @param Product $product
     * @return float
     */
    protected function getSpecialProductPrice(Product $product): ?float
    {
        $specialPrice = $product->getSpecialPrice();
        if (!$specialPrice) {
            return null;
        }

        // Special price range
        $from = strtotime($product->getSpecialFromDate());
        $to = $product->getSpecialToDate() === null ? null : strtotime($product->getSpecialToDate());
        $now = time();

        return ($now >= $from && $now <= $to) || ($now >= $from && is_null($to)) ? (float)$specialPrice : null;
    }

    protected function isPriceIncludesTax(){
        return $this->scopeConfig->getValue(
            self::XML_PRICE_INCLUDES_TAX,
            ScopeInterface::SCOPE_STORES
        );
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        $graphqlResolveInfo,
        ?array $processorOptions = []
    ): callable {
        $productPrices = [];

        $fields = $this->getFieldsFromProductInfo(
            $graphqlResolveInfo,
            $graphqlResolvePath
        );

        if (!count($fields)) {
            return function (&$productData) {
            };
        }

        $store = $this->storeManager->getStore();

        /** @var Product $product */
        foreach ($products as $product) {
            $productId = $product->getId();
            $productPrices[$productId] = [];

            if (in_array('minimum_price', $fields)) {
                $productPrices[$productId]['minimum_price'] =  $this->getMinimumProductPrice($product, $store);
            }

            if (in_array('maximum_price', $fields)) {
                $productPrices[$productId]['maximum_price'] =  $this->getMaximumProductPrice($product, $store);
            }
        }

        return function (&$productData) use ($productPrices) {
            if (!isset($productData['entity_id'])) {
                return;
            }

            $productId = $productData['entity_id'];

            if (!isset($productPrices[$productId])) {
                return;
            }

            foreach ($productPrices[$productId] as $priceType => $priceData) {
                $productData[$priceType] = $priceData;
            }
        };
    }
}
