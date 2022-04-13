<?php

namespace benf\neo\tasks;

use benf\neo\models\BlockStructure;
use benf\neo\Plugin as Neo;
use Craft;
use craft\i18n\Translation;
use craft\queue\BaseJob;

class DuplicateNeoStructureTask extends BaseJob
{
    /**
     * @var int
     */
    public int $field;

    /**
     * @var array containing the `id` and `siteId` of the owner
     */
    public array $owner;

    /**
     * @var array of blocks' `id`, `lft`, `rgt`, `level`
     */
    public array $blocks;

    /**
     * @var int|null
     */
    public ?int $siteId = null;

    /**
     * @var int[]
     */
    public array $supportedSites;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $blocks = [];
        $siteId = $this->siteId ?? $this->owner['siteId'];
        // Delete any existing block structures associated with this field/owner/site combination
        while (($blockStructure = Neo::$plugin->blocks->getStructure($this->field, $this->owner['id'], (int)$siteId)) !== null) {
            Neo::$plugin->blocks->deleteStructure($blockStructure);
        }

        $this->setProgress($queue, 0.3);

        foreach ($this->blocks as $b) {
            $neoBlock = Neo::$plugin->blocks->getBlockById($b['id'], $siteId);

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
            $blockStructure->fieldId = (int)$this->field;
            $blockStructure->ownerId = (int)$this->owner['id'];
            $blockStructure->siteId = (int)$siteId;

            Neo::$plugin->blocks->saveStructure($blockStructure);
            Neo::$plugin->blocks->buildStructure($blocks, $blockStructure);

            if (count($this->supportedSites) > 0) {
                // if has more than 3 sites then use a job instead to lighten the load.
                foreach ($this->supportedSites as $s) {
                    while (($mBlockStructure = Neo::$plugin->blocks->getStructure($this->field, $this->owner['id'], $s)) !== null) {
                        Neo::$plugin->blocks->deleteStructure($mBlockStructure);
                    }

                    $multiBlockStructure = $blockStructure;
                    $multiBlockStructure->id = null;
                    $multiBlockStructure->siteId = $s;

                    Neo::$plugin->blocks->saveStructure($multiBlockStructure);
                }
            }
        }

        $this->setProgress($queue, 1);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Translation::prep('neo', 'Saving the Neo structure for duplicated elements');
    }
}
