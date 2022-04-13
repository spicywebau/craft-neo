<?php

namespace benf\neo\gql\arguments\elements;

use craft\gql\base\ElementArguments;
use craft\gql\types\QueryArgument;
use GraphQL\Type\Definition\Type;

/**
 * Class Block
 */
class Block extends ElementArguments
{
    /**
     * @inheritdoc
     */
    public static function getArguments(): array
    {
        return array_merge(parent::getArguments(), [
            'fieldId' => [
                'name' => 'fieldId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => 'Narrows the query results based on the field the Neo blocks belong to, per the fields’ IDs.',
            ],
            'primaryOwnerId' => [
                'name' => 'primaryOwnerId',
                'type' => Type::listOf(QueryArgument::getType()),
                'description' => ' Narrows the query results based on the primary owner element of the Neo blocks, per the owners’ IDs.',
            ],
            'typeId' => Type::listOf(QueryArgument::getType()),
            'type' => [
                'name' => 'type',
                'type' => Type::listOf(Type::string()),
                'description' => 'Narrows the query results based on the Neo blocks’ block type handles.',
            ],
            'level' => [
                'name' => 'level',
                'type' => QueryArgument::getType(),
                'description' => 'The block’s level within its field',
            ],
        ]);
    }
}
