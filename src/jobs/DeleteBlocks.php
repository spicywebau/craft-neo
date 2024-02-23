<?php

namespace benf\neo\jobs;

use benf\neo\elements\Block;
use Craft;
use craft\queue\BaseJob;

/**
 * Class DeleteBlock
 *
 * @package benf\neo\jobs
 * @author Spicy Web <plugins@spicyweb.com.au>
 */
class DeleteBlocks extends BaseJob
{
    /**
     * @var int
     */
    public $fieldId;

    /**
     * @var int
     */
    public $elementId;

    /**
     * @var bool
     */
    public $hardDelete;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $elementsService = Craft::$app->getElements();

        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $blocks = Block::find()
                ->anyStatus()
                ->fieldId($this->fieldId)
                ->siteId($siteId)
                ->ownerId($this->elementId)
                ->inReverse()
                ->all();

            foreach ($blocks as $block) {
                $block->deletedWithOwner = true;
                $elementsService->deleteElement($block, $this->hardDelete);
            }
        }

        $this->setProgress($queue, 1);
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('neo', 'Deleting old Neo blocks');
    }
}
