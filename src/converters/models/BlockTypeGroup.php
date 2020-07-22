<?php

namespace benf\neo\converters\models;

use Craft;
use craft\base\Model;
use NerdsAndCompany\Schematic\Converters\Models\Base;

if (!class_exists(Base::class)) {
    return;
}

/**
 * Neo BlockTypeGroup Converter for Schematic.
 *
 * {@inheritdoc}
 * @since 2.1.0
 * @deprecated in 2.8.0
 */
class BlockTypeGroup extends Base
{
    /**
     * {@inheritdoc}
     */
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);

        unset($definition['attributes']['fieldId']);

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function saveRecord(Model $record, array $definition): bool
    {
        $neo = Craft::$app->getPlugins()->getPlugin('neo');

        return $neo->blockTypes->saveGroup($record);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecord(Model $record): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecordIndex(Model $record): string
    {
        return $record->name;
    }
}
