<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use ScandiPWA\Performance\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Swatches\Helper\Data;
use ScandiPWA\Performance\Api\ProductsDataPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

/**
 * Class Attributes
 * @package ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor
 */
class Attributes implements ProductsDataPostProcessorInterface
{
    use ResolveInfoFieldsTrait;

    const ATTRIBUTES = 'attributes';

    /**
     * @var Data
     */
    protected $swatchHelper;

    /**
     * @var CollectionFactory
     */
    protected $productCollection;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    protected $attributeRepository;

    /**
     * Attributes constructor.
     * @param Data $swatchHelper
     * @param CollectionFactory $productCollection
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductAttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        Data $swatchHelper,
        CollectionFactory $productCollection,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductAttributeRepositoryInterface $attributeRepository
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->productCollection = $productCollection;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @inheritDoc
     */
    protected function getFieldContent($node)
    {
        $attributes = [];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            }

            if ($selection->name->value === self::ATTRIBUTES) {
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
     * Append product attribute data with value, if value not found, strip the attribute from response
     * @param $attributes ProductAttributeInterface[]
     * @param $productIds array
     * @param $productAttributes
     * @throws LocalizedException
     */
    protected function appendWithValue(
        array $attributes,
        array $productIds,
        array &$productAttributes
    ): void {
        $productCollection = $this->productCollection->create()
            ->addAttributeToSelect($attributes)
            ->addIdFilter($productIds)
            ->getItems();

        /** @var Product $product */
        foreach ($productCollection as $product) {
            $productId = $product->getId();

            foreach ($attributes as $attributeCode => $attribute) {
                $attributeValue = $product->getData($attributeCode);

                if (!$attributeValue) {
                    // Remove all empty attributes
                    continue;
                }

                // Append value to existing attribute data
                $productAttributes[$productId][$attributeCode]['attribute_value'] = $attributeValue;
            }
        }
    }

    /**
     * Append options to attribute options
     *
     * @param $attributes ProductAttributeInterface[]
     * @param $products ExtensibleDataInterface[]
     * @param $productAttributes array
     * @param $swatchAttributes array
     */
    protected function appendWithOptions(
        array $attributes,
        array $products,
        array $swatchAttributes,
        array &$productAttributes
    ): void {
        $attributeCodes = array_keys($attributes);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('main_table.attribute_code', $attributeCodes, 'in')
            ->create();

        /** @var SearchCriteriaInterface $searchCriteria */
        $attributeRepository = $this->attributeRepository->getList($searchCriteria);
        $detailedAttributes = $attributeRepository->getItems();

        // To collect ids of options, to later load swatch data
        $optionIds = [];

        // Loop again, get options sorted in the right places
        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();

            $configuration = $product->getTypeId() === 'configurable'
                ? $product->getTypeInstance()->getConfigurableOptions($product)
                : [];

            foreach ($detailedAttributes as $attribute) {
                $key = $attribute->getAttributeCode();

                if (!isset($productAttributes[$id][$key])) {
                    continue;
                }

                $productAttributes[$id][$key]['attribute_options'] = [];
                $variantAttributeValues = [];

                if ($product->getTypeId() === 'configurable') {
                    $attributeId = $attribute->getAttributeId();
                    $productAttributeVariants = $configuration[$attributeId] ?? [];

                    $variantAttributeValues = array_filter(
                        array_column($productAttributeVariants, 'value_index')
                    );
                }

                if (
                    !isset($productAttributes[$id][$key]['attribute_value'])
                    && !count($variantAttributeValues)
                ) {
                    continue;
                }

                // Merge all attribute values into one array(map) and flip values with keys (convert to hash map)
                // This used to bring faster access check for value existence
                // Hash key variable check is faster then traditional search
                $values = array_flip( // Flip Array
                    array_merge( // phpcs:ignore
                        array_filter( // explode might return array with empty value, remove such values
                            explode(',', $productAttributes[$id][$key]['attribute_value'] ?? '')
                        ),
                        $variantAttributeValues
                    )
                );

                $options = $attribute->getOptions();
                array_shift($options);
                $productAttributes[$id][$key]['attribute_options'] = [];

                foreach ($options as $option) {
                    $value = $option->getValue();

                    if (!isset($values[$value])) {
                        continue;
                    }

                    $optionIds[] = $value;
                    $productAttributes[$id][$key]['attribute_options'][$value] = [
                        'value' => $value,
                        'label' => $option->getLabel()
                    ];
                }
            }
        }

        if (!empty($swatchAttributes)) {
            $this->appendWithSwatchOptions(
                $swatchAttributes,
                $optionIds,
                $products,
                $detailedAttributes,
                $productAttributes
            );
        }
    }

