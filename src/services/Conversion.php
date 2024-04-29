<?php

namespace benf\neo\services;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\Plugin as Neo;
use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\fields\Matrix as MatrixField;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\Exception;

/**
 * Handles conversion of Neo fields to Matrix.
 *
 * @package benf\neo\services
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.2.0
 */
class Conversion extends Component
{
    /**
     * Converts a Neo field to a Matrix field.
     * WARNING: Calling this will replace the Neo field with a Matrix one, so use with caution. Performing this
     * conversion cannot be undone.
     *
     * @param Field $neoField
     * @param bool $deleteOldBlockTypesAndGroups
     * @return bool
     * @throws \Throwable
     */
    public function convertFieldToMatrix(Field $neoField, bool $deleteOldBlockTypesAndGroups = true): bool
    {
        $elementsService = Craft::$app->getElements();
        $fieldsService = Craft::$app->getFields();
        $globalFields = [];

        foreach ($fieldsService->getAllFields() as $field) {
            $globalFields[$field->id] = $field;
        }

        $dbService = Craft::$app->getDb();
        $transaction = $dbService->beginTransaction();

        try {
            // Save a mapping of block type UIDs to their Neo ID for use later on when migrating content.
            $neoBlockTypeIds = [];
            $neoBlockTypes = $neoField->getBlockTypes();
            $neoBlockTypeGroups = $neoField->getGroups();

            foreach ($neoBlockTypes as $neoBlockType) {
                $neoBlockTypeIds[$neoBlockType->uid] = $neoBlockType->id;
            }

            $matrixField = new MatrixField();
            $matrixField->id = $neoField->id;
            $matrixField->name = $neoField->name;
            $matrixField->handle = $neoField->handle;
            $matrixEntryTypes = $this->convertBlockTypesToEntryTypes($neoBlockTypes);
            $matrixField->setEntryTypes($matrixEntryTypes);
            $matrixField->minEntries = (int)$neoField->minBlocks;
            $matrixField->maxEntries = (int)$neoField->maxBlocks;
            $matrixField->propagationMethod = $neoField->propagationMethod;
            $matrixField->viewMode = $matrixField::VIEW_MODE_BLOCKS;
            $matrixField->uid = $neoField->uid;

            if (!$fieldsService->saveField($matrixField, false)) {
                throw new Exception("Unable to save Matrix field");
            }

            $neoBlocks = [];
            $matrixEntries = [];
            $matrixEntryTypeIdsByEntryId = [];
            $neoToMatrixTypeIds = [];
            $neoToMatrixElementIds = [];
            $matrixEntryTypeFieldIds = [];
            $newRelations = [];

            // Find all blocks for this field from each site.
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                $siteBlocks = Block::find()
                    ->fieldId($neoField->id)
                    ->ownerId(null)
                    ->siteId($siteId)
                    ->limit(null)
                    ->status(null)
                    ->all();

                foreach ($siteBlocks as $siteBlock) {
                    if (!isset($neoBlocks[$siteBlock->id])) {
                        $neoBlocks[$siteBlock->id] = $siteBlock;
                    }
                }
            }

            foreach ($neoBlocks as $neoBlock) {
                $neoBlockType = $neoBlock->getType();
                $matrixEntryType = ArrayHelper::firstWhere($matrixField->getEntryTypes(), 'handle', $neoBlockType->handle);
                $matrixEntry = $this->convertBlockToEntry($neoBlock, $matrixEntryType);

                // The Neo block's block type ID is added here so it can be grabbed (and replaced with the Matrix entry
                // type ID) when looping over these Matrix entries later on
                $matrixEntry->id = $neoBlock->id;
                $matrixEntry->typeId = $neoBlock->typeId;
                $matrixEntries[] = $matrixEntry;
            }

            // Create a mapping of Neo block type IDs to Matrix entry type IDs
            // This is used below to set the correct entry type to a converted Matrix entry
            foreach ($matrixField->getEntryTypes() as $matrixEntryType) {
                $neoBlockTypeId = $neoBlockTypeIds[$matrixEntryType->uid];
                $neoToMatrixTypeIds[$neoBlockTypeId] = $matrixEntryType->id;

                // Create mapping from newly saved block type field handles to their IDs.
                // This is so that relations can be updated later on with the new field ID.
                $matrixFields = $matrixEntryType->getFieldLayout()->getCustomFields();
                $fieldIds = [];

                foreach ($matrixFields as $matrixFieldLayoutField) {
                    $fieldIds[$matrixFieldLayoutField->handle] = $matrixFieldLayoutField->id;
                }

                $matrixEntryTypeFieldIds[$matrixEntryType->id] = $fieldIds;
            }

            foreach ($matrixEntries as $matrixEntry) {
                $neoBlockId = $matrixEntry->id;

                // Ensure the entry doesn't have an ID
                $matrixEntry->id = null;

                // Assign the correct entry type ID now that it exists (from saving the field above)
                $neoBlockTypeId = $matrixEntry->typeId;
                $matrixEntry->typeId = $neoToMatrixTypeIds[$neoBlockTypeId];

                if (!$elementsService->saveElement($matrixEntry, false)) {
                    throw new Exception("Unable to save Matrix entry");
                }

                // Save the new Matrix entry ID for updating the relations
                $neoToMatrixElementIds[$neoBlockId] = $matrixEntry->id;
                $matrixEntryTypeIdsByEntryId[$matrixEntry->id] = $matrixEntry->typeId;
            }

            // Update the relations with the new Matrix entry IDs (sourceId) and Matrix field IDs
            $relations = (new Query())
                ->select('fieldId, sourceId, sourceSiteId, targetId, sortOrder')
                ->from(Table::RELATIONS)
                ->where(['in', 'sourceId', array_keys($neoToMatrixElementIds)])
                ->all();

            if ($relations) {
                foreach ($relations as $relation) {
                    $neoBlockId = $relation['sourceId'];
                    $matrixEntryId = $neoToMatrixElementIds[$neoBlockId];
                    $matrixEntryTypeId = $matrixEntryTypeIdsByEntryId[$matrixEntryId];
                    $globalFieldId = $relation['fieldId'];
                    $globalField = $globalFields[$globalFieldId];
                    $matrixFieldIds = $matrixEntryTypeFieldIds[$matrixEntryTypeId];
                    $matrixFieldId = $matrixFieldIds[$globalField->handle];
                    $newRelations[] = [
                        $matrixFieldId,
                        $matrixEntryId,
                        $relation['sourceSiteId'],
                        $relation['targetId'],
                        $relation['sortOrder'],
                    ];
                }
            }

            $dbService->createCommand()->batchInsert(Table::RELATIONS, [
                'fieldId',
                'sourceId',
                'sourceSiteId',
                'targetId',
                'sortOrder',
            ], $newRelations);

            if ($deleteOldBlockTypesAndGroups) {
                foreach ($neoBlockTypes as $neoBlockType) {
                    Neo::$plugin->blockTypes->delete($neoBlockType);
                }

                foreach ($neoBlockTypeGroups as $neoBlockTypeGroup) {
                    Neo::$plugin->blockTypes->deleteGroup($neoBlockTypeGroup);
                }
            }

            $transaction->commit();

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Craft::error("Couldn't convert Neo field '{$neoField->handle}' to Matrix: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Converts Neo block types into entry types.
     *
     * @param BlockType[] $blockTypes
     * @return EntryType[]
     */
    public function convertBlockTypesToEntryTypes(array $blockTypes): array
    {
        return Collection::make($blockTypes)
            ->map(fn($blockType) => $this->convertBlockTypeToEntryType($blockType))
            ->all();
    }

    /**
     * Converts a Neo block type to an entry type.
     *
     * @param BlockType $blockType
     * @return EntryType
     */
    public function convertBlockTypeToEntryType(BlockType $blockType): EntryType
    {
        $entriesService = Craft::$app->getEntries();
        $fieldLayout = FieldLayout::createFromConfig($blockType->getFieldLayout()?->getConfig() ?? []);

        foreach ($fieldLayout->getTabs() as $tab) {
            $tab->uid = StringHelper::UUID();

            foreach ($tab->getElements() as $element) {
                $element->uid = StringHelper::UUID();
            }
        }

        $entryType = new EntryType();
        $entryType->uid = $blockType->uid;
        $entryType->setFieldLayout($fieldLayout);
        $i = 0;

        do {
            $entryType->name = $blockType->name . (++$i !== 1 ? " $i" : '');
            $entryType->handle = $blockType->handle . ($i !== 1 ? "$i" : '');
            $success = $entriesService->saveEntryType($entryType);
        } while (!$success);

        return $entryType;
    }

    /**
     * Converts a Neo block to an entry, retaining all content.
     *
     * @param Block $neoBlock
     * @param EntryType|null $entryType
     * @return Entry
     */
    public function convertBlockToEntry(Block $neoBlock, ?EntryType $entryType = null): Entry
    {
        $fieldsService = Craft::$app->getFields();
        $entryFieldValues = [];

        foreach ($neoBlock->getFieldValues() as $handle => $value) {
            // Serialise Matrix field values
            if ($value instanceof EntryQuery && $value->fieldId) {
                $value = $fieldsService->getFieldById($value->fieldId)->serializeValue($value, null);
            }

            $entryFieldValues[$handle] = $value;
        }

        $entry = new Entry();
        $entry->id = null;
        $entry->ownerId = $neoBlock->ownerId;
        $entry->fieldId = $neoBlock->fieldId;
        $entry->siteId = $neoBlock->siteId;
        $entry->sortOrder = $neoBlock->sortOrder;
        $entry->collapsed = $neoBlock->collapsed;
        $entry->setFieldValues($entryFieldValues);

        if ($entryType) {
            $entry->typeId = $entryType->id;
        }

        return $entry;
    }
}
