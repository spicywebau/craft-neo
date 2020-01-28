<?php
namespace benf\neo\services;

use yii\base\Component;
use yii\base\Exception;

use Craft;
use craft\db\Query;
use craft\elements\MatrixBlock;
use craft\fields\Matrix as MatrixField;
use craft\models\MatrixBlockType;

use benf\neo\Field;
use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;
use benf\neo\models\BlockType;

/**
 * Handles conversion of Neo fields to Matrix.
 *
 * @package benf\neo\services
 * @author Spicy Web <craft@spicyweb.com.au>
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

		foreach ($fieldsService->getAllFields() as $field)
		{
			$globalFields[$field->id] = $field;
		}

		$dbService = Craft::$app->getDb();
		$transaction = $dbService->beginTransaction();

		try
		{
			// Save a mapping of block type handles to their Neo ID for use later on when migrating content.
			$neoBlockTypeIds = [];
			$neoBlockTypes = $neoField->getBlockTypes();
			foreach ($neoBlockTypes as $neoBlockType)
			{
				$neoBlockTypeIds[$neoBlockType->handle] = $neoBlockType->id;
			}

			$matrixField = new MatrixField();
			$matrixField->id = $neoField->id;
			$matrixField->groupId = $neoField->groupId;
			$matrixField->name = $neoField->name;
			$matrixField->handle = $neoField->handle;
			$matrixBlockTypes = $this->convertBlockTypesToMatrix($neoBlockTypes);
			$matrixField->setBlockTypes($matrixBlockTypes);
			$matrixField->minBlocks = $neoField->minBlocks;
			$matrixField->maxBlocks = $neoField->maxBlocks;
			$matrixField->localizeBlocks = $neoField->localizeBlocks;
			$matrixField->propagationMethod = $neoField->propagationMethod;
			$matrixField->uid = $neoField->uid;

			$neoBlocks = [];
			$matrixBlocks = [];
			$matrixBlockTypeIdsByBlockId = [];
			$neoToMatrixBlockTypeIds = [];
			$neoToMatrixBlockIds = [];
			$matrixBlockTypeFieldIds = [];
			$newRelations = [];

			// Find all blocks for this field from each site.
			foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId)
			{
				$siteBlocks = Block::find()
					->fieldId($neoField->id)
					->ownerId(null)
					->siteId($siteId)
					->limit(null)
					->anyStatus()
					->all();

				foreach ($siteBlocks as $siteBlock)
				{
					if (!isset($neoBlocks[$siteBlock->id]))
					{
						$neoBlocks[$siteBlock->id] = $siteBlock;
					}
				}
			}

			foreach ($neoBlocks as $neoBlock)
			{
				$matrixBlock = $this->convertBlockToMatrix($neoBlock);

				// This ID will be replaced with the Matrix block type ID after the field is saved. The Neo's block
				// type ID is added here so it can be grabbed when looping over these Matrix blocks later on.
				$matrixBlock->id = $neoBlock->id;
				$matrixBlock->typeId = $neoBlock->typeId;
				$matrixBlocks[] = $matrixBlock;
			}

			$success = $fieldsService->saveField($matrixField, false);

			if (!$success)
			{
				throw new Exception("Unable to save Matrix field");
			}

			// Create a mapping of Neo block type IDs to Matrix block type IDs. This is used below to set the
			// correct block type to a converted Matrix block.
			foreach ($matrixField->getBlockTypes() as $matrixBlockType)
			{
				$neoBlockTypeId = $neoBlockTypeIds[$matrixBlockType->handle];
				$neoToMatrixBlockTypeIds[$neoBlockTypeId] = $matrixBlockType->id;

				// Create mapping from newly saved block type field handles to their IDs.
				// This is so that relations can be updated later on with the new field ID.
				$matrixFieldLayout = $matrixBlockType->getFieldLayout();
				$matrixFields = $matrixFieldLayout->getFields();
				$fieldIds = [];

				foreach ($matrixFields as $matrixFieldLayoutField)
				{
					$fieldIds[$matrixFieldLayoutField->handle] = $matrixFieldLayoutField->id;
				}

				$matrixBlockTypeFieldIds[$matrixBlockType->id] = $fieldIds;
			}

			foreach ($matrixBlocks as $matrixBlock)
			{
				$neoBlockId = $matrixBlock->id;

				// Ensure the block doesn't have an ID.
				$matrixBlock->id = null;

				// Assign the correct block type ID now that it exists (from saving the field above).
				$neoBlockTypeId = $matrixBlock->typeId;
				$matrixBlock->typeId = $neoToMatrixBlockTypeIds[$neoBlockTypeId];

				$success = Craft::$app->getElements()->saveElement($matrixBlock, false);

				if (!$success)
				{
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

			if ($relations)
			{
				foreach ($relations as $relation)
				{
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
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();
			// NeoPlugin::log("Couldn't convert Neo field '{$neoField->handle}' to Matrix: " . $e->getMessage(), LogLevel::Error);
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

		foreach ($neoBlockTypes as $neoBlockType)
		{
			$matrixBlockType = $this->convertBlockTypeToMatrix($neoBlockType, $neoField);
			$matrixBlockType->id = 'new' . ($ids++);
			$matrixBlockTypes[] = $matrixBlockType;
		}

		return $matrixBlockTypes;
	}

	/**
	 * Converts a Neo block type to a Matrix block type.
	 *
	 * @param BlockType $neoBlockType
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
		$neoFields = $neoFieldLayout->getFields();
		$matrixFields = [];

		$ids = 1;

		foreach ($neoFields as $neoFieldLayoutField)
		{
			$fieldType = get_class($neoFieldLayoutField);

			if (!in_array($fieldType, [MatrixField::class, Field::class]))
			{
				$matrixField = clone $neoFieldLayoutField;
				$matrixField->id = 'new' . ($ids++);
				$matrixField->groupId = null;
				$matrixField->context = null;
				$matrixField->name = $neoFieldLayoutField->name;
				$matrixField->handle = $neoFieldLayoutField->handle;
				$matrixField->required = (bool)$neoFieldLayoutField->required;
				$matrixField->uid = null;

				// Force disable translation on fields if the Neo field was also translatable
				if ($field && $field->translatable)
				{
					$matrixField->translatable = false;
				}

				$matrixFields[] = $matrixField;
			}
		}

		$matrixBlockType->setFields($matrixFields);

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
		$blockFieldValues = $neoBlock->getFieldValues();

		$matrixBlock = new MatrixBlock();
		$matrixBlock->id = null;
		$matrixBlock->ownerId = $neoBlock->ownerId;
		$matrixBlock->fieldId = $neoBlock->fieldId;
		$matrixBlock->siteId = $neoBlock->siteId;
		$matrixBlock->sortOrder = $neoBlock->lft;
		$matrixBlock->collapsed = $neoBlock->collapsed;
		$matrixBlock->setFieldValues($blockFieldValues);

		if ($matrixBlockType)
		{
			$matrixBlock->typeId = $matrixBlockType->id;
		}

		return $matrixBlock;
	}
}
