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
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use ScandiPWA\Performance\Model\ResourceModel\Product\CollectionFactory;
use Magento\Swatches\Helper\Data;
use ScandiPWA\Performance\Api\ProductsDataPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;
use Magento\Eav\Api\Data\AttributeGroupInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory as GroupCollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;

/**
 * Class Attributes
 * @package ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor
 */
class Attributes implements ProductsDataPostProcessorInterface
{
    use ResolveInfoFieldsTrait;

    const ATTRIBUTES = 's_attributes';

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
     * @var GroupCollectionFactory
     */
    protected $groupCollection;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var array
     */
    protected $configurableAttributeIds = [];

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
        ProductAttributeRepositoryInterface $attributeRepository,
        GroupCollectionFactory $groupCollection,
        ResourceConnection $resourceConnection
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->productCollection = $productCollection;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
        $this->groupCollection = $groupCollection;
        $this->resourceConnection = $resourceConnection;
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

                // Remove all empty attributes
                if (!isset($attributeValue)) {
                    continue;
                }

                // Append value to existing attribute data
                $productAttributes[$productId][$attributeCode]['attribute_value'] = $attributeValue;
            }
        }
    }

    /**
     * Append attribute data with attribute group value per set
     * @param ProductAttributeInterface[] $attributes
     * @param array $attributeSetIds
     * @param array $attributeDataBySetId
     * @throws LocalizedException
     */
    protected function appendWithGroup(
        array $attributes,
        array $attributeSetIds,
        array &$attributeDataBySetId
    ): void {
        // Retrieve all groups with used product Set Ids
        $groupCollection = $this->groupCollection->create()
            ->addFieldToFilter('attribute_set_id', ['in' => $attributeSetIds])
            ->load();

        foreach ($attributeDataBySetId as $attributeSetId => $attributeData) {
            $dataPath = sprintf('attribute_set_info/%s/group_id', $attributeSetId);

            foreach ($attributeData as $attributeCode => $data) {
                $attributeGroupId = $attributes[$attributeCode]->getData($dataPath);

                // Find the correct group for every attribute
                /** @var AttributeGroupInterface $group */
                foreach ($groupCollection as $group) {
                    if ($attributeGroupId === $group->getAttributeGroupId()) {
                        $attributeDataBySetId[$attributeSetId][$attributeCode]['attribute_group_name'] = $group->getAttributeGroupName();
                        $attributeDataBySetId[$attributeSetId][$attributeCode]['attribute_group_id'] = $group->getAttributeGroupId();
                        $attributeDataBySetId[$attributeSetId][$attributeCode]['attribute_group_code'] = $group->getAttributeGroupCode();

                        break;
                    }
                }
            }
        }
    }

    /**
     * Append options to attribute options
     *
     * @param $attributes ProductAttributeInterface[]
     * @param $products ExtensibleDataInterface[]
     * @param $productAttributes array
     */
    protected function appendWithOptions(
        array $attributes,
        array $products,
        array &$productAttributes
    ): void {
        // To collect ids of options, to later load swatch data
        $optionIds = [];
        $formattedAttributes = [];
        $swatchAttributes = [];

        // Format attribute to use them later
        foreach ($attributes as $attributeCode => $attribute) {
            $options = $attribute->getOptions();

            if (!$options) {
                continue;
            }

            // Remove first option from array since it is empty
            array_shift($options);

            $formattedAttributes[$attributeCode] = [
                'attribute_id' => $attribute->getAttributeId(),
                'attribute_options' => $options
            ];
        }

        foreach ($formattedAttributes as $attributeCode => $attribute) {
            $attributeId = $attribute['attribute_id'];

            foreach ($products as $product) {
                $productId = $product->getId();
                $configuration = $product->getTypeId() === ConfigurableType::TYPE_CODE ?
                    $product->getTypeInstance()->getConfigurableOptions($product)
                    : [];

                if (!isset($productAttributes[$productId][$attributeCode])) {
                    continue;
                }

                $productAttributeVariants = $configuration[$attributeId] ?? [];

                $variantAttributeValues = array_filter(
                    array_column($productAttributeVariants, 'value_index')
                );

                if (!isset($productAttributes[$productId][$attributeCode]['attribute_value'])
                    && !count($variantAttributeValues)
                ) {
                    // Remove attribute if it has no value and empty options
                    unset($productAttributes[$productId][$attributeCode]);
                    continue;
                }

                // Merge all attribute values into one array(map) and flip values with keys (convert to hash map)
                // This used to bring faster access check for value existence
                // Hash key variable check is faster then traditional search
                $values = array_flip( // Flip Array
                    array_merge( // phpcs:ignore
                        array_filter( // explode might return array with empty value, remove such values
                            explode(',', $productAttributes[$productId][$attributeCode]['attribute_value'] ?? '')
                        ),
                        $variantAttributeValues
                    )
                );

                $productAttributes[$productId][$attributeCode]['attribute_options'] = [];

                foreach ($attribute['attribute_options'] as $option) {
                    $value = $option->getValue();

                    if (!isset($values[$value])) {
                        continue;
                    }

                    $optionIds[] = $value;
                    $productAttributes[$productId][$attributeCode]['attribute_options'][$value] = [
                        'value' => $value,
                        'label' => $option->getLabel()
                    ];
                }
            }
        }

        foreach ($attributes as $attributeCode => $attribute) {
            // Collect all swatches (we will need additional data for them)
            if ($this->swatchHelper->isSwatchAttribute($attribute)) {
                $swatchAttributes[] = $attributeCode;
            }
        }

        if (!empty($swatchAttributes)) {
            $this->appendWithSwatchOptions(
                $swatchAttributes,
                $optionIds,
                $products,
                $formattedAttributes,
                $productAttributes
            );
        }
    }

    /**
     * @param array $swatchAttributes
     * @param array $optionIds
     * @param array $products
     * @param array $formattedAttributes
     * @param array $productAttributes
     */
    protected function appendWithSwatchOptions(
        array $swatchAttributes,
        array $optionIds,
        array $products,
        array $formattedAttributes,
        array &$productAttributes
    ) {
        array_unique($optionIds);
        $swatchOptions = $this->swatchHelper->getSwatchesByOptionsId($optionIds);

        if (empty($swatchOptions)) {
            return;
        }

        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();

            foreach ($formattedAttributes as $attributeCode => $attribute) {
                if (in_array($attributeCode, $swatchAttributes)) {
                    foreach ($attribute['attribute_options'] as $option) {
                        $value = $option->getValue();

                        if (isset($swatchOptions[$value])
                            && isset($productAttributes[$id][$attributeCode]['attribute_options'][$value])) {
                            $productAttributes[$id][$attributeCode]['attribute_options'][$value]['swatch_data']
                                = $swatchOptions[$value];
                        }
                    }
                }
            }
        }
    }

    /**
     * This function collects attribute ids
     * from catalog_product_super_attribute table
     * to get which is used for configurable products
     */
    public function getConfigurableAttributeIds()
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();

        $select->from(
            $connection->getTableName('catalog_product_super_attribute'),
            'attribute_id'
        )->distinct(true);

        $this->configurableAttributeIds = $connection->fetchCol($select);
    }

    protected function isAttributeSkipped(
        Attribute $attribute,
        bool $isSingleProduct,
        bool $isCompare,
        bool $isCartProduct
    ): bool {
        /**
         * If attribute is in configurable attribute pool, we need to
         * pass it, so on frontend it will always present as an option
         * for config products.
         *
         * On PDP, If attribute is not visible on storefront
         * or has no label then we should skip it.
         *
         * On PLP, KEEP attribute if it is used on PLP.
         * This means if not visible on PLP we should SKIP it.
         *
         * Don't skip if attribute is for the compare page
         */
        if (in_array($attribute->getId(), $this->configurableAttributeIds)) {
            if(!$attribute->getUsedInProductListing() && !$isSingleProduct && !$isCartProduct) {
                return true;
            }
            return false;
        }

        if ($isSingleProduct) {
            return !$attribute->getIsVisibleOnFront() || !$attribute->getStoreLabel();
        }

        if ($isCompare) {
            return !$attribute->getIsComparable() || !$attribute->getIsVisible();
        }

        return !$attribute->getUsedInProductListing();
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

        $isSingleProduct = isset($processorOptions['isSingleProduct']) ? $processorOptions['isSingleProduct'] : false;
        $isCompare = isset($processorOptions['isCompare']) ? $processorOptions['isCompare'] : false;
        $isCartProduct = isset($processorOptions['isCartProduct']) ? $processorOptions['isCartProduct'] : false;

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

        $attributesBySetId = [];
        $attributeDataBySetId = [];

        if (empty($this->configurableAttributeIds)) {
            $this->getConfigurableAttributeIds();
        }

        foreach ($products as $product) {
            if (!array_key_exists($product->getAttributeSetId(), $attributesBySetId)) {
                $attributesBySetId[$product->getAttributeSetId()] = $product->getAttributes();
            }
        }

        foreach ($attributesBySetId as $attributeSetId => $attributesArr) {
            /**
             * @var Attribute $attribute
             */
            foreach ($attributesArr as $attributeCode => $attribute) {
                if ($this->isAttributeSkipped($attribute, $isSingleProduct, $isCompare, $isCartProduct)) {
                    continue;
                }

                $attributeDataBySetId[$attributeSetId][$attributeCode] = [
                    'attribute_code' => $attribute->getAttributeCode(),
                    'attribute_type' => $attribute->getFrontendInput(),
                    'attribute_label' => $attribute->getStoreLabel(),
                    'attribute_id' => $attribute->getAttributeId(),
                    'attribute_options' => [],
                    'used_in_product_listing' => $attribute->getUsedInProductListing()
                ];

                // Collect valid attributes
                if (!isset($attributes[$attributeCode])) {
                    $attributes[$attributeCode] = $attribute;
                }
            }
        }

        $this->appendWithGroup(
            $attributes,
            array_keys($attributesBySetId),
            $attributeDataBySetId
        );

        foreach ($products as $product) {
            $productId = $product->getId();
            $productIds[] = $productId;

            // Create storage for future attributes
            $productAttributes[$productId] = [];

            // some products may have nonexistent attribute sets with poor data integrity, skip load for these
            if (!isset($attributeDataBySetId[$product->getAttributeSetId()])) {
                continue;
            }

            foreach ($attributeDataBySetId[$product->getAttributeSetId()] as $attributeCode => $attributeData) {
                $productAttributes[$productId][$attributeCode] = $attributeData;
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
