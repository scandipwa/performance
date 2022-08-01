<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use ScandiPWA\Performance\Api\Inventory\GetReservationsQuantitiesInterface;

class GetReservationsQuantities implements GetReservationsQuantitiesInterface
{
    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resource;
    /**
     * Cached results
     * @var array
     */
    protected array $reservationQuantitiesByStockAndSku = [];

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    /**
     * Get item reservations by sku array and stock
     * Cache wrapper
     *
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $resultsBySku = [];
        $loadSkus = [];

        foreach ($skuArray as $sku) {
            if (isset($this->reservationQuantitiesByStockAndSku[$stockId][$sku])) {
                $resultsBySku[$sku] = $this->reservationQuantitiesByStockAndSku[$stockId][$sku];
            } else {
                $loadSkus[] = $sku;
                $resultsBySku[$sku] = null;
            }
        }

        if (count($loadSkus)) {
            $reservationsDatas = $this->getReservationsData($loadSkus, $stockId);

            foreach ($reservationsDatas as $sku => $reservationsData) {
                $this->reservationQuantitiesByStockAndSku[$stockId][$sku] = $reservationsData;
                $resultsBySku[$sku] = $reservationsData;
            }
        }

        return $resultsBySku;
    }

    /**
     * Get item reservations by skus and stock
     *
     * @param array $skuArray
     * @param int $stockId
     * @return array
     */
    public function getReservationsData(array $skuArray, int $stockId): array
    {
        $result = [];

        foreach ($skuArray as $sku) {
            $result[$sku] = (float)0;
        }

        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');

        $select = $connection->select()
            ->from($reservationTable,
                [
                    ReservationInterface::QUANTITY => 'SUM(' . ReservationInterface::QUANTITY . ')',
                    ReservationInterface::SKU
                ]
            )
            ->where(ReservationInterface::SKU . ' IN (?)', $skuArray)
            ->where(ReservationInterface::STOCK_ID . ' = ?', $stockId)
            ->group(ReservationInterface::SKU);

        foreach ($connection->fetchAll($select) as $row) {
            $result[$row[ReservationInterface::SKU]] = (float)$row[ReservationInterface::QUANTITY];
        }

        return $result;
    }
}
