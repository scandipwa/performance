<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor;

use Exception;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Catalog\Model\Product\ImageFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use ScandiPWA\Performance\Api\ProductsDataPostProcessorInterface;
use ScandiPWA\Performance\Model\Resolver\ResolveInfoFieldsTrait;

/**
 * Class Images
 * @package ScandiPWA\Performance\Model\Resolver\Products\DataPostProcessor
 */
class Images implements ProductsDataPostProcessorInterface
{
    use ResolveInfoFieldsTrait;

    const IMAGE_FIELDS = ['thumbnail', 'small_image', 'image'];

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var ImageFactory
     */
    protected $productImageFactory;

    /**
     * @var Emulation
     */
    protected $emulation;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Images constructor.
     *
     * @param ImageFactory $productImageFactory
     * @param Image $imageHelper
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ImageFactory $productImageFactory,
        Image $imageHelper,
        Emulation $emulation,
        StoreManagerInterface $storeManager
    ) {

        $this->imageHelper = $imageHelper;
        $this->productImageFactory = $productImageFactory;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
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
        $graphqlResolveInfo,
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

        $storeId = $this->storeManager->getStore()->getId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

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
                    'url' => $this->getImageUrl($imageType, $imagePath, $product),
                    'label' => $imageLabel ?? $product->getName()
                ];
            }
        }

        $this->emulation->stopEnvironmentEmulation();

        return function (&$productData) use ($productImages) {
            if (!isset($productData['entity_id'])) {
                return;
            }

            $productId = $productData['entity_id'];

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
     * @param Product $product
     * @return string
     * @throws Exception
     */
    protected function getImageUrl(
        string $imageType,
        ?string $imagePath,
        $product
    ): string {
        if (!isset($imagePath)) {
            return $this->imageHelper->getDefaultPlaceholderUrl($imageType);
        }

        $imageId = sprintf('scandipwa_%s', $imageType);

        $image = $this->imageHelper
            ->init(
                $product,
                $imageId,
                ['type' => $imageType]
            )
            ->constrainOnly(true)
            ->keepAspectRatio(true)
            ->keepTransparency(true)
            ->keepFrame(false);

        return $image->getUrl();
    }
}
