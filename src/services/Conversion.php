<?php

namespace benf\neo\services;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\models\BlockType as NeoBlockType;
use benf\neo\Plugin as Neo;
use Craft;
use craft\db\Query;
use craft\elements\MatrixBlock;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix as MatrixField;
use craft\helpers\ArrayHelper;
use craft\models\MatrixBlockType;
use verbb\supertable\elements\db\SuperTableBlockQuery;
use verbb\supertable\fields\SuperTableField;
use verbb\supertable\models\SuperTableBlockTypeModel as SuperTableBlockType;
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
     * @return bool
     * @throws \Throwable
     */
    public function convertFieldToMatrix(Field $neoField)
    {
        $fieldsService = Craft::$app->getFields();
        $globalFields = [];

        foreach ($fieldsService->getAllFields() as $field) {
            $globalFields[$field->id] = $field;
        }

        $dbService = Craft::$app->getDb();
        $transaction = $dbService->beginTransaction();

        try {
            // Save a mapping of block type handles to their Neo ID for use later on when migrating content.
            $neoBlockTypeIds = [];
            $neoBlockTypes = $neoField->getBlockTypes();

            foreach ($neoBlockTypes as $neoBlockType) {
                $neoBlockTypeIds[$neoBlockType->handle] = $neoBlockType->id;
            }

            $matrixField = new MatrixField();
            $matrixField->id = $neoField->id;
            $matrixField->groupId = $neoField->groupId;
            $matrixField->name = $neoField->name;
            $matrixField->handle = $neoField->handle;
            $matrixBlockTypes = $this->getNewBlockTypesData($neoBlockTypes);
            $matrixField->setBlockTypes($matrixBlockTypes);
            $matrixField->minBlocks = (int)$neoField->minBlocks;
            $matrixField->maxBlocks = (int)$neoField->maxBlocks;
            $matrixField->propagationMethod = $neoField->propagationMethod;
            $matrixField->uid = $neoField->uid;

            if (!$fieldsService->saveField($matrixField, false)) {
                throw new Exception("Unable to save Matrix field");
            }

            $neoBlocks = [];
            $matrixBlocks = [];
            $matrixBlockTypeIdsByBlockId = [];
            $neoToMatrixBlockTypeIds = [];
            $neoToMatrixBlockIds = [];
            $matrixBlockTypeFieldIds = [];
            $newRelations = [];

            // Find all blocks for this field from each site.
            foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
                $siteBlocks = Block::find()
                    ->fieldId($neoField->id)
                    ->ownerId(null)
                    ->siteId($siteId)
                    ->limit(null)
                    ->anyStatus()
                    ->all();

                foreach ($siteBlocks as $siteBlock) {
                    if (!isset($neoBlocks[$siteBlock->id])) {
                        $neoBlocks[$siteBlock->id] = $siteBlock;
                    }
                }
            }

            foreach ($neoBlocks as $neoBlock) {
                $neoBlockType = $neoBlock->getType();
                $matrixBlockType = ArrayHelper::firstWhere($matrixField->getBlockTypes(), 'handle', $neoBlockType->handle);
                $matrixBlock = $this->convertBlockToMatrix($neoBlock, $matrixBlockType);

                // The Neo block's block type ID is added here so it can be grabbed (and replaced with the Matrix block
                // type ID) when looping over these Matrix blocks later on.
                $matrixBlock->id = $neoBlock->id;
                $matrixBlock->typeId = $neoBlock->typeId;
                $matrixBlocks[] = $matrixBlock;
            }

            // Create a mapping of Neo block type IDs to Matrix block type IDs. This is used below to set the
            // correct block type to a converted Matrix block.
            foreach ($matrixField->getBlockTypes() as $matrixBlockType) {
                $neoBlockTypeId = $neoBlockTypeIds[$matrixBlockType->handle];
                $neoToMatrixBlockTypeIds[$neoBlockTypeId] = $matrixBlockType->id;

                // Create mapping from newly saved block type field handles to their IDs.
                // This is so that relations can be updated later on with the new field ID.
                $matrixFields = array_map(function($field) {
                    return $field->getField();
                }, array_filter($matrixBlockType->getFieldLayout()->getTabs()[0]->elements, function($field) {
                    return $field instanceof CustomField;
                }));
                $fieldIds = [];

                foreach ($matrixFields as $matrixFieldLayoutField) {
                    $fieldIds[$matrixFieldLayoutField->handle] = $matrixFieldLayoutField->id;
                }

                $matrixBlockTypeFieldIds[$matrixBlockType->id] = $fieldIds;
            }

            foreach ($matrixBlocks as $matrixBlock) {
                $neoBlockId = $matrixBlock->id;

                // Ensure the block doesn't have an ID.
                $matrixBlock->id = null;

                // Assign the correct block type ID now that it exists (from saving the field above).
                $neoBlockTypeId = $matrixBlock->typeId;
                $matrixBlock->typeId = $neoToMatrixBlockTypeIds[$neoBlockTypeId];

                if (!Craft::$app->getElements()->saveElement($matrixBlock, false)) {
                    throw new Exception("Unable to save Matrix block");
                }

                // Save the new Matrix block ID for updating the relations.
                $neoToMatrixBlockIds[$neoBlockId] = $matrixBlock->id;
                $matrixBlockTypeIdsByBlockId[$matrixBlock->id] = $matrixBlock->typeId;
            }

            // Update the relations with the new Matrix block IDs (sourceId) and Matrix field IDs.
            $relations = (new Query())
                ->select('fieldId, sourceId, sourceSiteId, targetId, sortOrder')
                ->from('{{%relations}}')
                ->where(['in', 'sourceId', array_keys($neoToMatrixBlockIds)])
                ->all();

            if ($relations) {
                foreach ($relations as $relation) {
                    $neoBlockId = $relation['sourceId'];
                    $matrixBlockId = $neoToMatrixBlockIds[$neoBlockId];
                    $matrixBlockTypeId = $matrixBlockTypeIdsByBlockId[$matrixBlockId];
                    $globalFieldId = $relation['fieldId'];
                    $globalField = $globalFields[$globalFieldId];
                    $matrixFieldIds = $matrixBlockTypeFieldIds[$matrixBlockTypeId];
                    $matrixFieldId = $matrixFieldIds[$globalField->handle];
                    $newRelations[] = [
                        $matrixFieldId,
                        $matrixBlockId,
                        $relation['sourceSiteId'],
                        $relation['targetId'],
                        $relation['sortOrder'],
                    ];
                }
            }

            $dbService->createCommand()->batchInsert('{{%relations}}', [
                'fieldId',
                'sourceId',
                'sourceSiteId',
                'targetId',
                'sortOrder',
            ], $newRelations);

            $transaction->commit();

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Craft::error("Couldn't convert Neo field '{$neoField->handle}' to Matrix: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Converts Neo block types into Matrix block types.
     *
     * @param array $neoBlockTypes
     * @param Field|null $neoField
     * @return array The Matrix block types.
     */
    public function convertBlockTypesToMatrix(array $neoBlockTypes, Field $neoField = null): array
    {
        $matrixBlockTypes = [];
        $ids = 1;

        foreach ($neoBlockTypes as $neoBlockType) {
            $matrixBlockType = $this->convertBlockTypeToMatrix($neoBlockType, $neoField);
            $matrixBlockType->id = 'new' . ($ids++);
            $matrixBlockTypes[] = $matrixBlockType;
        }

        return $matrixBlockTypes;
    }

    /**
     * Converts a Neo block type to a Matrix block type.
     *
     * @param NeoBlockType $neoBlockType
     * @param Field|null $field
     * @return MatrixBlockType
     */
    public function convertBlockTypeToMatrix(BlockType $neoBlockType, Field $field = null): MatrixBlockType
    {
        $matrixBlockType = new MatrixBlockType();
        $matrixBlockType->fieldId = $field ? $field->id : $neoBlockType->fieldId;
        $matrixBlockType->name = $neoBlockType->name;
        $matrixBlockType->handle = $neoBlockType->handle;

        $neoFieldLayout = $neoBlockType->getFieldLayout();
        $neoBlockTypeFields = $neoFieldLayout->getFields();
        $matrixBlockTypeFields = [];

        foreach ($neoBlockTypeFields as $neoBlockTypeField) {
            $fieldType = get_class($neoBlockTypeField);

            if (!in_array($fieldType, [MatrixField::class, Field::class])) {
                $matrixBlockTypeField = clone $neoBlockTypeField;
                $matrixBlockTypeField->id = null;
                $matrixBlockTypeField->groupId = null;
                $matrixBlockTypeField->context = null;
                $matrixBlockTypeField->name = $neoBlockTypeField->name;
                $matrixBlockTypeField->handle = $neoBlockTypeField->handle;
                $matrixBlockTypeField->required = (bool)$neoBlockTypeField->required;
                $matrixBlockTypeField->uid = null;

                // Force disable translation on fields if the Neo field was also translatable
                if ($field && $field->translatable) {
                    $matrixBlockTypeField->translatable = false;
                }

                $matrixBlockTypeFields[] = $matrixBlockTypeField;
            }
        }

        $matrixBlockType->setFields($matrixBlockTypeFields);

        return $matrixBlockType;
    }

    /**
     * Converts a Neo block to a Matrix block, retaining all content.
     *
     * @param Block $neoBlock
     * @param MatrixBlockType|null $matrixBlockType
     * @return MatrixBlock
     */
    public function convertBlockToMatrix(Block $neoBlock, MatrixBlockType $matrixBlockType = null): MatrixBlock
    {
        $blockFieldValues = [];

        foreach ($neoBlock->getFieldValues() as $handle => $value) {
            if ($value instanceof SuperTableBlockQuery && $matrixBlockType) {
                // We need to get the Super Table field from the Matrix block type's field layout;
                // Super Table serialised blocks identify their block type by ID (since Super Table
                // fields have exactly one block type, they don't really need handles) so if we
                // don't have the correct field from the correct layout, we have the incorrect ID.
                $fieldLayoutFields = array_map(function($field) {
                    return $field->getField();
                }, array_filter($matrixBlockType->getFieldLayout()->getTabs()[0]->elements, function($field) {
                    return $field instanceof CustomField;
                }));
                $superTableField = ArrayHelper::firstWhere($fieldLayoutFields, 'handle', $handle);

                $value = array_map(function($block) use ($superTableField) {
                    $block['type'] = $superTableField->getBlockTypes()[0]->id;
                    return $block;
                }, Craft::$app->getFields()->getFieldById($value->fieldId)->serializeValue($value));
            }

            $blockFieldValues[$handle] = $value;
        }

        $matrixBlock = new MatrixBlock();
        $matrixBlock->id = null;
        $matrixBlock->ownerId = $neoBlock->ownerId;
        $matrixBlock->fieldId = $neoBlock->fieldId;
        $matrixBlock->siteId = $neoBlock->siteId;
        $matrixBlock->sortOrder = Neo::$plugin->blockHasSortOrder ? $neoBlock->sortOrder : $neoBlock->lft;
        $matrixBlock->collapsed = $neoBlock->collapsed;
        $matrixBlock->setFieldValues($blockFieldValues);

        if ($matrixBlockType) {
            $matrixBlock->typeId = $matrixBlockType->id;
        }

        return $matrixBlock;
    }

    /**
     * Creates Matrix block type data from Neo block types.
     *
     * @param array $oldBlockTypes
     * @return array The Matrix block types.
     */
    private function getNewBlockTypesData(array $oldBlockTypes): array
    {
        $newBlockTypes = [];
        $ids = 1;

        foreach ($oldBlockTypes as $oldBlockType) {
            $newBlockTypes['new' . ($ids++)] = $this->getNewBlockTypeData($oldBlockType);
        }

        return $newBlockTypes;
    }

    /**
     * Creates Matrix block type data from a Neo block type.
     *
     * @param NeoBlockType|MatrixBlockType|SuperTableBlockType $oldBlockType
     * @return array The new block type data.
     */
    private function getNewBlockTypeData($oldBlockType): array
    {
        $ids = 0;
        $oldFieldLayout = $oldBlockType->getFieldLayout();
        $newBlockType = [];

        if (property_exists($oldBlockType, 'name')) {
            $newBlockType['name'] = $oldBlockType->name;
            $newBlockType['handle'] = $oldBlockType->handle;
        }

        if ($oldFieldLayout !== null) {
            foreach ($oldFieldLayout->getCustomFields() as $field) {
                $fieldType = get_class($field);
                $neoContainsMatrix = $oldBlockType instanceof NeoBlockType && $field instanceof MatrixField;
                $containsNeo = $field instanceof Field;
                $containsItself = $oldBlockType instanceof MatrixBlockType && $field instanceof MatrixField ||
                    class_exists(SuperTableField::class) && $oldBlockType instanceof SuperTableBlockType && $field instanceof SuperTableField;

                if (!($neoContainsMatrix || $containsNeo || $containsItself)) {
                    $newBlockType['fields']['new' . (++$ids)] = [
                        'name' => $field->name,
                        'handle' => $field->handle,
                        'instructions' => $field->instructions,
                        'required' => $field->required,
                        'searchable' => $field->searchable,
                        'type' => $fieldType,
                        'translationMethod' => $field->translationMethod,
                        'translationKeyFormat' => $field->translationKeyFormat,
                        'typesettings' => $field->getSettings(),
                        'width' => '100',
                    ];

                    if (in_array($fieldType, [MatrixField::class, SuperTableField::class])) {
                        $newBlockType['fields']['new' . $ids]['typesettings']['contentTable'] = null;
                        $newBlockType['fields']['new' . $ids]['typesettings']['blockTypes'] = $this->getNewBlockTypesData($field->getBlockTypes());
                    }
                }
            }
        }

        return $newBlockType;
    }
}
