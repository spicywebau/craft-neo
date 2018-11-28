<?php
namespace benf\neo\converters;

use Craft;
use craft\base\Model;
use NerdsAndCompany\Schematic\Converters\Base\Field as Base;
use benf\neo\helpers\Memoize;

/**
 * Neo Field Converter for Schematic.
 *
 * {@inheritdoc}
 */
class Field extends Base
{
    /**
     * {@inheritdoc}
     */
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);

        $neo = Craft::$app->getPlugins()->getPlugin('neo');
        $this->resetNeoCache();
        $blockTypeGroups = $neo->blockTypes->getGroupsByFieldId($record->id);
        $blockTypes = $neo->blockTypes->getByFieldId($record->id);

        $definition['blockTypeGroups'] = Craft::$app->controller->module->modelMapper->export($blockTypeGroups);
        $definition['blockTypes'] = Craft::$app->controller->module->modelMapper->export($blockTypes);

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function saveRecord(Model $record, array $definition): bool
    {
        if (parent::saveRecord($record, $definition)) {
            Craft::$app->controller->module->modelMapper->import(
                $definition['blockTypeGroups'],
                $record->getGroups(),
                ['fieldId' => $record->id]
            );

            Craft::$app->controller->module->modelMapper->import(
                $definition['blockTypes'],
                $record->getBlockTypes(),
                ['fieldId' => $record->id]
            );

            return true;
        }

        return false;
    }

    /**
     * Reset neo caches.
     */
    private function resetNeoCache()
    {
        Memoize::$blockTypeGroupsById = null;
        Memoize::$blockTypeGroupsByFieldId = null;
        Memoize::$blockTypesById = null;
        Memoize::$blockTypesByFieldId = null;
    }
}
