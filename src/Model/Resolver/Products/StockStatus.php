<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Resolver\Products;

use Magento\Framework\GraphQl\Query\Resolver\BatchServiceContractResolverInterface;
use Magento\Framework\GraphQl\Query\Resolver\ResolveRequestInterface;
use ScandiPWA\Performance\Model\Resolver\Products\StockStatus\AreProductsSalable;
use ScandiPWA\Performance\Model\Resolver\Products\StockStatus\ProductCriteria;

class StockStatus implements BatchServiceContractResolverInterface
{
    /**
     * @var AreProductsSalable
     */
    protected AreProductsSalable $areProductsSalable;

    /**
     * @param AreProductsSalable $areProductsSalable
     */
    public function __construct(AreProductsSalable $areProductsSalable)
    {
        $this->areProductsSalable = $areProductsSalable;
    }

    /**
     * @inheritDoc
     */
    public function getServiceContract(): array
    {
        return [AreProductsSalable::class, 'execute'];
    }

    /**
     * @inheritDoc
     */
    public function convertToServiceArgument(ResolveRequestInterface $request): ProductCriteria
    {
        // add criteria to a "shared" queue to load data
        // this is necessary, because otherwise BatchContractResolverWrapper
        // is going to make an individual AreProductsSalable batch per product type, which is unnecessary
        $this->areProductsSalable->addSkuToQueue($request->getValue()['model']->getSku());

        // criteria for this particular request
        return new ProductCriteria($request->getValue()['model']->getSku());
    }

    /**
     * @inheritDoc
     */
    public function convertFromServiceResult(
        $result,
        ResolveRequestInterface $request
    ): string {
        return $result->isSalable() ? 'IN_STOCK' : 'OUT_OF_STOCK';
    }
}
