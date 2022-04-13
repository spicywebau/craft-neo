<?php

namespace benf\neo\gql\types\elements;

use benf\neo\gql\interfaces\elements\Block as NeoBlockInterface;
use craft\gql\base\ObjectType;
use craft\gql\types\elements\Element;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class MatrixBlock
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class Block extends Element
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config)
    {
        $config['interfaces'] = [
            NeoBlockInterface::getType(),
        ];

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $fieldName = $resolveInfo->fieldName;

        if ($fieldName === 'typeHandle') {
            return $source->getType()->handle;
        }

        if ($fieldName === 'children') {
            $childrenLevel = (int)$source->level + 1;

            // The blocks at `$source->$fieldName` cannot be trusted, it will most likely be out of order and cached.
            // We should retrieve the children blocks by query instead, so it'll always be in the correct order.
            $descendants = $source->getDescendants()->all();
            $children = array_filter($descendants, function($block) use ($childrenLevel) {
                return (int)$block->level === $childrenLevel;
            });

            return $children;
        }

        return parent::resolve($source, $arguments, $context, $resolveInfo);
    }
}
