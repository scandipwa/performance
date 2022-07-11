<?php
/**
 * @category  ScandiPWA_Performance
 * @author    Aleksandrs Mokans <info@scandiweb.com>
 * @copyright Copyright (c) 2022 Scandiweb, Inc (https://scandiweb.com)
 * @license   http://opensource.org/licenses/OSL-3.0 The Open Software License 3.0 (OSL-3.0)
 */
declare(strict_types=1);

namespace ScandiPWA\Performance\Model\Inventory;

use Magento\Framework\Exception\LocalizedException;
use ScandiPWA\Performance\Api\Inventory\AreProductsSalableInterface;

class AreProductsSalableConditionChain implements AreProductsSalableInterface
{
    /**
     * @var AreProductsSalableInterface[]
     */
    protected array $unrequiredConditions;

    /**
     * @var AreProductsSalableInterface[]
     */
    protected array $requiredConditions;

    /**
     * @param array $conditions
     * @throws LocalizedException
     */
    public function __construct(
        array $conditions
    ) {
        $this->setConditions($conditions);
    }

    /**
     * @param array $conditions
     * @throws LocalizedException
     */
    protected function setConditions(array $conditions)
    {
        $this->validateConditions($conditions);

        $unrequiredConditions = array_filter(
            $conditions,
            function ($item) {
                return !isset($item['required']);
            }
        );

        $this->unrequiredConditions = array_column($this->sortConditions($unrequiredConditions), 'object');

        $requiredConditions = array_filter(
            $conditions,
            function ($item) {
                return isset($item['required']) && $item['required'];
            }
        );

        $this->requiredConditions = array_column($requiredConditions, 'object');
    }

    /**
     * @param array $conditions
     * @throws LocalizedException
     */
    protected function validateConditions(array $conditions)
    {
        foreach ($conditions as $condition) {
            if (empty($condition['object'])) {
                throw new LocalizedException(__('Parameter "object" must be present.'));
            }

            if (empty($condition['required']) && empty($condition['sort_order'])) {
                throw new LocalizedException(__('Parameter "sort_order" must be present for unrequired conditions.'));
            }

            if (!$condition['object'] instanceof AreProductsSalableInterface) {
                throw new LocalizedException(
                    __('Condition has to implement AreProductsSalableInterface.')
                );
            }
        }
    }

    /**
     * @param array $conditions
     * @return array
     */
    protected function sortConditions(array $conditions): array
    {
        usort($conditions, function (array $conditionLeft, array $conditionRight) {
            if ($conditionLeft['sort_order'] == $conditionRight['sort_order']) {
                return 0;
            }

            return ($conditionLeft['sort_order'] < $conditionRight['sort_order']) ? -1 : 1;
        });

        return $conditions;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skuArray, int $stockId): array
    {
        $finalResults = [];

        // default value - false for all skus
        foreach ($skuArray as $sku) {
            $finalResults[$sku] = false;
        }

        // if one of the required check fails, mark this result and skip unrequired conditions
        $skusToCheck = array_flip($skuArray);

        foreach ($this->requiredConditions as $requiredCondition) {
            $results = $requiredCondition->execute(array_keys($skusToCheck), $stockId);

            foreach ($results as $sku => $result) {
                if (!$result) {
                    $finalResults[$sku] = $result;
                    unset($skusToCheck[$sku]);
                }
            }
        }

        // if least one of these conditions returns True, final result for that sku can change
        // (stock not managed, or backorders enabled, or available despite reservations)
        foreach ($this->unrequiredConditions as $unrequiredCondition) {
            $results = $unrequiredCondition->execute(array_keys($skusToCheck), $stockId);

            foreach ($results as $sku => $result) {
                if ($result) {
                    $finalResults[$sku] = $result;
                    unset($skusToCheck[$sku]);
                }
            }
        }

        return $finalResults;
    }
}
