<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use ScandiPWA\Performance\Api\ProductsCollectionPostProcessorInterface;

class CollectionPostProcessor
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
     * @param Collection $collection
     * @param array $attributeNames
     * @return Collection
     */
    public function process(
        Collection $collection,
        array $attributeNames
    ): Collection {
        foreach ($this->processors as $processor) {
            /** @var ProductsCollectionPostProcessorInterface $processor */
            $processor->process(
                $collection,
                $attributeNames
            );
        }

        return $collection;
    }
}
