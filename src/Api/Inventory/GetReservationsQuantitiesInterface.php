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
 * Responsible for retrieving Reservation Quantity (without stock data) for SKU array
 *
 * @api
 */
interface GetReservationsQuantitiesInterface
{
    /**
     * Given a product sku array and a stock id, return reservation quantity for each sku
     *
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function execute(array $skuArray, int $stockId): array;
}
