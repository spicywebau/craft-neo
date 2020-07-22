<?php

namespace benf\neo\converters;

use benf\neo\helpers\Memoize;
use Craft;
use craft\base\Model;
use NerdsAndCompany\Schematic\Converters\Base\Field as Base;

if (!class_exists(Base::class)) {
    return;
}

/**
 * Neo Field Converter for Schematic.
 *
 * {@inheritdoc}
 * @since 2.1.0
 * @deprecated in 2.8.0
 */
class Field extends Base
{
    /**
     * {@inheritdoc}
     */
    public function getRecordDefinition(Model $record): array
    {
        $definition = parent::getRecordDefinition($record);
        $definition['blockTypeGroups'] = Craft::$app->controller->module->modelMapper->export($record->getGroups());
        $definition['blockTypes'] = Craft::$app->controller->module->modelMapper->export($record->getBlockTypes());

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    public function saveRecord(Model $record, array $definition): bool
    {
        if (parent::saveRecord($record, $definition)) {
            if (array_key_exists('blockTypeGroups', $definition)) {
                $this->resetNeoCache();
                $this->resetNeoFieldBlockTypeGroupsCache($record);

                Craft::$app->controller->module->modelMapper->import(
                    $definition['blockTypeGroups'],
                    $record->getGroups(),
                    ['fieldId' => $record->id]
                );
            }

            if (array_key_exists('blockTypes', $definition)) {
                $this->resetNeoCache();
                $this->resetNeoFieldBlockTypesCache($record);

                Craft::$app->controller->module->modelMapper->import(
                    $definition['blockTypes'],
                    $record->getBlockTypes(),
                    ['fieldId' => $record->id]
                );
            }

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

    /**
     * Reset neo field block types cache using reflection.
     *
     * @param Model $record
     */
    private function resetNeoFieldBlockTypesCache(Model $record)
    {
        $obj = $record;
        $refObject = new \ReflectionObject($obj);
        if ($refObject->hasProperty('_blockTypes')) {
            $refProperty1 = $refObject->getProperty('_blockTypes');
            $refProperty1->setAccessible(true);
            $refProperty1->setValue($obj, null);
        }
    }

    /**
     * Reset neo field block type groups cache using reflection.
     *
     * @param Model $record
     */
    private function resetNeoFieldBlockTypeGroupsCache(Model $record)
    {
        $obj = $record;
        $refObject = new \ReflectionObject($obj);
        if ($refObject->hasProperty('_blockTypeGroups')) {
            $refProperty1 = $refObject->getProperty('_blockTypeGroups');
            $refProperty1->setAccessible(true);
            $refProperty1->setValue($obj, null);
        }
    }
}
