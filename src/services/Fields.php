<?php
namespace benf\neo\services;

use benf\neo\models\BlockStructure;
use yii\base\Component;

use Craft;
use craft\base\ElementInterface;
use craft\helpers\Html;

use benf\neo\Plugin as Neo;
use benf\neo\Field;
use benf\neo\elements\Block;
use benf\neo\helpers\Memoize;

class Fields extends Component
{
	public function validate(Field $field): bool
	{
		$isValid = true;

		$handles = [];

		foreach ($field->getBlockTypes() as $blockType)
		{
			$isBlockTypeValid = Neo::$plugin->blockTypes->validate($blockType, false);
			$isValid = $isValid && $isBlockTypeValid;

			if (isset($handles[$blockType->handle]))
			{
				$blockType->addError('handle', Craft::t('neo', "{label} \"{value}\" has already been taken.", [
					'label' => $blockType->getAttributeLabel('handle'),
					'value' => Html::encode($blockType->handle),
				]));

				$isValid = false;
			}
			else
			{
				$handles[$blockType->handle] = true;
			}
		}

		return $isValid;
	}

	public function save(Field $field, bool $validate = true): bool
	{
		$dbService = Craft::$app->getDb();

		$isValid = !$validate || $this->validate($field);

		if ($isValid)
		{
			$transaction = $dbService->beginTransaction();
			try
			{
				// Delete the old block types first, in case there's a handle conflict with one of the new ones
				$oldBlockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);
				$oldBlockTypesById = [];

				foreach ($oldBlockTypes as $blockType)
				{
					$oldBlockTypesById[$blockType->id] = $blockType;
				}

				foreach ($field->getBlockTypes() as $blockType)
				{
					if (!$blockType->getIsNew())
					{
						unset($oldBlockTypesById[$blockType->id]);
					}
				}

				foreach ($oldBlockTypesById as $blockType)
				{
					Neo::$plugin->blockTypes->delete($blockType);
				}

				// Delete all groups to be replaced with what's new
				Neo::$plugin->blockTypes->deleteGroupsByFieldId($field->id);

				// Save the new block types and groups
				foreach ($field->getBlockTypes() as $blockType)
				{
					$blockType->fieldId = $field->id;
					Neo::$plugin->blockTypes->save($blockType, false);
				}

				foreach ($field->getGroups() as $blockTypeGroup)
				{
					$blockTypeGroup->fieldId = $field->id;
					Neo::$plugin->blockTypes->saveGroup($blockTypeGroup);
				}

				$transaction->commit();

				Memoize::$blockTypesByFieldId[$field->id] = $field->getBlockTypes();
				Memoize::$blockTypeGroupsByFieldId[$field->id] = $field->getGroups();
			}
			catch (\Throwable $e)
			{
				$transaction->rollBack();

				throw $e;
			}
		}

		return $isValid;
	}

	public function delete(Field $field): bool
	{
		$dbService = Craft::$app->getDb();

		$transaction = $dbService->beginTransaction();
		try
		{
			$blockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);

			foreach ($blockTypes as $blockType)
			{
				Neo::$plugin->blockTypes->delete($blockType);
			}

			Neo::$plugin->blockTypes->deleteGroupsByFieldId($field->id);

			$transaction->commit();
		}
		catch (\Throwable $e)
		{
			$transaction->rollBack();

			throw $e;
		}

		return true;
	}

	public function saveValue(Field $field, ElementInterface $owner, bool $isNew)
	{
		$dbService = Craft::$app->getDb();
		$elementsService = Craft::$app->getElements();

		$query = $owner->getFieldValue($field->handle);
		$ownerSiteId = $field->localizeBlocks ? $owner->siteId : null;

		$isSite = $query->siteId == $owner->siteId;
		$isNewElement = !$query->ownerId;
		$isDuplicatingElement = $query->ownerId && $query->ownerId != $owner->id;

		if ($isSite)
		{
			$blocks = $query->getCachedResult();

			if ($blocks === null)
			{
				$query = clone $query;
				$query->status = null;
				$query->enabledForSite = false;

				$blocks = $query->all();
			}

			$transaction = $dbService->beginTransaction();
			try
			{
				if (!$isNewElement)
				{
					$this->_applyFieldTranslationSettings($query->ownerId, $query->siteId, $field);
				}

				if ($isDuplicatingElement)
				{
					$blockCheckQuery = clone $query;
					$blockCheckQuery->ownerId = $owner->id;

					$hasBlocks = $blockCheckQuery->exists();

					if (!$hasBlocks)
					{
						foreach ($blocks as $block)
						{
							$duplicatedBlock = $elementsService->duplicateElement($block, [
								'ownerId' => $owner->id,
								'ownerSiteId' => $ownerSiteId,
							]);

							$duplicatedBlock->setCollapsed($block->getCollapsed());
							$duplicatedBlock->cacheCollapsed();
						}
					}
				}
				else
				{
					$blockIds = [];

					foreach ($blocks as $block)
					{
						$block->ownerId = $owner->id;
						$block->ownerSiteId = $ownerSiteId;
						$block->propagating = $owner->propagating;

						$elementsService->saveElement($block, false, !$owner->propagating);
						$block->cacheCollapsed();

						$blockIds[] = $block->id;
					}

					$deleteQuery = Block::find()
						->status(null)
						->enabledForSite(false)
						->ownerId($owner->id)
						->fieldId($field->id)
						->where(['not', ['elements.id' => $blockIds] ]);

					if ($field->localizeBlocks)
					{
						$deleteQuery->ownerSiteId($owner->siteId);
					}
					else
					{
						$deleteQuery->siteId($owner->siteId);
					}

					$deleteBlocks = $deleteQuery->all();

					foreach ($deleteBlocks as $deleteBlock)
					{
						$deleteBlock->forgetCollapsed();
						$elementsService->deleteElement($deleteBlock);
					}

					$blockStructure = Neo::$plugin->blocks->getStructure($field->id, $owner->id, $ownerSiteId);

					if ($blockStructure)
					{
						Neo::$plugin->blocks->deleteStructure($blockStructure);
					}

					if (!empty($blocks))
					{
						$blockStructure = new BlockStructure();
						$blockStructure->fieldId = $field->id;
						$blockStructure->ownerId = $owner->id;
						$blockStructure->ownerSiteId = $ownerSiteId;

						Neo::$plugin->blocks->saveStructure($blockStructure);
						Neo::$plugin->blocks->buildStructure($blocks, $blockStructure);
					}
				}

				$transaction->commit();
			}
			catch (\Throwable $e)
			{
				$transaction->rollBack();

				throw $e;
			}
		}
	}

	private function _applyFieldTranslationSettings(int $ownerId, int $ownerSiteId, Field $field)
	{

	}
}
