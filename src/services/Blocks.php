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

/**
 * Class Blocks
 *
 * @package benf\neo\services
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Blocks extends Component
{
	/**
	 * Returns a Neo block given its ID.
	 *
	 * @param int $blockId The Neo block ID to look for.
	 * @param int|null $siteId The site the Neo block should belong to.
	 * @return Block|null The Neo block found, if any.
	 */
	public function getBlockById(int $blockId, int $siteId = null)
	{
		return Craft::$app->getElements()->getElementById($blockId, Block::class, $siteId);
	}

	/**
	 * Gets the search keywords to be associated with the given Neo block.
	 *
	 * Checks the fields associated with the given Neo block, finds their search keywords and concatenates them.
	 *
	 * @param Block $block The Neo block.
	 * @param ElementInterface|null $element The element the Neo block is associated with, if any.
	 * $return string The search keywords.
	 */
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

	/**
	 * Renders a Neo block's tabs.
	 *
	 * @param Block $block The Neo block having its tabs rendered.
	 * @param bool $static Whether to generate static tab content.
	 * @param string|null $namespace
	 * @return array The tabs data.
	 */
	public function renderTabs(Block $block, bool $static = false, $namespace = null): array
	{
		$viewService = Craft::$app->getView();

		$blockType = $block->getType();
		$field = $blockType->getField();

		$namespace = $namespace ?? $viewService->namespaceInputName($field->handle);
		$oldNamespace = $viewService->getNamespace();
		$newNamespace = $namespace . '[__NEOBLOCK__][fields]';
		$viewService->setNamespace($newNamespace);

		// Ensure that this block is actually new, and not just a pasted or cloned block
		// New blocks won't have their levels set at this stage, whereas they will be set for pasted/cloned blocks
		$isNewBlock = $block->id === null && $block->level === null;

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
					if ($isNewBlock)
					{
						$field->setIsFresh(true);
					}

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

			if ($isNewBlock)
			{
				// Reset $_isFresh's
				foreach ($fields as $field)
				{
					$field->setIsFresh(null);
				}
			}

			$fieldsJs = $viewService->clearJsBuffer();

			$tabHtml['bodyHtml'] = $viewService->namespaceInputs($fieldsHtml);
			$tabHtml['footHtml'] = $fieldsJs;

			$tabsHtml[] = $tabHtml;
		}

		$viewService->setNamespace($oldNamespace);

		return $tabsHtml;
	}

	/**
	 * Gets a Neo block structure.
	 * Looks for a block structure associated with a given field ID and owner ID, and optionally the owner's site ID.
	 *
	 * @param int $fieldId The field ID to look for.
	 * @param int $ownerId The owner ID to look for.
	 * @return BlockStructure|null The block structure found, if any.
	 */
	public function getStructure(int $fieldId, int $ownerId, int $siteId=null)
	{
		$blockStructure = null;

		$query = $this->_createStructureQuery()
			->where([
				'fieldId' => $fieldId,
				'ownerId' => $ownerId,
                'ownerSiteId' => $siteId
			]);

		$result = $query->one();

		if ($result)
		{
			$blockStructure = new BlockStructure($result);
		}

		return $blockStructure;
	}

	/**
	 * Gets a Neo block structure given its ID.
	 *
	 * @param int $id The block structure ID to look for.
	 * @return BlockStructure|null The block structure found, if any.
	 */
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

	/**
	 * Saves a Neo block structure.
	 *
	 * @param BlockStructure $blockStructure The block structure to save.
	 * @throws \Throwable
	 */
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

			$record->structureId = (int)$blockStructure->structureId;
			$record->ownerId = (int)$blockStructure->ownerId;
            $record->ownerSiteId = (int)$blockStructure->ownerSiteId;
			$record->fieldId = (int)$blockStructure->fieldId;

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

	/**
	 * Deletes a Neo block structure.
	 *
	 * @param BlockStructure $blockStructure The block structure to delete.
	 * @return bool Whether the deletion was successful.
	 * @throws \Throwable
	 */
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

	/**
	 * Builds a Neo block structure.
	 *
	 * @param array $blocks The Neo blocks to associate with the block structure.
	 * @param BlockStructure $blockStructure The Neo block structure.
	 * @return bool Whether building the block structure was successful.
	 * @throws \Throwable
	 */
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

	/**
	 * Creates a basic Neo block structure query.
	 *
	 * @return Query
	 */
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
