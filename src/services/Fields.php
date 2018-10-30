<?php
namespace benf\neo\services;

use benf\neo\models\BlockStructure;
use yii\base\Component;

use Craft;
use craft\base\ElementInterface;
use craft\fields\BaseRelationField;
use craft\helpers\Html;

use benf\neo\Plugin as Neo;
use benf\neo\Field;
use benf\neo\elements\Block;
use benf\neo\helpers\Memoize;

/**
 * Class Fields
 *
 * @package benf\neo\services
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Fields extends Component
{
	/**
	 * Performs validation on a Neo field.
	 *
	 * @param Field $field The field to validate.
	 * @return bool Whether validation was successful.
	 */
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

	/**
	 * Saves a Neo field's settings.
	 *
	 * @param Field $field The field to save.
	 * @param bool $validate Whether to perform validation.
	 * @return bool Whether saving was successful.
	 * @throws \Throwable
	 */
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

	/**
	 * Deletes a Neo field.
	 *
	 * @param Field $field The field to delete.
	 * @return bool Whether deletion was successful.
	 * @throws \Throwable
	 */
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

	/**
	 * Saves a Neo field's value for a given element.
	 *
	 * @param Field $field The Neo field.
	 * @param ElementInterface $owner The element that owns the Neo field.
	 * @param bool $isNew
	 * @throws \Throwable
	 */
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
						->inReverse()
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

	/**
	 * Applies translation settings for this field to its blocks for a given owner and owner site ID.
	 *
	 * @param int $ownerId The ID of the blocks' owner.
	 * @param int $ownerSiteId The ID of the blocks' owner's site.
	 * @param Field $field The field having its translation settings applied to its blocks.
	 */
	private function _applyFieldTranslationSettings(int $ownerId, int $ownerSiteId, Field $field)
	{
		$elementsService = Craft::$app->getElements();
		$sitesService = Craft::$app->getSites();
		$siteIds = $sitesService->getAllSiteIds();
		$fieldId = $field->id;
		$hasPerSiteBlocks = $field->localizeBlocks;

		if ($hasPerSiteBlocks)
		{
			$query = Block::find()
				->fieldId($fieldId)
				->ownerId($ownerId)
				->siteId($ownerSiteId)
				->ownerSiteId(':empty:')
				->anyStatus();

			$blocks = $query->all();
			$hasBlocks = !empty($blocks);

			if ($hasBlocks)
			{
				$relatedElementFields = [];

				foreach ($blocks as $block)
				{
					$blockType = $block->getType();
					$blockTypeId = $blockType->id;
					$blockTypeFields = $blockType->getFields();
					$relatedElementFieldsSet = isset($relatedElementFields[$blockTypeId]);

					if (!$relatedElementFieldsSet)
					{
						$relatedElementFields[$blockTypeId] = [];

						foreach ($blockTypeFields as $blockTypeField)
						{
							if ($blockTypeField instanceof BaseRelationField)
							{
								$relatedElementFields[$blockTypeId][] = $blockTypeField->handle;
							}
						}
					}
				}

				foreach ($siteIds as $siteId)
				{
					$isOwnerSite = $siteId == $ownerSiteId;

					if (!$isOwnerSite)
					{
						$query->siteId($siteId);
						$siteBlocks = $query->all();

						foreach ($siteBlocks as $siteBlock)
						{
							$blockIsCollapsed = $siteBlock->getCollapsed();
							$blockTypeId = $siteBlock->typeId;
							$relatedElementFieldsSet = isset($relatedElementFields[$blockTypeId]);

							if ($relatedElementFieldsSet)
							{
								foreach ($relatedElementFields[$blockTypeId] as $handle)
								{
									$relatedQuery = $siteBlock->getFieldValue($handle);
									$relatedElementIds = $relatedQuery->ids();
									$siteBlock->setFieldValue($handle, $relatedElementIds);
								}
							}

							$siteBlock->id = null;
							$siteBlock->contentId = null;
							$siteBlock->siteId = (int)$siteId;
							$siteBlock->ownerSiteId = (int)$siteId;

							$elementsService->saveElement($siteBlock, false);
							$siteBlock->setCollapsed($blockIsCollapsed);
							$siteBlock->cacheCollapsed();
						}

						$siteBlockStructure = new BlockStructure();
						$siteBlockStructure->fieldId = $fieldId;
						$siteBlockStructure->ownerId = $ownerId;
						$siteBlockStructure->ownerSiteId = (int)$siteId;

						Neo::$plugin->blocks->saveStructure($siteBlockStructure);
						Neo::$plugin->blocks->buildStructure($siteBlocks, $siteBlockStructure);
					}
				}

				foreach ($blocks as $block)
				{
					$block->ownerSiteId = $ownerSiteId;
					$elementsService->saveElement($block, false);
				}
			}
		}
		else
		{
			foreach ($siteIds as $siteId)
			{
				$siteIsOwner = $siteId == $ownerSiteId;

				if (!$siteIsOwner)
				{
					$deleteQuery = Block::find()
						->fieldId($fieldId)
						->ownerId($ownerId)
						->siteId($siteId)
						->ownerSiteId($siteId)
						->anyStatus()
						->inReverse();

					$deleteBlocks = $deleteQuery->all();

					foreach ($deleteBlocks as $deleteBlock)
					{
						$deleteBlock->forgetCollapsed();
						$elementsService->deleteElement($deleteBlock);
					}

					$blockStructure = Neo::$plugin->blocks->getStructure($fieldId, $ownerId, (int)$siteId);

					if ($blockStructure)
					{
						Neo::$plugin->blocks->deleteStructure($blockStructure);
					}
				}
			}
		}
	}
}
