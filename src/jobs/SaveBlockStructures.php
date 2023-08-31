<?php

namespace benf\neo\jobs;

use benf\neo\models\BlockStructure;
use benf\neo\Plugin as Neo;
use craft\i18n\Translation;
use craft\queue\BaseJob;

/**
 * Class SaveBlockStructures
 *
 * @package benf\neo\jobs
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.0.0
 */
class SaveBlockStructures extends BaseJob
{
    /**
     * @var int
     */
    public int $fieldId;

    /**
     * @var int
     */
    public int $ownerId;

    /**
     * @var int
     */
    public int $siteId;

    /**
     * @var int[]
     */
    public array $otherSupportedSiteIds;

    /**
     * @var array of blocks' `id`, `lft`, `rgt`, `level`
     */
    public array $blocks;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $blocks = [];

        // Delete any existing block structures associated with this field/owner/site combination
        $blockStructures = Neo::$plugin->blocks->getStructures([
            'fieldId' => $this->fieldId,
            'ownerId' => $this->ownerId,
            'siteId' => $this->siteId,
        ]);
        foreach ($blockStructures as $blockStructure) {
            Neo::$plugin->blocks->deleteStructure($blockStructure);
        }

        $this->setProgress($queue, 0.3);

        foreach ($this->blocks as $b) {
            $neoBlock = Neo::$plugin->blocks->getBlockById($b['id'], $this->siteId, ['trashed' => null]);

            if ($neoBlock) {
                $neoBlock->lft = (int)$b['lft'];
                $neoBlock->rgt = (int)$b['rgt'];
                $neoBlock->level = (int)$b['level'];

                $blocks[] = $neoBlock;
            }
        }

        $this->setProgress($queue, 0.6);

        if (!empty($blocks)) {
            $blockStructure = new BlockStructure();
            $blockStructure->fieldId = $this->fieldId;
            $blockStructure->ownerId = $this->ownerId;
            $blockStructure->siteId = $this->siteId;

            Neo::$plugin->blocks->saveStructure($blockStructure);
            Neo::$plugin->blocks->buildStructure($blocks, $blockStructure);

            // Now do the other supported sites
            foreach ($this->otherSupportedSiteIds as $siteId) {
                $otherBlockStructures = Neo::$plugin->blocks->getStructures([
                    'fieldId' => $this->fieldId,
                    'ownerId' => $this->ownerId,
                    'siteId' => $siteId,
                ]);
                foreach ($otherBlockStructures as $otherBlockStructure) {
                    Neo::$plugin->blocks->deleteStructure($otherBlockStructure);
                }

                $multiBlockStructure = $blockStructure;
                $multiBlockStructure->id = null;
                $multiBlockStructure->siteId = $siteId;

                Neo::$plugin->blocks->saveStructure($multiBlockStructure);
            }
        }

        $this->setProgress($queue, 1);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Translation::prep('neo', 'Saving Neo block structures for duplicated elements');
    }
}
