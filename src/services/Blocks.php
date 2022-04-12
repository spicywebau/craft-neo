<?php

namespace benf\neo\services;

use benf\neo\elements\Block;
use benf\neo\models\BlockStructure;
use benf\neo\Plugin as Neo;
use benf\neo\records\BlockStructure as BlockStructureRecord;
use Craft;
use craft\db\Query;
use craft\fieldlayoutelements\CustomField;
use craft\models\Structure;
use yii\base\Component;

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
    public function getBlockById(int $blockId, ?int $siteId = null): ?Block
    {
        return Craft::$app->getElements()->getElementById($blockId, Block::class, $siteId);
    }

    /**
     * Renders a Neo block's tabs.
     *
     * @param Block $block The Neo block having its tabs rendered.
     * @param string|null $namespace
     * @throws
     * @return array The tabs data.
     */
    public function renderTabs(Block $block, ?string $namespace = null): array
    {
        $view = Craft::$app->getView();
        $blockType = $block->getType();
        $field = $blockType->getField();

        $namespace = $namespace ?? $view->namespaceInputName($field->handle);
        $oldNamespace = $view->getNamespace();
        $newNamespace = $namespace . '[blocks][__NEOBLOCK__]';
        $view->setNamespace($newNamespace);

        // Ensure that this block is actually new, and not just a pasted or cloned block
        // New blocks won't have their levels set at this stage, whereas they will be set for pasted/cloned blocks
        $isNewBlock = $block->id === null && $block->level === null;

        $fieldLayout = $blockType->getFieldLayout();
        $tabsHtml = [];

        foreach ($fieldLayout->getTabs() as $tab) {

            $tabHtml = [
                'name' => Craft::t('neo', $tab->name),
                'bodyHtml' => '',
                'footHtml' => '',
                'errors' => [],
            ];

            $elements = $tab->elements;
            $fieldsHtml = [];
            $view->startJsBuffer();

            foreach ($elements as $tabElement) {
                if ($tabElement instanceof CustomField && $isNewBlock) {
                    $tabElement->getField()->setIsFresh(true);
                }

                $fieldsHtml[] = $tabElement->formHtml($block);

                if ($tabElement instanceof CustomField && $isNewBlock) {
                    // Reset $_isFresh's
                    $tabElement->getField()->setIsFresh(null);
                }
            }

            $tabHtml['bodyHtml'] = $view->namespaceInputs(implode('', $fieldsHtml));
            $tabHtml['footHtml'] = $view->clearJsBuffer();

            $tabsHtml[] = $tabHtml;
        }

        $view->setNamespace($oldNamespace);

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
    public function getStructure(int $fieldId, int $ownerId, ?int $siteId = null): ?BlockStructure
    {
        $blockStructure = null;

        $query = $this->_createStructureQuery()
            ->where([
                'fieldId' => $fieldId,
                'ownerId' => $ownerId,
                'ownerSiteId' => $siteId,
            ]);

        $result = $query->one();

        if ($result) {
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
    public function getStructureById(int $id): ?BlockStructure
    {
        $blockStructure = null;

        $result = $this->_createStructureQuery()
            ->where(['id' => $id])
            ->one();

        if ($result) {
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
    public function saveStructure(BlockStructure $blockStructure): void
    {
        $dbService = Craft::$app->getDb();
        $structuresService = Craft::$app->getStructures();
        $fieldMaxLevels = Craft::$app->getFields()->getFieldById($blockStructure->fieldId)->maxLevels ?: null;
        $record = new BlockStructureRecord();

        $transaction = $dbService->beginTransaction();
        try {
            $this->deleteStructure($blockStructure);

            $structure = $blockStructure->getStructure();

            if (!$structure) {
                $structure = new Structure();
                $structure->maxLevels = $fieldMaxLevels;
                $structuresService->saveStructure($structure);
                $blockStructure->structureId = $structure->id;
            } elseif ($structure->maxLevels !== $fieldMaxLevels) {
                $structure->maxLevels = $fieldMaxLevels;
                $structuresService->saveStructure($structure);
            }

            $record->structureId = (int)$blockStructure->structureId;
            $record->ownerId = (int)$blockStructure->ownerId;
            // can't be 0 need to be at least the primary site.
            $record->ownerSiteId = (int)$blockStructure->ownerSiteId === 0 ? Craft::$app->getSites()->getPrimarySite()->id : (int)$blockStructure->ownerSiteId;
            $record->fieldId = (int)$blockStructure->fieldId;

            $record->save(false);

            $blockStructure->id = $record->id;

            $transaction->commit();
        } catch (\Throwable $e) {
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

        if ($blockStructure->id) {
            $transaction = $dbService->beginTransaction();
            try {
                if ($blockStructure->structureId) {
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
            } catch (\Throwable $e) {
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

        if ($structure) {
            $transaction = $dbService->beginTransaction();
            try {
                // Build the block structure by mapping block sort orders and levels to parent/child relationships
                $parentStack = [];

                foreach ($blocks as $block) {
                    // Remove parent blocks until either empty or a parent block is only one level below this one (meaning
                    // it'll be the parent of this block)
                    while (!empty($parentStack) && $block->level <= $parentStack[count($parentStack) - 1]->level) {
                        array_pop($parentStack);
                    }

                    if (empty($parentStack)) {
                        // If there are no blocks in our stack, it must be a root level block
                        $structuresService->appendToRoot($structure->id, $block);
                    } else {
                        // Otherwise, the block at the top of the stack will be the parent
                        $parentBlock = $parentStack[count($parentStack) - 1];
                        $structuresService->append($structure->id, $block, $parentBlock);
                    }

                    // The current block may potentially be a parent block as well, so save it to the stack
                    $parentStack[] = $block;
                }

                $transaction->commit();

                $success = true;
            } catch (\Throwable $e) {
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
    private function _createStructureQuery(): Query
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
