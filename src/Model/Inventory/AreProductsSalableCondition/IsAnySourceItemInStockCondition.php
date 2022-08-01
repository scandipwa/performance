<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory\AreProductsSalableCondition;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Inventory\Model\ResourceModel\SourceItem\Collection;
use Magento\Inventory\Model\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForSkuInterface;
use Magento\Inventory\Model\ResourceModel\SourceItem\CollectionFactory;
use ScandiPWA\Performance\Api\Inventory\AreProductsSalableInterface;

class IsAnySourceItemInStockCondition implements AreProductsSalableInterface
{
    /**
     * @var SourceItemRepositoryInterface
     */
    protected SourceItemRepositoryInterface $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface
     */
    protected GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var IsSourceItemManagementAllowedForSkuInterface
     */
    protected IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku;

    /**
     * @var ManageStockCondition
     */
    protected ManageStockCondition $manageStockCondition;

    /**
     * @var CollectionProcessorInterface
     */
    protected CollectionProcessorInterface $collectionProcessor;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $sourceItemCollectionFactory;

    /**
     * @param SourceItemRepositoryInterface $sourceItemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority
     * @param IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku
     * @param ManageStockCondition $manageStockCondition
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionFactory $sourceItemCollectionFactory
     */
    public function __construct(
        SourceItemRepositoryInterface $sourceItemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStockOrderedByPriority,
        IsSourceItemManagementAllowedForSkuInterface $isSourceItemManagementAllowedForSku,
        ManageStockCondition $manageStockCondition,
        CollectionProcessorInterface $collectionProcessor,
        CollectionFactory $sourceItemCollectionFactory
    ) {
        $this->sourceItemRepository = $sourceItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getSourcesAssignedToStockOrderedByPriority = $getSourcesAssignedToStockOrderedByPriority;
        $this->isSourceItemManagementAllowedForSku = $isSourceItemManagementAllowedForSku;
        $this->manageStockCondition = $manageStockCondition;
        $this->collectionProcessor = $collectionProcessor;
        $this->sourceItemCollectionFactory = $sourceItemCollectionFactory;
    }

    /**
     * @param array $skuArray
     * @param int $stockId
     * @return array
     * @throws InputException
     * @throws LocalizedException
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $result = $this->manageStockCondition->execute($skuArray, $stockId);

        $skusToCheck = [];

        foreach ($result as $sku => $value) {
            // if value is true, that is a final result; otherwise, proceed with the next checks
            if (!$value) {
                $skusToCheck[$sku] = null;
            }
        }

        foreach (array_keys($skusToCheck) as $sku) {
            if (!$this->isSourceItemManagementAllowedForSku->execute((string)$sku)) {
                // if source item management is not allowed, that is a final result for that sku
                $result[$sku] = true;

                unset($skusToCheck[$sku]);
            }
        }

        $sourceCodes = $this->getSourceCodesAssignedToStock($stockId);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, array_keys($skusToCheck), 'in')
            ->addFilter(SourceItemInterface::SOURCE_CODE, $sourceCodes, 'in')
            ->addFilter(SourceItemInterface::STATUS, SourceItemInterface::STATUS_IN_STOCK)
            ->create();

        /** @var Collection $collection */
        $collection = $this->sourceItemCollectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var SourceItem $item */
        foreach ($collection as $item) {
            $result[$item->getSku()] = true;
        }

        return $result;
    }

    /**
     * Provides source codes for certain stock
     *
     * @param int $stockId
     *
     * @return array
     * @throws InputException
     * @throws LocalizedException
     */
    protected function getSourceCodesAssignedToStock(int $stockId): array
    {
        $sources = $this->getSourcesAssignedToStockOrderedByPriority->execute($stockId);
        $sourceCodes = [];

        foreach ($sources as $source) {
            if ($source->isEnabled()) {
                $sourceCodes[] = $source->getSourceCode();
            }
        }

        return $sourceCodes;
    }
}
