<?php
/**
 * @category    ScandiPWA
 * @package     ScandiPWA_Performance
 * @author      Alfreds Genkins <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */

namespace ScandiPWA\Performance\Model\Resolver;

use GraphQL\Language\AST\FieldNode;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

trait ResolveInfoFieldsTrait
{
    /**
     * Take the main info about common field
     *
     * @param FieldNode $node
     * @return array
     */
    protected function getFieldContent($node)
    {
        $fields = [];

        foreach ($node->selectionSet->selections as $selection) {
            if (!isset($selection->name)) {
                continue;
            };

            $fields[] = $selection->name->value;
        }

        return $fields;
    }

    /**
     * Return field names for all requested product fields.
     *
     * @param ResolveInfo|FieldNode $graphqlResolveInfo
     * @param string $graphqlResolvePath
     * @return array
     */
    protected function getFieldsFromProductInfo(
        $graphqlResolveInfo,
        string $graphqlResolvePath
    ) {
        /** @var FieldNode|ResolveInfo $nodes */
        $nodes = isset($graphqlResolveInfo->selectionSet) ?
        $graphqlResolveInfo->selectionSet->selections :
        $graphqlResolveInfo->fieldNodes;

        $pathPieces = explode('/', $graphqlResolvePath);

        foreach ($nodes as $node) {
            // Skip all non valid values
            if (!isset($node->name) || $node->name->value !== $pathPieces[0]) {
                continue;
            }

            // Check for only one path part
            if (count($pathPieces) === 1) {
                return $this->getFieldContent($node);
            }

            // Loop through second level
            foreach ($node->selectionSet->selections as $selection) {
                if (!isset($selection->name) || $selection->name->value !== $pathPieces[1]) {
                    continue;
                }

                return $this->getFieldContent($selection);
            }
        }

        return [];
    }
}
