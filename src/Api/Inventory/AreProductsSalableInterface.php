<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Api\Inventory;

/**
 * Service which detects whether products are salable for given stock (stock data + reservations).
 *
 * @api
 */
interface AreProductsSalableInterface
{
    /**
     * Get products salable status for given SKUs and given Stock.
     *
     * @param string[] $skuArray
     * @param int $stockId
     * @return array
     */
    public function execute(array $skuArray, int $stockId): array;
}