    /**
     * @param array $swatchAttributes
     * @param array $optionIds
     * @param array $products
     * @param array $detailedAttributes
     * @param array $productAttributes
     */
    protected function appendWithSwatchOptions(
        array $swatchAttributes,
        array $optionIds,
        array $products,
        array $detailedAttributes,
        &$productAttributes
    ) {
        array_unique($optionIds);
        $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($optionIds);

        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();

            foreach ($detailedAttributes as $attribute) {
                $key = $attribute->getAttributeCode();

                if (in_array($key, $swatchAttributes)) {
                    $options = $attribute->getOptions();

                    foreach ($options as $option) {
                        $value = $option->getValue();

                        if (isset($swatchOptions[$value])
                            && isset($productAttributes[$id][$key]['attribute_options'][$value])) {
                            $productAttributes[$id][$key]['attribute_options'][$value]['swatch_data']
                                = $swatchOptions[$value];
                        }
                    }
                }
            }
        }
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        $graphqlResolveInfo,
        ?array $processorOptions = []
    ): callable {
        $productIds = [];
        $productAttributes = [];
        $attributes = [];
        $swatchAttributes = [];

        $fields = $this->getFieldsFromProductInfo(
            $graphqlResolveInfo,
            $graphqlResolvePath
        );

        if (!count($fields)) {
            // Do nothing with the product
            return function (&$productData) {
            };
        }

        $isCollectOptions = in_array('attribute_options', $fields);

        foreach ($products as $product) {
            $productId = $product->getId();
            $productIds[] = $productId;

            // Create storage for future attributes
            $productAttributes[$productId] = [];

            /**
             * @var Attribute $attribute
             */
            foreach ($product->getAttributes() as $attributeCode => $attribute) {
                if (!$attribute->getIsVisibleOnFront()) {
                    continue;
                }

                $productAttributes[$productId][$attributeCode] = [
                    'attribute_code' => $attribute->getAttributeCode(),
                    'attribute_type' => $attribute->getFrontendInput(),
                    'attribute_label' => $attribute->getStoreLabel(),
                    'attribute_id' => $attribute->getAttributeId(),
                    'attribute_options' => []
                ];

                // Collect valid attributes
                if (!isset($attributes[$attributeCode])) {
                    $attributes[$attributeCode] = $attribute;

                    // Collect all swatches (we will need additional data for them)
                    /** @var Attribute $attribute */
                    if ($isCollectOptions && $this->swatchHelper->isSwatchAttribute($attribute)) {
                        $swatchAttributes[] = $attributeCode;
                    }
                }
            }
        }

        $this->appendWithValue(
            $attributes,
            $productIds,
            $productAttributes
        );

        if ($isCollectOptions) {
            $this->appendWithOptions(
                $attributes,
                $products,
                $swatchAttributes,
                $productAttributes
            );
        }

        return function (&$productData) use ($productAttributes) {
            if (!isset($productData['entity_id'])) {
                return;
            }

            $productId = $productData['entity_id'];

            if (!isset($productAttributes[$productId])) {
                return;
            }

            $productData[self::ATTRIBUTES] = $productAttributes[$productId];
        };
    }
}
