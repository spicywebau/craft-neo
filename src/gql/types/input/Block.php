<?php

namespace benf\neo\gql\types\input;

use benf\neo\Field as NeoField;
use Craft;
use craft\base\Field;
use craft\gql\GqlEntityRegistry;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * @package benf\neo\gql\types\input
 * @since 2.9.0
 */
class Block extends InputObjectType
{
    public static function getType(NeoField $context)
    {
        $typeName = $context->handle . '_NeoInput';

        if ($inputType = GqlEntityRegistry::getEntity($typeName)) {
            return $inputType;
        }

        // Array of block types.
        $blockTypes = $context->getBlockTypes();
        $blockInputTypes = [];

        // For all the blocktypes
        foreach ($blockTypes as $blockType) {
            $fields = $blockType->getFields();
            $blockTypeFields = [
                'id' => [
                    'name' => 'id',
                    'type' => Type::id(),
                ],
                'level' => [
                    'name' => 'level',
                    'type' => Type::int(),
                ]
            ];

            // Get the field input types
            foreach ($fields as $field) {
                $blockTypeFields[$field->handle] = $field->getContentGqlMutationArgumentType();
            }

            $blockTypeGqlName = $context->handle . '_' . $blockType->handle . '_NeoBlockInput';
            $blockInputTypes[$blockType->handle] = [
                'name' => $blockType->handle,
                'type' => GqlEntityRegistry::createEntity($blockTypeGqlName, new InputObjectType([
                    'name' => $blockTypeGqlName,
                    'fields' => $blockTypeFields
                ]))
            ];
        }

        // All the different field block types now get wrapped in a container input.
        // If two different block types are passed, the selected block type to parse is undefined.
        $blockTypeContainerName = $context->handle . '_NeoBlockContainerInput';
        $blockContainerInputType = GqlEntityRegistry::createEntity($blockTypeContainerName, new InputObjectType([
            'name' => $blockTypeContainerName,
            'fields' => function() use ($blockInputTypes) {
                return $blockInputTypes;
            }
        ]));

        $inputType = GqlEntityRegistry::createEntity($typeName, new InputObjectType([
            'name' => $typeName,
            'fields' => function() use ($blockContainerInputType) {
                return [
                    'sortOrder' => [
                        'name' => 'sortOrder',
                        'type' => Type::listOf(QueryArgument::getType())
                    ],
                    'blocks' => [
                        'name' => 'blocks',
                        'type' => Type::listOf($blockContainerInputType)
                    ]
                ];
            },
            'normalizeValue' => [self::class, 'normalizeValue']
        ]));

        return $inputType;
    }

    public static function normalizeValue($value)
    {
        $preparedBlocks = [];
        $blockCounter = 1;

        if (!empty($value['blocks'])) {
            foreach ($value['blocks'] as $block) {
                if (!empty($block)) {
                    $type = array_key_first($block);
                    $block = reset($block);
                    $blockId = !empty($block['id']) ? $block['id'] : 'new:' . ($blockCounter++);
                    $blockLevel = null;
                    
                    if (!empty($block['level'])) {
                        // Set the block's new level
                        $blockLevel = $block['level'];
                    } else if (empty($block['id'])) {
                        // Default new blocks to level 1
                        $blockLevel = 1;
                    }

                    unset($block['id'], $block['level']);

                    $preparedBlocks[$blockId] = [
                        'type' => $type,
                        'level' => $blockLevel,
                        'modified' => true,
                        'fields' => $block
                    ];
                }
            }

            $value['blocks'] = $preparedBlocks;
        }

        return $value;
    }
}
