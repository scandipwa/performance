<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Api\Inventory;

use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;

/**
 * Returns stock item configuration data by sku and stock id.
 *
 * @api
 */
interface GetStockItemsConfigurationsInterface
{
    /**
     * @param array $skuArray
     * @param int $stockId
     * @return StockItemConfigurationInterface[]
     */
    public function execute(
        array $skuArray,
        int $stockId
    ): array;
}
