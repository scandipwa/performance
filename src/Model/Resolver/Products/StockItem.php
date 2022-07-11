<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\GraphQl\Query\Resolver\BatchServiceContractResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ResolveRequestInterface;
use ScandiPWA\Performance\Model\Resolver\Products\StockItem\GetStockItem;
use ScandiPWA\Performance\Model\Resolver\Products\StockItem\ProductCriteria;

class StockItem implements BatchServiceContractResolverInterface
{
    /**
     * @var GetStockItem
     */
    protected GetStockItem $getStockItem;

    /**
     * @param GetStockItem $getStockItem
     */
    public function __construct(GetStockItem $getStockItem)
    {
        $this->getStockItem = $getStockItem;
    }

    /**
     * @inheritDoc
     */
    public function getServiceContract(): array
    {
        return [GetStockItem::class, 'execute'];
    }

    /**
     * @inheritDoc
     */
    public function convertToServiceArgument(ResolveRequestInterface $request): ProductCriteria
    {
        // add criteria to a "shared" queue to load data
        // this is necessary, because otherwise BatchContractResolverWrapper
        // is going to make an individual GetStockItem  batch per product type, which is unnecessary
        $this->getStockItem->addSkuToQueue($request->getValue()['model']->getSku());

        // criteria for this particular request
        return new ProductCriteria($request->getValue()['model']->getSku());
    }

    /**
     * @param StockItemInterface $result
     * @param ResolveRequestInterface $request
     * @return array
     */
    public function convertFromServiceResult(
        $result,
        ResolveRequestInterface $request
    ): array {
        return [
            'min_sale_qty' => $result->getMinSaleQty(),
            'max_sale_qty' => $result->getMaxSaleQty(),
            'qty_increments' => $result->getQtyIncrements() === false ? 1 : $result->getQtyIncrements()
        ];
    }
}
