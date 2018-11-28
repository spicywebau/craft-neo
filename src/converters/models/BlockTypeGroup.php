<?php
namespace benf\neo\converters\models;

use Craft;
use craft\base\Model;
use NerdsAndCompany\Schematic\Converters\Models\Base;

/**
 * Neo BlockTypeGroup Converter for Schematic.
 *
 * {@inheritdoc}
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
