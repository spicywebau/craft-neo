<?php
namespace benf\neo\services;

use yii\base\Component;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\models\Structure;
use craft\helpers\StringHelper;

use benf\neo\elements\Block;
use benf\neo\models\BlockType;
use benf\neo\models\BlockStructure;
use benf\neo\records\BlockStructure as BlockStructureRecord;

class Blocks extends Component
{
	public function getSearchKeywords(Block $block, ElementInterface $element = null): string
	{
		$fieldsService = Craft::$app->getFields();

		if ($element === null)
		{
			$element = $block;
		}

		$keywords = [];

		$fieldLayout = $block->getFieldLayout();
		$fieldIds = $fieldLayout->getFieldIds();

		foreach ($fieldsService->getAllFields() as $field)
		{
			if (in_array($field->id, $fieldIds))
			{
				$fieldValue = $block->getFieldValue($field->handle);
				$keywords[] = $field->getSearchKeywords($fieldValue, $element);
			}
		}

		return StringHelper::toString($keywords, ' ');
	}

	public function renderTabs(Block $block, bool $static = false, $namespace = null): array
	{
		$viewService = Craft::$app->getView();

		$blockType = $block->getType();
		$field = $blockType->getField();

		$namespace = $namespace ?? $viewService->namespaceInputName($field->handle);
		$oldNamespace = $viewService->getNamespace();
		$newNamespace = $namespace . '[__NEOBLOCK__][fields]';
		$viewService->setNamespace($newNamespace);

		$tabsHtml = [];

		$fieldLayout = $blockType->getFieldLayout();
		$tabs = $fieldLayout->getTabs();

		foreach ($tabs as $tab)
		{
			$viewService->startJsBuffer();

			$tabHtml = [
				'name' => Craft::t('neo', $tab->name),
				'headHtml' => '',
				'bodyHtml' => '',
				'footHtml' => '',
				'errors' => [],
			];

			$fields = $tab->getFields();

			if ($block)
			{
				foreach ($fields as $field)
				{
					$fieldErrors = $block->getErrors($field->handle);

					if (!empty($fieldErrors))
					{
						$tabHtml['errors'] = array_merge($tabHtml['errors'], $fieldErrors);
					}
				}
			}

			$fieldsHtml = $viewService->renderTemplate('_includes/fields', [
				'namespace' => null,
				'element' => $block,
				'fields' => $fields,
				'static' => $static,
			]);

			$fieldsJs = $viewService->clearJsBuffer();

			$tabHtml['bodyHtml'] = $viewService->namespaceInputs($fieldsHtml);
			$tabHtml['footHtml'] = $fieldsJs;

			$tabsHtml[] = $tabHtml;
		}

		$viewService->setNamespace($oldNamespace);

		return $tabsHtml;
	}

	public function getStructure(int $fieldId, int $ownerId, $ownerSiteId = null)
	{
		$blockStructure = null;

		$query = $this->_createStructureQuery()
			->where([
				'fieldId' => $fieldId,
				'ownerId' => $ownerId,
			]);

		if ($ownerSiteId)
		{
			$query->andWhere(['ownerSiteId' => $ownerSiteId]);
		}

		$result = $query->one();

		if ($result)
		{
			$blockStructure = new BlockStructure($result);
		}

		return $blockStructure;
	}

	public function getStructureById(int $id)
	{
		$blockStructure = null;

		$result = $this->_createStructureQuery()
			->where(['id' => $id])
			->one();

		if ($result)
		{
			$blockStructure = new BlockStructure($result);
		}

		return $blockStructure;
	}

	public function saveStructure(BlockStructure $blockStructure)
	{
		$dbService = Craft::$app->getDb();
		$structuresService = Craft::$app->getStructures();

		$record = new BlockStructureRecord();

		$transaction = $dbService->beginTransaction();
		try
		{
			$this->deleteStructure($blockStructure);

			$structure = $blockStructure->getStructure();

			if (!$structure)
			{
				$structure = new Structure();
				$structuresService->saveStructure($structure);
				$blockStructure->structureId = $structure->id;
			}

			$record->structureId = $blockStructure->structureId;
			$record->ownerId = $blockStructure->ownerId;
			$record->ownerSiteId = $blockStructure->ownerSiteId;
			$record->fieldId = $blockStructure->fieldId;

			$record->save(false);

			$blockStructure->id = $record->id;

			$transaction->commit();
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}
	}

	public function deleteStructure(BlockStructure $blockStructure): bool
	{
		$dbService = Craft::$app->getDb();
		$structuresService = Craft::$app->getStructures();

		$success = false;

		if ($blockStructure->id)
		{
			$transaction = $dbService->beginTransaction();
			try
			{
				if ($blockStructure->structureId)
				{
					$structuresService->deleteStructureById($blockStructure->structureId);
				}

				$affectedRows = $dbService->createCommand()
					->delete('{{%neoblockstructures}}', [
						'id' => $blockStructure->id,
						'ownerId' => $blockStructure->ownerId,
						'ownerSiteId' => $blockStructure->ownerSiteId,
						'fieldId' => $blockStructure->fieldId,
					])
					->execute();

				$transaction->commit();

				$success = (bool)$affectedRows;
			}
			catch (\Throwable $e)
			{
				$transaction->rollBack();

				throw $e;
			}
		}

		return $success;
	}

	public function buildStructure(array $blocks, BlockStructure $blockStructure): bool
	{
		$dbService = Craft::$app->getDb();
		$structuresService = Craft::$app->getStructures();

		$success = false;

		$structure = $blockStructure->getStructure();

		if ($structure)
		{
			$transaction = $dbService->beginTransaction();
			try
			{
				// Build the block structure by mapping block sort orders and levels to parent/child relationships
				$parentStack = [];

				foreach ($blocks as $block)
				{
					// Remove parent blocks until either empty or a parent block is only one level below this one (meaning
					// it'll be the parent of this block)
					while (!empty($parentStack) && $block->level <= $parentStack[count($parentStack) - 1]->level)
					{
						array_pop($parentStack);
					}

					// If there are no blocks in our stack, it must be a root level block
					if (empty($parentStack))
					{
						$structuresService->appendToRoot($structure->id, $block);
					}
					// Otherwise, the block at the top of the stack will be the parent
					else
					{
						$parentBlock = $parentStack[count($parentStack) - 1];
						$structuresService->append($structure->id, $block, $parentBlock);
					}

					// The current block may potentially be a parent block as well, so save it to the stack
					array_push($parentStack, $block);
				}

				$transaction->commit();

				$success = true;
			}
			catch (\Throwable $e)
			{
				$transaction->rollBack();

				throw $e;
			}
		}

		return $success;
	}

	private function _createStructureQuery()
	{
		return (new Query())
			->select([
				'id',
				'structureId',
				'ownerId',
				'ownerSiteId',
				'fieldId',
			])
			->from(['{{%neoblockstructures}}']);
	}
}
