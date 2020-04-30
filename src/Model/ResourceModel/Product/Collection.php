<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\Performance\Model\ResourceModel\Product;

use Magento\Catalog\Model\ResourceModel\Product\Collection as CoreCollection;
use Magento\Eav\Model\Entity\Attribute\AttributeInterface;
use Magento\Framework\App\Config\Element;
use Magento\Framework\Exception\LocalizedException;

class Collection extends CoreCollection
{
    /**
     * Add attribute to entities in collection. If $attribute=='*' select all attributes.
     *
     * @param array|string|integer|Element $attribute
     * @param bool|string $joinType
     * @return CoreCollection
     * @throws LocalizedException
     */
    public function addAttributeToSelect($attribute, $joinType = false)
    {
        if ($this->isEnabledFlat()) {
            if (!is_array($attribute)) {
                $attribute = [$attribute];
            }

            foreach ($attribute as $attributeCode) {
                if (is_object($attributeCode)) {
                    $attributeCode = $attributeCode->getAttributeCode();
                }

                if ($attributeCode == '*') {
                    foreach ($this->getEntity()->getAllTableColumns() as $column) {
                        $this->getSelect()->columns('e.' . $column);
                        $this->_selectAttributes[$column] = $column;
                        $this->_staticFields[$column] = $column;
                    }
                } else {
                    $columns = $this->getEntity()->getAttributeForSelect($attributeCode);
                    if ($columns) {
                        foreach ($columns as $alias => $column) {
                            $this->getSelect()->columns([$alias => 'e.' . $column]);
                            $this->_selectAttributes[$column] = $column;
                            $this->_staticFields[$column] = $column;
                        }
                    }
                }
            }

            return $this;
        }

        return $this->addAttributeOrAttributeEntityToSelect($attribute, $joinType);
    }

    protected function addAttributeOrAttributeEntityToSelect($attribute, $joinType = false)
    {
        if (is_array($attribute)) {
            foreach ($attribute as $a) {
                $this->addAttributeToSelect($a, $joinType);
            }
            return $this;
        }
        if ($joinType !== false && !$this->getEntity()->getAttribute($attribute)->isStatic()) {
            $this->_addAttributeJoin($attribute, $joinType);
        } elseif ('*' === $attribute) {
            $entity = clone $this->getEntity();
            $attributes = $entity->loadAllAttributes()->getAttributesByCode();
            foreach ($attributes as $attrCode => $attr) {
                $this->_selectAttributes[$attrCode] = $attr->getId();
            }
        } else {
            if ($attribute instanceof AttributeInterface) {
                $this->_selectAttributes[$attribute->getAttributeCode()] = $attribute->getId();
                return $this;
            }

            if (isset($this->_joinAttributes[$attribute])) {
                $attrInstance = $this->_joinAttributes[$attribute]['attribute'];
            } else {
                $attrInstance = $this->_eavConfig->getAttribute($this->getEntity()->getType(), $attribute);
            }
            if (empty($attrInstance)) {
                throw new LocalizedException(
                    __(
                        'The "%1" attribute requested is invalid. Verify the attribute and try again.',
                        (string)$attribute
                    )
                );
            }
            $this->_selectAttributes[$attrInstance->getAttributeCode()] = $attrInstance->getId();
        }

        return $this;
    }
}