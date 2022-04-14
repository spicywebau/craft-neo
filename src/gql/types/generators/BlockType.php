<?php

namespace benf\neo\gql\types\generators;

use benf\neo\elements\Block as BlockElement;
use benf\neo\gql\interfaces\elements\Block as BlockInterface;
use benf\neo\gql\types\elements\Block;
use benf\neo\Plugin as Neo;
use Craft;
use craft\gql\base\Generator;
use craft\gql\base\GeneratorInterface;
use craft\gql\base\ObjectType;
use craft\gql\base\SingleGeneratorInterface;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

/**
 * Class BlockType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class BlockType extends Generator implements GeneratorInterface, SingleGeneratorInterface
{
    /**
     * @inheritdoc
     */
    public static function generateTypes(mixed $context = null): array
    {
        if ($context) {
            $blockTypes = $context->getBlockTypes();
        } else {
            $blockTypes = Neo::$plugin->blockTypes->getAllBlockTypes();
        }

        $gqlTypes = [];

        foreach ($blockTypes as $blockType) {
            $type = static::generateType($blockType);
            $gqlTypes[$type->name] = $type;
        }

        return $gqlTypes;
    }

    /**
     * @inheritdoc
     */
    public static function generateType(mixed $context): ObjectType
    {
        $typeName = BlockElement::gqlTypeNameByContext($context);

        if (!($entity = GqlEntityRegistry::getEntity($typeName))) {
            $contentFieldGqlTypes = self::getContentFields($context);
            $blockTypeFields = array_merge(BlockInterface::getFieldDefinitions(), $contentFieldGqlTypes);

            $entity = GqlEntityRegistry::getEntity($typeName);

            if (!$entity) {
                $entity = new Block([
                    'name' => $typeName,
                    'fields' => function() use ($blockTypeFields, $typeName) {
                        return Craft::$app->getGql()->prepareFieldDefinitions($blockTypeFields, $typeName);
                    },
                ]);

                $entity = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, $entity);
            }
        }

        return $entity;
    }

    /**
     * @inheritdoc
     */
    protected static function getContentFields(mixed $context): array
    {
        $contentFieldGqlTypes = parent::getContentFields($context);

        if (!empty($context->childBlocks)) {
            $contentFieldGqlTypes['children'] = [
                'name' => 'children',
                'type' => Type::listOf(BlockInterface::getType()),
                'description' => 'The child block types for this Neo block',
            ];
        }

        return $contentFieldGqlTypes;
    }
}
