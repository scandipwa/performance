<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\PostProcessor;

use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\View\ConfigInterface;
use Magento\Catalog\Model\Product\ImageFactory;
use ScandiPWA\Performance\Api\ProductPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\Products\PostProcessorTrait;

/**
 * Class Images
 * @package ScandiPWA\Performance\Model\Resolver\Products\PostProcessor
 */
class Images implements ProductPostProcessorInterface
{
    use PostProcessorTrait;

    const IMAGE_FIELDS = ['thumbnail', 'small_image', 'image'];

    /**
     * @var ConfigInterface
     */
    protected $presentationConfig;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var ImageFactory
     */
    protected $productImageFactory;

    /**
     * Images constructor.
     *
     * @param ImageFactory $productImageFactory
     * @param ConfigInterface $presentationConfig
     * @param Image $imageHelper
     */
    public function __construct(
        ImageFactory $productImageFactory,
        ConfigInterface $presentationConfig,
        Image $imageHelper
    ) {

        $this->imageHelper = $imageHelper;
        $this->presentationConfig = $presentationConfig;
        $this->productImageFactory = $productImageFactory;
    }

    /**
     * @inheritDoc
     */
    protected function getFieldContent($node)
    {
        $images = [];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            };

            $name = $selection->name->value;

            if (in_array($name, self::IMAGE_FIELDS)) {
                $images[] = $name;
            }
        }

        return $images;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function process(
        array $products,
        string $graphqlResolvePath,
        ResolveInfo $graphqlResolveInfo,
        ?array $processorOptions = []
    ): callable {
        $productImages = [];

        $fields = $this->getFieldsFromProductInfo(
            $graphqlResolveInfo,
            $graphqlResolvePath
        );

        if (!count($fields)) {
            return function (&$productData) {
            };
        }

        /** @var Product $product */
        foreach ($products as $product) {
            $id = $product->getId();
            $productImages[$id] = [];

            foreach ($fields as $imageType) {
                $productImages[$id][$imageType] = [];
                $imagePath = $product->getData($imageType);
                $imageLabel = $product->getData(sprintf('%s_label', $imageType));

                $productImages[$id][$imageType] = [
                    'path' => $imagePath,
                    'url' => $this->getImageUrl($imageType, $imagePath),
                    'label' => $imageLabel ?? $product->getName()
                ];
            }
        }

        return function (&$productData) use ($productImages) {
            $productId = $productData['id'];

            if (!isset($productImages[$productId])) {
                return;
            }

            foreach ($productImages[$productId] as $imageType => $imageData) {
                $productData[$imageType] = $imageData;
            }
        };
    }

    /**
     * @param string $imageType
     * @param string|null $imagePath
     * @return string
     * @throws Exception
     */
    protected function getImageUrl(
        string $imageType,
        ?string $imagePath
    ): string {
        if (!isset($imagePath)) {
            return $this->imageHelper->getDefaultPlaceholderUrl($imageType);
        }

        $imageId = sprintf('catalog_product_media_%s', $imageType);

        $viewImageConfig = $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            ImageHelper::MEDIA_TYPE_CONFIG_NODE,
            $imageId
        );

        $image = $this->productImageFactory->create();

        $image
            ->setWidth((int) $viewImageConfig['width'])
            ->setHeight((int) $viewImageConfig['height'])
            ->setDestinationSubdir($imageType)
            ->setBaseFile($imagePath);

        return $image->getUrl();
    }
}
