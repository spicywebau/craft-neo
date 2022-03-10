<?php

namespace benf\neo\gql\resolvers\elements;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block as BlockElement;
use benf\neo\elements\db\BlockQuery;
use craft\gql\base\ElementResolver;
use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class Block
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class Block extends ElementResolver
{
    /**
     * @inheritdoc
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $query = self::prepareElementQuery($source, $arguments, $context, $resolveInfo);
        $blocks = $query instanceof BlockQuery ? $query->all() : $query;

        if ($query instanceof BlockQuery && $query->level == 0) {
            // If we have all blocks, memoize them to avoid database calls for child block queries.
            // This also allows child block queries after mutations to return results... not sure if
            // there's some caching going on causing it to otherwise return no child blocks, need to
            // look into it further.
            foreach ($blocks as $block) {
                $block->useMemoized($blocks);
            }
        }

        return GqlHelper::applyDirectives($source, $resolveInfo, $blocks);
    }

    /**
     * @inheritdoc
     */
    public static function prepareQuery(mixed $source, array $arguments, $fieldName = null): mixed
    {
        // If this is the beginning of a resolver chain, start fresh
        if ($source === null) {
            // get the first level
            $query = BlockElement::find()->level(1);

            // If not, get the prepared element query
        } else {
            $query = $source->$fieldName;
        }

        // If it's preloaded, it's preloaded.
        if (is_array($query)) {
            $query = array_unique($query);

            // Return level 1 blocks only, unless the `level` argument says otherwise
            $level = isset($arguments['level'])
                ? ($arguments['level'] !== 0 ? $arguments['level'] : null)
                : 1;
            $newBlocks = $level === null
                ? $query
                : array_filter($query, function($block) use ($level) { return (int)$block->level === $level; });

            return !empty($newBlocks) ? $newBlocks : $query;
        }

        // We require level 1 unless the arguments say otherwise
        $query->level(1);

        foreach ($arguments as $key => $value) {
            $query->$key($value);
        }

        return $query;
    }
}
