<?php
namespace benf\neo\converters\models;

use Craft;
use craft\base\Model;
use NerdsAndCompany\Schematic\Converters\Models\Base;

/**
 * Neo BlockType Converter for Schematic.
 *
 * {@inheritdoc}
 */
class BlockType extends Base
{
    /**
     * {@inheritdoc}
     */
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);

        unset($definition['attributes']['fieldId']);
        unset($definition['attributes']['hasFieldErrors']);

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function saveRecord(Model $record, array $definition): bool
    {
        $neo = Craft::$app->getPlugins()->getPlugin('neo');

        return $neo->blockTypes->save($record);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteRecord(Model $record): bool
    {
        $neo = Craft::$app->getPlugins()->getPlugin('neo');

        return $neo->blockTypes->delete($record);
    }
}
