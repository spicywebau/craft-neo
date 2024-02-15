<?php

namespace benf\neo\jobs;

use benf\neo\models\BlockStructure;
use benf\neo\Plugin as Neo;
use Craft;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\i18n\Translation;
use craft\queue\BaseJob;

/**
 * Class ResaveFieldBlockStructures
 *
 * @package benf\neo\jobs
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 4.0.0
 */
class ResaveFieldBlockStructures extends BaseJob
{
    /**
     * @var int
     */
    public ?int $fieldId = null;

    /**
     * @var array Optional override structure data, nested by site ID -> owner ID -> block ID
     */
    public array $structureOverrides = [];

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $elementsService = Craft::$app->getElements();
        $field = Craft::$app->getFields()->getFieldById($this->fieldId);
        $ownerIds = (new Query())
            ->select(['ownerId'])
            ->from('{{%neoblockstructures}}')
            ->where([
                'fieldId' => $this->fieldId,
            ])
            ->distinct()
            ->column();
        $ownerIdsTotal = count($ownerIds);
        $ownerIdsCounter = 0;

        foreach (array_filter($ownerIds) as $ownerId) {
            $owner = $elementsService->getElementById($ownerId, criteria: [
                'status' => null,
                'trashed' => null,
            ]);
            $supportedSiteIds = $this->_supportedSiteIds($owner);
            $blocks = [];

            // Get the blocks with the existing structure data first
            foreach ($supportedSiteIds as $siteId) {
                $blockQuery = clone $owner->getFieldValue($field->handle);
                $blocks[$siteId] = $blockQuery
                    ->status(null)
                    ->siteId($siteId)
                    ->all();

                // Ensure any passed-in structure data is prioritised
                foreach ($blocks[$siteId] as $block) {
                    $overrideStructureData = $this->structureOverrides[$siteId][$ownerId][$block->id] ?? null;

                    if ($overrideStructureData !== null) {
                        $block->level = $overrideStructureData['level'];
                        $block->lft = $overrideStructureData['lft'];
                        $block->rgt = $overrideStructureData['rgt'];
                    }
                }
            }

            // Now it's safe to recreate the block structures
            foreach ($supportedSiteIds as $siteId) {
                $oldBlockStructures = Neo::$plugin->blocks->getStructures([
                    'fieldId' => $this->fieldId,
                    'ownerId' => $ownerId,
                    'siteId' => $siteId,
                ]);

                foreach ($oldBlockStructures as $oldBlockStructure) {
                    Neo::$plugin->blocks->deleteStructure($oldBlockStructure, true);
                }

                $blockStructure = new BlockStructure();
                $blockStructure->fieldId = $this->fieldId;
                $blockStructure->ownerId = $ownerId;
                $blockStructure->siteId = $siteId;
                Neo::$plugin->blocks->saveStructure($blockStructure);
                Neo::$plugin->blocks->buildStructure($blocks[$siteId], $blockStructure);
            }

            $this->setProgress($queue, ++$ownerIdsCounter / $ownerIdsTotal);
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Translation::prep('neo', 'Saving Neo block structures for duplicated elements');
    }

    private function _supportedSiteIds(ElementInterface $element): array
    {
        return ArrayHelper::getColumn(
            ElementHelper::supportedSitesForElement($element),
            'siteId'
        );
    }
}
