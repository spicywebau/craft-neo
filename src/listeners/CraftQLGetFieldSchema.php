<?php

namespace benf\neo\listeners;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\models\BlockType;
use craft\elements\db\ElementQuery;
use markhuot\CraftQL\Events\GetFieldSchema;

class CraftQLGetFieldSchema {

    static function handle(GetFieldSchema $event) {
        $event->handled = true;

        /** @var Field $field */
        $field = $event->sender;
        $schema = $event->schema;

        /** @var BlockType[] $blockTypes */
        $blockTypes = $field->getBlockTypes();

        if (empty($blockTypes)) {
            $schema->addStringField($field)->resolve('The field `'.$field->handle.'` has no block types, which would violate the GraphQL spec, so we filled it with this placeholder field.');
            return;
        }

        $union = $schema->addUnionField($field)
            ->lists()
            ->arguments(function (\markhuot\CraftQL\Builders\Field $field) {
                $field->addIntArgument('level');
                $field->addIntArgument('id');
                $field->addIntArgument('limit');
                $field->addIntArgument('offset');
                $field->addStringArgument('search');
                $field->addStringArgument('status');
                $field->addStringArgument('type');
                $field->addIntArgument('typeId');
            })
            ->resolveType(function (Block $root, $args) use ($field) {
                $block = $root->getType();
                return ucfirst($field->handle).ucfirst($block->handle);
            })
            ->resolve(function ($root, $args, $context, $info) use ($field) {
                /** @var ElementQuery $criteria */
                $criteria = $root->{$field->handle};

                foreach ($args as $k => $v) {
                    $criteria->{$k} = $v;
                }

                return $criteria->all();
            });

        foreach ($blockTypes as $blockType) {
            $type = $union->addType(ucfirst($field->handle).ucfirst($blockType->handle), $blockType);
            $type->addField('ancestors')->type($union)->lists();
            $type->addField('children')->type($union)->lists();
            $type->addBooleanField('collapsed');
            $type->addDateField('dateCreated');
            $type->addDateField('dateUpdated');
            $type->addField('descendants')->type($union)->lists();
            $type->addBooleanField('enabled');
            $type->addField('field')
                ->type(\markhuot\CraftQL\Types\Field::class)
                ->resolve(function (Block $root, $args) {
                    return \Craft::$app->fields->getFieldById($root->fieldId);
                });
            $type->addIntField('fieldId');
            $type->addBooleanField('hasDescendants');
            $type->addIntField('id');
            $type->addIntField('level');
            $type->addField('next')->type($union);
            $type->addField('nextSibling')->type($union);
            $type->addField('owner');
            $type->addIntField('ownerId');
            $type->addField('parent')->type($union);
            $type->addField('prev')->type($union);
            $type->addField('prevSibling')->type($union);
            $type->addField('siblings')->type($union)->lists();
            $type->addField('type')->type($union)->lists();
            $type->addIntField('typeId');
            $type->addFieldsByLayoutId($blockType->fieldLayoutId);
        }
    }

}