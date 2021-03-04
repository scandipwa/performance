<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use ScandiPWA\Performance\Api\ProductsCollectionPostProcessorInterface;

/**
 * Class Options
 * @package ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor
 */
class Options implements ProductsCollectionPostProcessorInterface
{
    public const OPTIONS_FIELD = 'options';

    /**
     * @inheritDoc
     */
    public function process(
        Collection $collection,
        array $attributeNames
    ): Collection {
        if (in_array(self::OPTIONS_FIELD, $attributeNames, true)) {
            $collection->addOptionsToResult();
        }

        return $collection;
    }
}
