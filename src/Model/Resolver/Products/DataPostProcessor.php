<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products;

use GraphQL\Language\AST\FieldNode;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ScandiPWA\Performance\Api\ProductsDataPostProcessorInterface;
use Magento\Catalog\Model\Product;

class DataPostProcessor
{
    /**
     * Please use DI to change this property
     * @var array
     */
    private $processors;

    /**
     * ProductPostProcessor constructor.
     * @param array $processors
     */
    public function __construct(
        array $processors = []
    ) {
        $this->processors = $processors;
    }

    /**
     * @param Product[] $products
     * @param string $graphqlResolvePath
     * @param ResolveInfo|FieldNode $graphqlResolveInfo
     * @param array $processorOptions
     * @return array
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        $graphqlResolveInfo,
        array $processorOptions = []
    ): array {
        $processorsCallbacks = array_map(function ($processor) use (
            $products,
            $graphqlResolvePath,
            $graphqlResolveInfo,
            $processorOptions
        ) {
            /** @var ProductsDataPostProcessorInterface $processor */
            return $processor->process(
                $products,
                $graphqlResolvePath,
                $graphqlResolveInfo,
                $processorOptions
            );
        }, $this->processors);

        $productsData = [];

        foreach ($products as $product) {
            $productId = $product->getId();
            $productsData[$productId] = $product->getData() + ['model' => $product];

            // Give the processor power to process the product data
            foreach ($processorsCallbacks as $processorsCallback) {
                $processorsCallback($productsData[$productId]);
            }
        }

        return $productsData;
    }
}
