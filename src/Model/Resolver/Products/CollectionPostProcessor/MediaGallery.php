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
use Magento\Framework\Exception\LocalizedException;
use ScandiPWA\Performance\Api\ProductsCollectionPostProcessorInterface;

/**
 * Class MediaGallery
 * @package ScandiPWA\Performance\Model\Resolver\Products\CollectionPostProcessor
 */
class MediaGallery implements ProductsCollectionPostProcessorInterface
{
    public const MEDIA_FIELDS = ['media_gallery_entries', 'media_gallery'];

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function process(
        Collection $collection,
        array $attributeNames
    ): Collection {
        if (array_intersect(self::MEDIA_FIELDS, $attributeNames)) {
            $collection->addMediaGalleryData();
        }

        return $collection;
    }
}
