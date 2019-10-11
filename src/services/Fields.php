<?php
namespace benf\neo\services;

use benf\neo\elements\db\BlockQuery;
use benf\neo\models\BlockStructure;
use yii\base\Component;

use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\fields\BaseRelationField;
use craft\helpers\Html;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;

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
					// since the old groups was deleted, we make sure to add in the new ones only and ignore writing the old groups to the project.yaml file.
					if(empty($blockTypeGroup->id)) {
						$blockTypeGroup->fieldId = $field->id;
						Neo::$plugin->blockTypes->saveGroup($blockTypeGroup);
					}
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
			
			// sort block types so the sort order is descending
			// need to reverse to multi level blocks get deleted before the parent
			usort($blockTypes, function($a, $b)
			{
				if ((int)$a->sortOrder === (int)$b->sortOrder)
				{
					return 0;
				}
				
				return (int)$a->sortOrder > (int)$b->sortOrder ? -1 : 1;
			});

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
	public function saveValue(Field $field, ElementInterface $owner)
	{
		$dbService = Craft::$app->getDb();
		$elementsService = Craft::$app->getElements();
		$neoSettings = Neo::$plugin->getSettings();
		
		$query = $owner->getFieldValue($field->handle);
		$blocks = $query->getCachedResult() ?? (clone $query)->anyStatus()->all();
		$blockIds = [];
		
		$transaction = $dbService->beginTransaction();
		
		try {
			foreach ($blocks as $block) {
				$block->ownerId = (int)$owner->id;
				$elementsService->saveElement($block, false);
				$blockIds[] = $block->id;
				
				if (!$neoSettings->collapseAllBlocks)
				{
					$block->cacheCollapsed();
				}
			}
		
			$this->_deleteOtherBlocks($field, $owner, $blockIds);
		
			if (!empty($blocks))
			{
				// get the supported sites
				$supportedSites = $this->getSupportedSiteIdsForField($field, $owner);
		
				if ($this->_checkSupportedSitesAndPropagation($field, $supportedSites)) {
					foreach ($supportedSites as $site) {
						$this->_saveNeoStructuresForSites($field, $owner, $blocks, $site);
					}
				} else {
					$this->_saveNeoStructuresForSites($field, $owner, $blocks);
				}
			}
		
			if (
				$field->propagationMethod !== Field::PROPAGATION_METHOD_ALL &&
				($owner->propagateAll || !empty($owner->newSiteIds))
			) {
				$ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
				$fieldSiteIds = $this->getSupportedSiteIdsForField($field, $owner);
				$otherSiteIds = array_diff($ownerSiteIds, $fieldSiteIds);
				
				if (!$owner->propagateAll) {
					$otherSiteIds = array_intersect($otherSiteIds, $owner->newSiteIds);
				}
		
				if (!empty($otherSiteIds)) {
					// Get the original element and duplicated element for each of those sites
					/** @var Element[] $otherTargets */
					$otherTargets = $owner::find()
						->drafts($owner->getIsDraft())
						->revisions($owner->getIsRevision())
						->id($owner->id)
						->siteId($otherSiteIds)
						->anyStatus()
						->all();
		
					// Duplicate Matrix blocks, ensuring we don't process the same blocks more than once
					$handledSiteIds = [];
					$cachedQuery = clone $query;
					$cachedQuery->anyStatus();
					$cachedQuery->setCachedResult($blocks);
					$owner->setFieldValue($field->handle, $cachedQuery);
		
					foreach ($otherTargets as $otherTarget) {
						// Make sure we haven't already duplicated blocks for this site, via propagation from another site
						if (isset($handledSiteIds[$otherTarget->siteId])) {
							continue;
						}
						$this->duplicateBlocks($field, $owner, $otherTarget);
						// Make sure we don't duplicate blocks for any of the sites that were just propagated to
						$sourceSupportedSiteIds = $this->getSupportedSiteIdsForField($field, $otherTarget);
						$handledSiteIds = array_merge($handledSiteIds, array_flip($sourceSupportedSiteIds));
					}
					$owner->setFieldValue($field->handle, $query);
				}
			}
		
			$transaction->commit();
		
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}
	}

	/**
	 * Duplicates Neo blocks from one owner element to another.
	 *
	 * @param Field $field The Neo field to duplicate blocks for
	 * @param ElementInterface $source The source element blocks should be duplicated from
	 * @param ElementInterface $target The target element blocks should be duplicated to
	 * @param bool $checkOtherSites Whether to duplicate blocks for the source element's other supported sites
	 * @throws \Throwable if reasons
	 */
	public function duplicateBlocks(Field $field, ElementInterface $source, ElementInterface $target, bool $checkOtherSites = false)
	{
		/** @var Element $source */
		/** @var Element $target */
		$elementsService = Craft::$app->getElements();
		/** @var BlockQuery $query */
		$query = $source->getFieldValue($field->handle);
		
		
		/** @var Block[] $blocks */
		if (($blocks = $query->getCachedResult()) === null) {
			$blocksQuery = clone $query;
			$blocks = $blocksQuery->anyStatus()->all();
		}
		
		$newBlockIds = [];
		$transaction = Craft::$app->getDb()->beginTransaction();
		
		try {
			$newBlocks = [];
			foreach ($blocks as $block) {
				/** @var Block $newBlock */
				$collapsed = $block->getCollapsed();
		
				$newBlock = $elementsService->duplicateElement($block, [
					'ownerId' => $target->id,
					'owner' => $target,
					'siteId' => $target->siteId,
					'propagating' => false,
				]);
		
				$newBlock->setCollapsed($collapsed);
				$newBlock->cacheCollapsed();
		
				$newBlockIds[] = $newBlock->id;
				$newBlocks[] = $newBlock;
			}
			// Delete any blocks that shouldn't be there anymore
			$this->_deleteOtherBlocks($field, $target, $newBlockIds);
		
			if (!empty($newBlocks))
			{
				// $this->_saveNeoStructuresForSites($field, $target, $newBlocks);
		
				// get the supported sites
				$supportedSites = $this->getSupportedSiteIdsForField($field, $target);
		
				if ($this->_checkSupportedSitesAndPropagation($field, $supportedSites)) {
					foreach ($supportedSites as $site) {
						$this->_saveNeoStructuresForSites($field, $target, $newBlocks, $site);
					}
				} else {
					$this->_saveNeoStructuresForSites($field, $target, $newBlocks);
				}
			}
		
			$transaction->commit();
		} catch (\Throwable $e) {
			$transaction->rollBack();
			throw $e;
		}
		// Duplicate blocks for other sites as well?
		if ($checkOtherSites && $field->propagationMethod !== Field::PROPAGATION_METHOD_ALL) {
			// Find the target's site IDs that *aren't* supported by this site's Matrix blocks
			$targetSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($target), 'siteId');
			$fieldSiteIds = $this->getSupportedSiteIdsForField($field, $target);
			$otherSiteIds = array_diff($targetSiteIds, $fieldSiteIds);
			
			if (!empty($otherSiteIds)) {
				// Get the original element and duplicated element for each of those sites
				/** @var Element[] $otherSources */
				$otherSources = $target::find()
					->drafts($source->getIsDraft())
					->revisions($source->getIsRevision())
					->id($source->id)
					->siteId($otherSiteIds)
					->anyStatus()
					->all();
				/** @var Element[] $otherTargets */
				$otherTargets = $target::find()
					->drafts($target->getIsDraft())
					->revisions($target->getIsRevision())
					->id($target->id)
					->siteId($otherSiteIds)
					->anyStatus()
					->indexBy('siteId')
					->all();
				
				// Duplicate Matrix blocks, ensuring we don't process the same blocks more than once
				$handledSiteIds = [];
				foreach ($otherSources as $otherSource) {
					// Make sure the target actually exists for this site
					if (!isset($otherTargets[$otherSource->siteId])) {
						continue;
					}
					// Make sure we haven't already duplicated blocks for this site, via propagation from another site
					if (in_array($otherSource->siteId, $handledSiteIds, false)) {
						continue;
					}
					$this->duplicateBlocks($field, $otherSource, $otherTargets[$otherSource->siteId]);
					// Make sure we don't duplicate blocks for any of the sites that were just propagated to
					$sourceSupportedSiteIds = $this->getSupportedSiteIdsForField($field, $otherSource);
					$handledSiteIds = array_merge($handledSiteIds, $sourceSupportedSiteIds);
				}
			}
		}
	}

	/**
	 * Returns the site IDs that are supported by Matrix blocks for the given Matrix field and owner element.
	 *
	 * @param MatrixField $field
	 * @param ElementInterface $owner
	 * @throws \Throwable if reasons
	 * @return int[]
	 */
	public function getSupportedSiteIdsForField(Field $field, ElementInterface $owner): array
	{
		/** @var Element $owner */
		/** @var Site[] $allSites */
		$allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(), 'id');
		$ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
		$siteIds = [];
		
		foreach ($ownerSiteIds as $siteId) {
			switch ($field->propagationMethod) {
				case Field::PROPAGATION_METHOD_NONE:
					$include = (int)$siteId === (int)$owner->siteId;
					break;
				case Field::PROPAGATION_METHOD_SITE_GROUP:
					$include = (int)$allSites[$siteId]->groupId === (int)$allSites[$owner->siteId]->groupId;
					break;
				case Field::PROPAGATION_METHOD_LANGUAGE:
					$include = $allSites[$siteId]->language === $allSites[$owner->siteId]->language;
					break;
				default:
					$include = true;
					break;
			}
			if ($include) {
				$siteIds[] = $siteId;
			}
		}
		return $siteIds;
	}

	// Private Methods
	// =========================================================================

	/**
	 * Deletes blocks from an owner element
	 *
	 * @param Field $field The Neo field
	 * @param ElementInterface The owner element
	 * @param int[] $except Block IDs that should be left alone
	 * @throws \Throwable if reasons
	 */
	private function _deleteOtherBlocks(Field $field, ElementInterface $owner, array $except)
	{
		$supportedSites = $this->getSupportedSiteIdsForField($field, $owner);
		$supportedSitesCount = count($supportedSites);
		// throw new \Exception(print_r($supportedSitesCount, true));
		if ($supportedSitesCount > 1 && $field->propagationMethod !== Field::PROPAGATION_METHOD_NONE) {
			foreach	($supportedSites as $site) {
				$this->_deleteNeoBlocksAndStructures($field, $owner, $except, $site);
			}
		} else {
			$this->_deleteNeoBlocksAndStructures($field, $owner, $except);
		}
	}
    
	private function _checkSupportedSitesAndPropagation($field, $supportedSites) {
		// get the supported sites
		$supportedSitesCount = count($supportedSites);
		
		// if more than 1 supported sites and propagation method is not PROPAGATION_METHOD_NONE
		// then save the neo structures for each site.
		return $supportedSitesCount > 1 && $field->propagationMethod !== Field::PROPAGATION_METHOD_NONE;
	}
    
	private function _deleteNeoBlocksAndStructures(Field $field, ElementInterface $owner, $except, $sId = null) {
		
		$siteId = $sId ?? $owner->siteId;
		
		/** @var Element $owner */
		$deleteBlocks = Block::find()
			->anyStatus()
			->ownerId($owner->id)
			->fieldId($field->id)
			->siteId($siteId)
			->inReverse()
			->andWhere(['not', ['elements.id' => $except]])
			->all();
		
		$elementsService = Craft::$app->getElements();
		
		foreach ($deleteBlocks as $deleteBlock) {
			$deleteBlock->forgetCollapsed();
			$elementsService->deleteElement($deleteBlock);
		}
		
		// Delete any existing block structures associated with this field/owner/site combination
		while (($blockStructure = Neo::$plugin->blocks->getStructure($field->id, $owner->id, $siteId)) !== null)
		{
			Neo::$plugin->blocks->deleteStructure($blockStructure);
		}
	}
    
	private function _saveNeoStructuresForSites(Field $field, ElementInterface $owner, $blocks, $sId = null) {
		$siteId = $sId ?? $owner->siteId;
		$blockStructure = new BlockStructure();
		$blockStructure->fieldId = (int)$field->id;
		$blockStructure->ownerId = (int)$owner->id;
		$blockStructure->ownerSiteId = (int)$siteId;
		
		Neo::$plugin->blocks->saveStructure($blockStructure);
		Neo::$plugin->blocks->buildStructure($blocks, $blockStructure);
	}
}
