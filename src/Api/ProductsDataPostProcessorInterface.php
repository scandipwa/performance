<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\Performance\Api;

use GraphQL\Language\AST\FieldNode;
use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

interface ProductsDataPostProcessorInterface
{
    /**
     * @param Product[] $products
     * @param string $graphqlResolvePath
     * @param ResolveInfo|FieldNode  $graphqlResolveInfo
     * @param array $processorOptions
     * @return callable
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        $graphqlResolveInfo,
        ?array $processorOptions = []
    ): callable;
}
