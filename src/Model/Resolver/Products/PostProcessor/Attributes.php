<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\PostProcessor;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Swatches\Helper\Data;
use ScandiPWA\Performance\Api\ProductPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\Products\PostProcessorTrait;

/**
 * Class Attributes
 * @package ScandiPWA\Performance\Model\Resolver\Products\PostProcessor
 */
class Attributes implements ProductPostProcessorInterface
{
    use PostProcessorTrait;

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
     * Attributes constructor.
     * @param Data $swatchHelper
     * @param CollectionFactory $productCollection
     */
    public function __construct(
        Data $swatchHelper,
        CollectionFactory $productCollection
    ) {
        $this->swatchHelper = $swatchHelper;
        $this->productCollection = $productCollection;
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

        return array_map(function ($attribute) {
            return $attribute->name->value;
        }, $attributes);
    }

    /**
     * Append product attribute data with value, if value not found, strip the attribute from response
     * @param $attributes ProductAttributeInterface[]
     * @param $productIds array
     * @param $productAttributes
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
                    unset($productAttributes[$productId][$attributeCode]);
                    continue;
                }

                // Append value to existing attribute data
                $productAttributes[$productId][$attributeCode]['attribute_value'] = $attributeValue;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        ResolveInfo $graphqlResolveInfo,
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

            foreach ($product->getAttributes() as $attributeCode => $attribute) {
                if (!$attribute->getIsVisibleOnFront()) {
                    continue;
                }

                $productAttributes[$productId][$attributeCode] = [
                    'attribute_code' => $attribute->getAttributeCode(),
                    'attribute_type' => $attribute->getFrontendInput(),
                    'attribute_label' => $attribute->getFrontendLabel(),
                    'attribute_id' => $attribute->getAttributeId()
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

        return function (&$productData) use ($productAttributes) {
            $productId = $productData['id'];

            if (!isset($productAttributes[$productId])) {
                return;
            }

            $productData[self::ATTRIBUTES] = $productAttributes[$productId];
        };
    }
}
