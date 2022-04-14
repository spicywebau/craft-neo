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
        while (($blockStructure = Neo::$plugin->blocks->getStructure($this->fieldId, $this->ownerId, $this->siteId)) !== null) {
            Neo::$plugin->blocks->deleteStructure($blockStructure);
        }

        $this->setProgress($queue, 0.3);

        foreach ($this->blocks as $b) {
            $neoBlock = Neo::$plugin->blocks->getBlockById($b['id'], $this->siteId);

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
                while (($mBlockStructure = Neo::$plugin->blocks->getStructure($this->fieldId, $this->ownerId, $siteId)) !== null) {
                    Neo::$plugin->blocks->deleteStructure($mBlockStructure);
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
