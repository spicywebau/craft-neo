<?php

namespace benf\neo\services;

use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;
use benf\neo\Field;
use benf\neo\helpers\Memoize;
use benf\neo\jobs\SaveBlockStructures;
use benf\neo\models\BlockStructure;
use benf\neo\models\BlockTypeGroup;
use benf\neo\Plugin as Neo;
use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\services\Structures;
use Illuminate\Support\Collection;
use yii\base\Component;
use yii\base\InvalidArgumentException;

/**
 * Class Fields
 *
 * @package benf\neo\services
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Fields extends Component
{
    /**
     * @var bool
     * @since 2.7.1.1
     */
    private bool $_rebuildIfDeleted = false;

    /**
     * @var bool[]
     * @since 2.10.0
     */
    private array $_searchableBlockTypes = [];

    /**
     * Performs validation on a Neo field.
     *
     * @param Field $field The field to validate.
     * @return bool Whether validation was successful.
     */
    public function validate(Field $field): bool
    {
        $allBlockTypesValid = true;
        $anyBlockTypesAtTopLevel = false;
        $handles = [];

        foreach ($field->getBlockTypes() as $blockType) {
            $isBlockTypeValid = Neo::$plugin->blockTypes->validate($blockType, false);
            $allBlockTypesValid = $allBlockTypesValid && $isBlockTypeValid;
            $anyBlockTypesAtTopLevel = $anyBlockTypesAtTopLevel || $blockType->topLevel;

            if (isset($handles[$blockType->handle])) {
                $blockType->addError('handle', Craft::t('neo', "{label} \"{value}\" has already been taken.", [
                    'label' => $blockType->getAttributeLabel('handle'),
                    'value' => Html::encode($blockType->handle),
                ]));

                $allBlockTypesValid = false;
            } else {
                $handles[$blockType->handle] = true;
            }
        }

        if (!$anyBlockTypesAtTopLevel) {
            $field->addError('blockTypes', Craft::t('neo', 'Neo fields must have at least one block type allowed at the top level.'));
        }

        return $allBlockTypesValid && $anyBlockTypesAtTopLevel;
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

        if ($isValid && !Craft::$app->getProjectConfig()->getIsApplyingExternalChanges()) {
            $transaction = $dbService->beginTransaction();
            try {
                // Delete the old block types first, in case there's a handle conflict with one of the new ones
                $oldBlockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);
                $oldBlockTypesById = [];

                foreach ($oldBlockTypes as $blockType) {
                    $oldBlockTypesById[$blockType->id] = $blockType;
                }

                foreach ($field->getBlockTypes() as $blockType) {
                    if (!$blockType->getIsNew()) {
                        unset($oldBlockTypesById[$blockType->id]);
                    }
                }

                foreach ($oldBlockTypesById as $blockType) {
                    Neo::$plugin->blockTypes->delete($blockType);
                }

                // Delete any old groups that were removed
                $oldGroups = Neo::$plugin->blockTypes->getGroupsByFieldId($field->id);
                $oldGroupsById = [];

                foreach ($oldGroups as $blockTypeGroup) {
                    $oldGroupsById[$blockTypeGroup->id] = $blockTypeGroup;
                }

                foreach ($field->getGroups() as $blockTypeGroup) {
                    if (!$blockTypeGroup->getIsNew()) {
                        unset($oldGroupsById[$blockTypeGroup->id]);
                    }
                }

                foreach ($oldGroupsById as $blockTypeGroup) {
                    Neo::$plugin->blockTypes->deleteGroup($blockTypeGroup);
                }

                // Save the new block types and groups
                $currentGroup = null;

                foreach ($field->getItems() as $item) {
                    $item->fieldId = $field->id;

                    if ($item instanceof BlockTypeGroup) {
                        $currentGroup = $item;
                        Neo::$plugin->blockTypes->saveGroup($item);
                    } else {
                        $item->groupId = $currentGroup?->id;
                        Neo::$plugin->blockTypes->save($item, false);
                    }
                }

                $transaction->commit();

                Memoize::$blockTypesByFieldId[$field->id] = $field->getBlockTypes();
                Memoize::$blockTypeGroupsByFieldId[$field->id] = $field->getGroups();
            } catch (\Throwable $e) {
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
        try {
            $blockTypes = Neo::$plugin->blockTypes->getByFieldId($field->id);

            // sort block types so the sort order is descending
            // need to reverse to multi level blocks get deleted before the parent
            usort($blockTypes, fn($a, $b) => $b->sortOrder <=> $a->sortOrder);

            foreach ($blockTypes as $blockType) {
                Neo::$plugin->blockTypes->delete($blockType);
            }

            Neo::$plugin->blockTypes->deleteGroupsByFieldId($field->id);

            $transaction->commit();
        } catch (\Throwable $e) {
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
    public function saveValue(Field $field, ElementInterface $owner): void
    {
        $dbService = Craft::$app->getDb();
        $draftsService = Craft::$app->getDrafts();
        $elementsService = Craft::$app->getElements();
        $neoSettings = Neo::$plugin->getSettings();

        $this->_rebuildIfDeleted = false;
        $value = $owner->getFieldValue($field->handle);

        if ($value instanceof Collection) {
            $blocks = $value->all();
            $saveAll = true;
        } else {
            $blocks = $value->getCachedResult();
            if ($blocks !== null) {
                $saveAll = false;
            } else {
                $blocks = (clone $value)->status(null)->all();
                $saveAll = true;
            }
        }

        $blockIds = [];
        $sortOrder = 0;
        $structureModified = false;

        $transaction = $dbService->beginTransaction();

        try {
            foreach ($blocks as $block) {
                $sortOrder++;
                if ($saveAll || !$block->id || $block->dirty) {
                    // Check if the sortOrder has changed and we need to resave the block structure
                    if ((int)$block->sortOrder !== $sortOrder) {
                        $structureModified = true;
                    }

                    $block->setOwner($owner);
                    // If the block already has an ID and primary owner ID, don't reassign it
                    if (!$block->id || !$block->primaryOwnerId) {
                        $block->primaryOwnerId = $owner->id;
                    }
                    $block->sortOrder = $sortOrder;
                    $elementsService->saveElement($block, false, true, $this->_hasSearchableBlockType($field, $block));

                    if (!$neoSettings->collapseAllBlocks) {
                        $block->cacheCollapsed();
                    }

                    // If this is a draft, we can shed the draft data now
                    if ($block->getIsDraft()) {
                        $canonicalBlockId = $block->getCanonicalId();
                        $draftsService->removeDraftData($block);
                        Db::delete('{{%neoblocks_owners}}', [
                            'blockId' => $canonicalBlockId,
                            'ownerId' => $owner->id,
                        ]);
                    }
                } elseif ((int)$block->sortOrder !== $sortOrder) {
                    // Just update its sortOrder
                    $block->sortOrder = $sortOrder;
                    Db::update('{{%neoblocks_owners}}', [
                        'sortOrder' => $sortOrder,
                    ], [
                        'blockId' => $block->id,
                        'ownerId' => $owner->id,
                    ], [], false);

                    $structureModified = true;
                }

                // check if block level has been changed
                if ((!$structureModified && $block->level !== (int)$block->oldLevel) || !$block->structureId || !$block->id) {
                    $structureModified = true;
                }

                $blockIds[] = $block->id;
            }

            // Check if any different block IDs are in the structure compared with what we just saved, which could
            // happen e.g. when saving a provisional draft
            $structureId = (new Query())
                ->select(['structureId'])
                ->from(['{{%neoblockstructures}}'])
                ->where([
                    'fieldId' => $field->id,
                    'ownerId' => $owner->id,
                    'siteId' => $owner->siteId,
                ])
                ->scalar();

            if ($structureId) {
                $oldStructureBlocks = (new Query())
                    ->select(['elementId'])
                    ->from(Table::STRUCTUREELEMENTS)
                    ->where(['structureId' => $structureId])
                    ->andWhere('[[elementId]] IS NOT NULL')
                    ->column();
                $structureBlocksChanged = !empty(array_diff($oldStructureBlocks, $blockIds));
            } else {
                // Fall back to what `$this->_rebuildIfDeleted` ends up being
                $structureBlocksChanged = false;
            }

            $this->_deleteOtherBlocks($field, $owner, $blockIds);

            // We need to check if the blocks are different e.g any deletions so we can rebuild the structure
            if ($this->_rebuildIfDeleted || $structureBlocksChanged) {
                $structureModified = true;
            }

            if ($structureModified) {
                $this->_saveNeoStructuresForSites($field, $owner, $blocks);
            }

            if (
                $field->propagationMethod !== Field::PROPAGATION_METHOD_ALL &&
                ($owner->propagateAll || !empty($owner->newSiteIds))
            ) {
                $ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
                $fieldSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $owner, $field->propagationKeyFormat);
                $otherSiteIds = array_diff($ownerSiteIds, $fieldSiteIds);

                // If propagateAll isn't set, only deal with sites that the element was just propagated to for the first time
                if (!$owner->propagateAll) {
                    $preexistingOtherSiteIds = array_diff($otherSiteIds, $owner->newSiteIds);
                    $otherSiteIds = array_intersect($otherSiteIds, $owner->newSiteIds);
                } else {
                    $preexistingOtherSiteIds = [];
                }

                if (!empty($otherSiteIds)) {
                    // Get the owner element across each of those sites
                    $localizedOwners = $owner::find()
                        ->drafts($owner->getIsDraft())
                        ->provisionalDrafts($owner->isProvisionalDraft)
                        ->revisions($owner->getIsRevision())
                        ->id($owner->id)
                        ->siteId($otherSiteIds)
                        ->status(null)
                        ->all();

                    // Duplicate Neo blocks, ensuring we don't process the same blocks more than once
                    $handledSiteIds = [];

                    if ($value instanceof BlockQuery) {
                        $cachedQuery = (clone $value)->status(null);
                        $cachedQuery->setCachedResult($blocks);
                        $owner->setFieldValue($field->handle, $cachedQuery);
                    }

                    foreach ($localizedOwners as $localizedOwner) {
                        // Make sure we haven't already duplicated blocks for this site, via propagation from another site
                        if (isset($handledSiteIds[$localizedOwner->siteId])) {
                            continue;
                        }

                        // Find all of the field’s supported sites shared with this target
                        $sourceSupportedSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $localizedOwner, $field->propagationKeyFormat);

                        // Do blocks in this target happen to share supported sites with a preexisting site?
                        if (
                            !empty($preexistingOtherSiteIds) &&
                            !empty($sharedPreexistingOtherSiteIds = array_intersect($preexistingOtherSiteIds, $sourceSupportedSiteIds)) &&
                            $preexistingLocalizedOwner = $owner::find()
                                ->drafts($owner->getIsDraft())
                                ->provisionalDrafts($owner->isProvisionalDraft)
                                ->revisions($owner->getIsRevision())
                                ->id($owner->id)
                                ->siteId($sharedPreexistingOtherSiteIds)
                                ->status(null)
                                ->one()
                        ) {
                            // Just resave Neo blocks for that one site, and let them propagate over to the new site(s) from there
                            $this->saveValue($field, $preexistingLocalizedOwner);
                        } else {
                            // Duplicate the blocks, but **don't track** the duplications, so the edit page doesn’t think
                            // its blocks have been replaced by the other sites’ blocks
                            $this->duplicateBlocks($field, $owner, $localizedOwner, trackDuplications: false);
                        }

                        // Make sure we don't duplicate blocks for any of the sites that were just propagated to
                        $handledSiteIds = array_merge($handledSiteIds, array_flip($sourceSupportedSiteIds));
                    }

                    if ($value instanceof BlockQuery) {
                        $owner->setFieldValue($field->handle, $value);
                    }
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
     * @param bool $deleteOtherBlocks Whether to delete any blocks that belong to the element, which weren't included in the duplication
     * @param bool $trackDuplications whether to keep track of the duplications from [[\craft\services\Elements::$duplicatedElementIds]]
     * and [[\craft\services\Elements::$duplicatedElementSourceIds]]
     * @throws
     */
    public function duplicateBlocks(
        Field $field,
        ElementInterface $source,
        ElementInterface $target,
        bool $checkOtherSites = false,
        bool $deleteOtherBlocks = true,
        bool $trackDuplications = true,
    ): void {
        $elementsService = Craft::$app->getElements();
        $value = $source->getFieldValue($field->handle);

        if ($value instanceof Collection) {
            $blocks = $value->all();
        } else {
            $blocks = $value->getCachedResult() ?? (clone $value)->status(null)->all();
        }

        $newBlockIds = [];
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $newBlocks = [];
            $newBlocksTaskData = [];

            foreach ($blocks as $block) {
                // Temporarily remove the `structureId`, otherwise `updateCanonicalElement()` won't update the correct block
                $oldStructureId = $block->structureId;
                $block->structureId = null;
                $collapsed = $block->getCollapsed();
                $newBlock = null;
                $newAttributes = [
                    'canonicalId' => $target->getIsDerivative() ? $block->id : null,
                    'primaryOwnerId' => $target->id,
                    'owner' => $target,
                    'siteId' => $target->siteId,
                    'structureId' => null,
                    'propagating' => false,
                ];

                if ($target->updatingFromDerivative && $block->getIsDerivative()) {
                    if (
                        ElementHelper::isRevision($source) ||
                        !empty($target->newSiteIds) ||
                        (!$source::trackChanges() || $source->isFieldModified($field->handle, true))
                    ) {
                        $newBlock = $elementsService->updateCanonicalElement($block, $newAttributes);
                        $alreadyHasOwnerRow = (new Query())
                            ->from(['{{%neoblocks_owners}}'])
                            ->where([
                                'blockId' => $newBlock->id,
                                'ownerId' => $target->id,
                            ])
                            ->exists();

                        if (!$alreadyHasOwnerRow) {
                            Db::insert('{{%neoblocks_owners}}', [
                                'blockId' => $newBlock->id,
                                'ownerId' => $target->id,
                                'sortOrder' => $newBlock->sortOrder,
                            ]);
                        }
                    } else {
                        $newBlock = $block->getCanonical();

                        if ($newBlock->trashed && !$block->trashed) {
                            $newBlock->trashed = false;
                        }
                    }
                } elseif ($block->primaryOwnerId === $target->id && $source->id !== $target->id) {
                    // Only the block ownership was duplicated, so just update its sort order for the target element
                    Db::update('{{%neoblocks_owners}}', [
                        'sortOrder' => $block->sortOrder,
                    ], ['blockId' => $block->id, 'ownerId' => $target->id], updateTimestamp: false);
                    $newBlock = $block;
                } else {
                    $newBlock = $elementsService->duplicateElement($block, $newAttributes, true, $trackDuplications);
                }

                $newBlockIds[] = $newBlock->id;
                $block->structureId = $oldStructureId;

                // Levels not applying properly when saving drafts, so do it manually
                $newBlock->level = $block->level;

                $newBlock->setCollapsed($collapsed);
                $newBlock->cacheCollapsed();

                $newBlocksTaskData[] = [
                    'id' => $newBlock->id,
                    'sortOrder' => $newBlock->sortOrder,
                    'lft' => $newBlock->lft,
                    'rgt' => $newBlock->rgt,
                    'level' => $newBlock->level,
                ];
                $newBlocks[] = $newBlock;
            }
            // Delete any blocks that shouldn't be there anymore
            if ($deleteOtherBlocks) {
                $this->_deleteOtherBlocks($field, $target, $newBlockIds);
            }

            $this->_saveNeoStructuresForSites($field, $target, $newBlocks);

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
        // Duplicate blocks for other sites as well?
        if ($checkOtherSites && $field->propagationMethod !== Field::PROPAGATION_METHOD_ALL) {
            // Find the target's site IDs that *aren't* supported by this site's Neo blocks
            $targetSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($target), 'siteId');
            $fieldSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $target, $field->propagationKeyFormat);
            $otherSiteIds = array_diff($targetSiteIds, $fieldSiteIds);

            if (!empty($otherSiteIds)) {
                // Get the original element and duplicated element for each of those sites
                /** @var Element[] $otherSources */
                $otherSources = $target::find()
                    ->drafts($source->getIsDraft())
                    ->provisionalDrafts($source->isProvisionalDraft)
                    ->revisions($source->getIsRevision())
                    ->id($source->id)
                    ->siteId($otherSiteIds)
                    ->status(null)
                    ->all();
                /** @var Element[] $otherTargets */
                $otherTargets = $target::find()
                    ->drafts($target->getIsDraft())
                    ->provisionalDrafts($target->isProvisionalDraft)
                    ->revisions($target->getIsRevision())
                    ->id($target->id)
                    ->siteId($otherSiteIds)
                    ->status(null)
                    ->indexBy('siteId')
                    ->all();

                // Duplicate Neo blocks, ensuring we don't process the same blocks more than once
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

                    $otherTargets[$otherSource->siteId]->updatingFromDerivative = $target->updatingFromDerivative;
                    $this->duplicateBlocks($field, $otherSource, $otherTargets[$otherSource->siteId]);

                    // Make sure we don't duplicate blocks for any of the sites that were just propagated to
                    $sourceSupportedSiteIds = $this->getSupportedSiteIds($field->propagationMethod, $otherSource, $field->propagationKeyFormat);
                    $handledSiteIds = array_merge($handledSiteIds, $sourceSupportedSiteIds);
                }
            }
        }
    }

    /**
     * Duplicates block ownership relations for a new draft element.
     *
     * @param Field $field The Neo field
     * @param ElementInterface $canonical The canonical element
     * @param ElementInterface $draft The draft element
     * @since 3.0.0
     * @see \craft\services\Matrix::duplicateOwnership()
     */
    public function duplicateOwnership(Field $field, ElementInterface $canonical, ElementInterface $draft): void
    {
        if (!$canonical->getIsCanonical()) {
            throw new InvalidArgumentException('The source element must be canonical.');
        }

        if (!$draft->getIsDraft()) {
            throw new InvalidArgumentException('The target element must be a draft.');
        }

        $blocksTable = '{{%neoblocks}}';
        $ownersTable = '{{%neoblocks_owners}}';

        // Duplicate into the owners table
        Craft::$app->getDb()->createCommand(<<<SQL
INSERT INTO $ownersTable ([[blockId]], [[ownerId]], [[sortOrder]]) 
SELECT [[o.blockId]], '$draft->id', [[o.sortOrder]] 
FROM $ownersTable AS [[o]]
INNER JOIN $blocksTable AS [[b]] ON [[b.id]] = [[o.blockId]] AND [[b.primaryOwnerId]] = '$canonical->id' AND [[b.fieldId]] = '$field->id'
WHERE [[o.ownerId]] = '$canonical->id'
SQL
        )->execute();

        // Save the block structures for the draft
        foreach (ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($draft), 'siteId') as $siteId) {
            $blocks = Block::find()
                ->ownerId($canonical->id)
                ->fieldId($field->id)
                ->siteId($siteId)
                ->structureId(
                    (new Query())
                        ->select(['structureId'])
                        ->from(['{{%neoblockstructures}}'])
                        ->where([
                            'fieldId' => $field->id,
                            'ownerId' => $canonical->id,
                            'siteId' => $siteId,
                        ])
                        ->scalar()
                )
                ->unique()
                ->status(null)
                ->all();
            // Opt out of creating structures for other supported sites - we'll be doing that from here if necessary
            $this->_saveNeoStructuresForSites($field, $draft, $blocks, $siteId, false);
        }
    }

    /**
     * Creates revisions for all the blocks that belong to the given canonical element, and assigns those
     * revisions to the given owner revision.
     *
     * @param Field $field The Neo field
     * @param ElementInterface $canonical The canonical element
     * @param ElementInterface $revision The revision element
     * @since 3.0.0
     * @see \craft\services\Matrix::createRevisionBlocks()
     */
    public function createRevisionBlocks(Field $field, ElementInterface $canonical, ElementInterface $revision): void
    {
        $supportedSiteIds = fn($element) => ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($element), 'siteId');
        $blocks = [];

        foreach ($supportedSiteIds($canonical) as $siteId) {
            $blocks[$siteId] = Block::find()
                ->ownerId($canonical->id)
                ->fieldId($field->id)
                ->siteId($siteId)
                ->structureId($this->_getStructureId($field, $canonical, $siteId))
                ->unique()
                ->status(null)
                ->all();
        }

        $revisionsService = Craft::$app->getRevisions();
        $queue = Craft::$app->getConfig()->getGeneral()->runQueueAutomatically
            ? Craft::$app->getQueue()
            : null;
        $ownershipData = [];
        $jobData = [];
        $nonJobData = [];
        $processedSites = [];
        $otherSites = [];

        foreach ($blocks as $siteId => $siteBlocks) {
            // Don't bother if we've already processed the blocks for this site
            if (isset($processedSites[$siteId])) {
                continue;
            }

            $supportedSites = null;

            foreach ($siteBlocks as $block) {
                if ($supportedSites === null) {
                    $supportedSites = $supportedSiteIds($block);
                }

                // Don't bother if the block doesn't have a supported site, likely the owner is disabled for the site now
                if (!in_array($block->siteId, $supportedSites)) {
                    continue;
                }

                $blockRevisionId = $revisionsService->createRevision($block, null, null, [
                    'primaryOwnerId' => $revision->id,
                    'saveOwnership' => false,
                ]);
                $ownershipData[] = [$blockRevisionId, $revision->id, $block->sortOrder];

                if ($queue !== null) {
                    // Store the job data for block structure creation
                    $jobData[$siteId][] = [
                        'id' => $blockRevisionId,
                        'level' => $block->level,
                        'lft' => $block->lft,
                        'rgt' => $block->rgt,
                    ];
                } else {
                    // Because querying for blocks with the revision IDs doesn't work yet...
                    // I wish `$revisionsService->createRevision()` returned the revision element and not just the ID
                    $cloneBlock = clone $block;
                    $cloneBlock->id = $blockRevisionId;
                    $cloneBlock->ownerId = $revision->id;
                    $cloneBlock->structureId = null;
                    $nonJobData[$siteId][] = $cloneBlock;
                }
            }

            foreach ($supportedSites ?? [] as $supportedSite) {
                $processedSites[$supportedSite] = true;
            }

            $otherSites[$siteId] = $supportedSites;
        }

        Db::batchInsert('{{%neoblocks_owners}}', ['blockId', 'ownerId', 'sortOrder'], $ownershipData);

        foreach ($jobData as $siteId => $data) {
            $queue->push(new SaveBlockStructures([
                'fieldId' => $field->id,
                'ownerId' => $revision->id,
                'siteId' => $siteId,
                'otherSupportedSiteIds' => $otherSites[$siteId],
                'blocks' => $data,
            ]));
        }

        foreach ($nonJobData as $siteId => $siteBlocks) {
            $this->_saveNeoStructuresForSites($field, $revision, $nonJobData[$siteId], $siteId);
        }
    }

    /**
     * Merges recent canonical Neo block changes into the given Neo field’s blocks.
     *
     * @param Field $field The Neo field
     * @param ElementInterface $owner The element the field is associated with
     * @return void
     * @since 2.11.0
     * @see \craft\services\Matrix::mergeCanonicalChanges()
     */
    public function mergeCanonicalChanges(Field $field, ElementInterface $owner): void
    {
        $localizedOwners = $owner::find()
            ->id($owner->id ?: false)
            ->siteId(['not', $owner->siteId])
            ->drafts($owner->getIsDraft())
            ->provisionalDrafts($owner->isProvisionalDraft)
            ->revisions($owner->getIsRevision())
            ->status(null)
            ->ignorePlaceholders()
            ->indexBy('siteId')
            ->all();
        $localizedOwners[$owner->siteId] = $owner;

        $canonicalOwners = $owner::find()
            ->id($owner->getCanonicalId())
            ->siteId(array_keys($localizedOwners))
            ->status(null)
            ->ignorePlaceholders()
            ->all();

        $elementsService = Craft::$app->getElements();
        $structuresService = Craft::$app->getStructures();
        $handledSiteIds = [];

        foreach ($canonicalOwners as $canonicalOwner) {
            if (isset($handledSiteIds[$canonicalOwner->siteId])) {
                continue;
            }

            $allBlocks = [];
            $newBlocks = [];
            $nextBlockSortOrder = 1;

            $canonicalBlocks = Block::find()
                ->fieldId($field->id)
                ->primaryOwnerId($canonicalOwner->id)
                ->siteId($canonicalOwner->siteId)
                ->status(null)
                ->trashed(null)
                ->ignorePlaceholders()
                ->indexBy('id')
                ->orderBy(['lft' => SORT_ASC])
                ->all();

            $derivativeBlocks = Block::find()
                ->fieldId($field->id)
                ->ownerId($owner->id)
                ->siteId($canonicalOwner->siteId)
                ->status(null)
                ->trashed(null)
                ->andWhere(['not', ['structureId' => null]])
                ->ignorePlaceholders()
                ->indexBy('canonicalId')
                ->all();

            $derivativeStructureId = (new Query())
                ->select(['structureId'])
                ->from(['{{%neoblockstructures}}'])
                ->where([
                    'fieldId' => $field->id,
                    'ownerId' => $owner->id,
                    'siteId' => $canonicalOwner->siteId,
                ])
                ->scalar();

            foreach ($canonicalBlocks as $canonicalBlock) {
                $newBlock = null;
                $structureMode = null;

                if (isset($derivativeBlocks[$canonicalBlock->id])) {
                    $derivativeBlock = $derivativeBlocks[$canonicalBlock->id];

                    if ($canonicalBlock->trashed) {
                        if ($derivativeBlock->dateUpdated == $derivativeBlock->dateCreated) {
                            $elementsService->deleteElement($derivativeBlock);
                        }
                    } elseif (!$derivativeBlock->trashed) {
                        if (ElementHelper::isOutdated($derivativeBlock)) {
                            if (!$owner->isProvisionalDraft && $derivativeBlock->sortOrder != $nextBlockSortOrder) {
                                $derivativeBlock->sortOrder = $nextBlockSortOrder;
                                $structureMode = Structures::MODE_AUTO;
                            }

                            $elementsService->mergeCanonicalChanges($derivativeBlock);
                            $allBlocks[] = $newBlock = $derivativeBlock;
                        } else {
                            $allBlocks[] = $derivativeBlock;
                        }
                    }
                } elseif (!$canonicalBlock->trashed && $canonicalBlock->dateCreated > $owner->dateCreated) {
                    $allBlocks[] = $newBlock = $elementsService->duplicateElement($canonicalBlock, [
                        'canonicalId' => $canonicalBlock->id,
                        'level' => $canonicalBlock->level,
                        'primaryOwnerId' => $owner->id,
                        'owner' => $localizedOwners[$canonicalBlock->siteId],
                        'propagating' => false,
                        'siteId' => $canonicalBlock->siteId,
                        'structureId' => null,
                    ]);
                    $structureMode = Structures::MODE_INSERT;
                }

                if ($derivativeStructureId && $structureMode !== null) {
                    if (count($allBlocks) > 1) {
                        $prevBlock = $allBlocks[count($allBlocks) - 2];

                        // If $prevBlock->level is lower, $newBlock is the first child block and we need to prepend
                        $method = $prevBlock->level < $newBlock->level ? 'prepend' : 'moveAfter';

                        // If $prevBlock->level is higher, then $newBlock is a sibling of one of $prevBlock's ancestors,
                        // so we'll need to move $newBlock after that ancestor
                        if ($prevBlock->level > $newBlock->level) {
                            for ($i = count($allBlocks) - 3; $i >= 0; $i--) {
                                if ($allBlocks[$i]->level == $newBlock->level) {
                                    $prevBlock = $allBlocks[$i];
                                    break;
                                }
                            }
                        }

                        $structuresService->$method($derivativeStructureId, $newBlock, $prevBlock, $structureMode);
                    } else {
                        // Put it at the top
                        $structuresService->prependToRoot($derivativeStructureId, $newBlock, $structureMode);
                    }
                }

                if ($newBlock !== null) {
                    $newBlocks[] = $newBlock;
                    $nextBlockSortOrder++;
                }
            }

            if (!$derivativeStructureId && !empty($newBlocks)) {
                // No derivative structure exists, and these blocks have to go somewhere, so create one
                $this->_saveNeoStructuresForSites($field, $owner, $newBlocks, $canonicalOwner->siteId);
            }

            $siteIds = $this->getSupportedSiteIds($field->propagationMethod, $canonicalOwner, $field->propagationKeyFormat);

            foreach ($siteIds as $siteId) {
                $handledSiteIds[$siteId] = true;
            }
        }
    }

    /**
     * Returns the site IDs that are supported by Neo blocks for the given propagation method and owner element.
     *
     * @param string $propagationMethod
     * @param ElementInterface $owner
     * @param string|null $propagationKeyFormat
     * @return int[]
     * @throws
     * @since 2.5.10
     */
    public function getSupportedSiteIds(string $propagationMethod, ElementInterface $owner, ?string $propagationKeyFormat = null): array
    {
        /** @var Element $owner */
        /** @var Site[] $allSites */
        $allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(), 'id');
        $ownerSiteIds = ArrayHelper::getColumn(ElementHelper::supportedSitesForElement($owner), 'siteId');
        $siteIds = [];

        if ($propagationMethod === Field::PROPAGATION_METHOD_CUSTOM && $propagationKeyFormat !== null) {
            $view = Craft::$app->getView();
            $elementsService = Craft::$app->getElements();
            $propagationKey = $view->renderObjectTemplate($propagationKeyFormat, $owner);
        }

        foreach ($ownerSiteIds as $siteId) {
            switch ($propagationMethod) {
                case Field::PROPAGATION_METHOD_NONE:
                    $include = $siteId == $owner->siteId;
                    break;
                case Field::PROPAGATION_METHOD_SITE_GROUP:
                    $include = $allSites[$siteId]->groupId == $allSites[$owner->siteId]->groupId;
                    break;
                case Field::PROPAGATION_METHOD_LANGUAGE:
                    $include = $allSites[$siteId]->language == $allSites[$owner->siteId]->language;
                    break;
                case Field::PROPAGATION_METHOD_CUSTOM:
                    if (!isset($propagationKey)) {
                        $include = true;
                    } else {
                        $siteOwner = $elementsService->getElementById($owner->id, get_class($owner), $siteId);
                        $include = $siteOwner && $propagationKey === $view->renderObjectTemplate($propagationKeyFormat, $siteOwner);
                    }
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

    /**
     * Returns the site IDs that are supported by Neo blocks for the given propagation method and owner element.
     *
     * @param Field $field
     * @param ElementInterface $owner
     * @return int[]
     * @throws
     * @since 2.5.10
     */
    public function getSupportedSiteIdsExCurrent(Field $field, ElementInterface $owner): array
    {
        // we need to setup the structure for the other supported sites too.
        // must be immediate to show changes on the front end.
        $supported = $this->getSupportedSiteIds($field->propagationMethod, $owner, $field->propagationKeyFormat);

        // remove the current
        if (($key = array_search($owner->siteId, $supported)) !== false) {
            array_splice($supported, $key, 1);
        }

        return $supported;
    }

    public function getNeoFields(): array
    {
        return array_filter(
            Craft::$app->getFields()->getAllFields(),
            fn($field) => $field instanceof Field
        );
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
    private function _deleteOtherBlocks(Field $field, ElementInterface $owner, array $except): void
    {
        $supportedSites = $this->getSupportedSiteIds($field->propagationMethod, $owner, $field->propagationKeyFormat);
        $supportedSitesCount = count($supportedSites);

        if ($supportedSitesCount > 1 && $field->propagationMethod !== Field::PROPAGATION_METHOD_NONE) {
            foreach ($supportedSites as $site) {
                $this->_deleteNeoBlocksAndStructures($field, $owner, $except, $site);
            }
        } else {
            $this->_deleteNeoBlocksAndStructures($field, $owner, $except);
        }
    }

    /**
     * Deletes Neo blocks and block structures for a given field, owner and site.
     *
     * @param Field $field
     * @param ElementInterface $owner
     * @param int[] $except Block IDs that should be left alone
     * @param int|null $sId the site ID; if this is null, the owner's site ID will be used
     * @since 2.4.3
     */
    private function _deleteNeoBlocksAndStructures(Field $field, ElementInterface $owner, array $except, ?int $sId = null): void
    {
        $siteId = $sId ?? $owner->siteId;

        /** @var Element $owner */
        $blocks = Block::find()
            ->status(null)
            ->ownerId($owner->id)
            ->fieldId($field->id)
            ->siteId($siteId)
            ->inReverse()
            ->andWhere(['not', ['elements.id' => $except]])
            ->all();

        $elementsService = Craft::$app->getElements();
        $deleteOwnership = [];

        foreach ($blocks as $block) {
            $block->forgetCollapsed();

            if ($block->primaryOwnerId === $owner->id) {
                $elementsService->deleteElement($block);
            } else {
                // Just delete the ownership relation
                $deleteOwnership[] = $block->id;
            }
        }

        if ($deleteOwnership) {
            Db::delete('{{%neoblocks_owners}}', [
                'blockId' => $deleteOwnership,
                'ownerId' => $owner->id,
            ]);
        }

        // If there are blocks to delete, then we need to rebuild the block structure
        if (!empty($blocks)) {
            $this->_rebuildIfDeleted = true;
        }
    }

    /**
     * Saves Neo block structures for a given field, owner and site.
     *
     * @param Field $field
     * @param ElementInterface $owner
     * @param Block[] $blocks Block IDs that should be left alone
     * @param int|null $sId the site ID; if this is null, the owner's site ID will be used
     * @param bool $saveForOtherSupportedSites
     */
    private function _saveNeoStructuresForSites(
        Field $field,
        ElementInterface $owner,
        $blocks,
        ?int $sId = null,
        $saveForOtherSupportedSites = true,
    ): void {
        $siteId = $sId ?? $owner->siteId;

        // Delete any existing block structures associated with this field/owner/site combination
        $blockStructures = Neo::$plugin->blocks->getStructures([
            'fieldId' => $field->id,
            'ownerId' => $owner->id,
            'siteId' => $siteId,
        ]);
        foreach ($blockStructures as $blockStructure) {
            Neo::$plugin->blocks->deleteStructure($blockStructure, true);
        }

        $blockStructure = new BlockStructure();
        $blockStructure->fieldId = (int)$field->id;
        $blockStructure->ownerId = (int)$owner->id;
        $blockStructure->siteId = (int)$siteId;

        Neo::$plugin->blocks->saveStructure($blockStructure);
        Neo::$plugin->blocks->buildStructure($blocks, $blockStructure);

        // If this is a multi-site Craft project, these blocks have other supported sites and we haven't explicitly
        // disabled saving of structures for those other sites, then save them for those other sites here, since we've
        // already built the block structure
        if (!$saveForOtherSupportedSites) {
            return;
        }

        $supported = $this->getSupportedSiteIdsExCurrent($field, $owner, $field->propagationKeyFormat);

        foreach ($supported as $s) {
            $otherBlockStructures = Neo::$plugin->blocks->getStructures([
                'fieldId' => $field->id,
                'ownerId' => $owner->id,
                'siteId' => $s,
            ]);
            foreach ($otherBlockStructures as $otherBlockStructure) {
                Neo::$plugin->blocks->deleteStructure($otherBlockStructure);
            }

            $multiBlockStructure = $blockStructure;
            $multiBlockStructure->id = null;
            $multiBlockStructure->siteId = $s;

            Neo::$plugin->blocks->saveStructure($multiBlockStructure);
        }
    }

    /**
     * Checks whether a block should be considered searchable.
     *
     * @param Field $field
     * @param Block $block
     * @return bool
     * @throws InvalidArgumentException if $block doesn't belong to $field
     * @since 2.10.0
     */
    private function _hasSearchableBlockType(Field $field, Block $block): bool
    {
        if ($block->fieldId != $field->id) {
            throw new InvalidArgumentException('Incompatible Neo field and block');
        }

        // Just say yes if the setting is disabled
        if (!Neo::$plugin->getSettings()->optimiseSearchIndexing) {
            return true;
        }

        $typeId = $block->typeId;

        if (!isset($this->_searchableBlockTypes[$typeId])) {
            // A Neo block type should only be searchable if all of the following apply:
            // 1. the Neo field it belongs to is searchable
            // 2. it has a field layout
            // 3. the field layout has any searchable sub-fields
            $this->_searchableBlockTypes[$typeId] = $field->searchable && !empty(array_filter(
                $block->getType()->getFieldLayout()->getCustomFields(),
                fn($subField) => $subField->searchable
            ));
        }

        return $this->_searchableBlockTypes[$typeId];
    }

    /**
     * Gets a block structure ID.
     *
     * @param Field $field
     * @param ElementInterface $owner
     * @param int|null $siteId The site ID of the structure to get. If null, then $owner->siteId will be used.
     * @return int|null
     */
    private function _getStructureId(Field $field, ElementInterface $owner, ?int $siteId = null): ?int
    {
        return (new Query())
            ->select(['nbs.structureId'])
            ->from(['nbs' => '{{%neoblockstructures}}'])
            ->where([
                'ownerId' => $owner->id,
                'siteId' => $siteId ?? $owner->siteId,
                'fieldId' => $field->id,
            ])
            ->scalar();
    }
}
