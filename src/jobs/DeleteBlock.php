<?php

namespace benf\neo\jobs;

use benf\neo\elements\Block;
use Craft;
use craft\db\Table;
use craft\queue\BaseJob;

/**
 * Class DeleteBlock
 *
 * @package benf\neo\jobs
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.13.16
 */
class DeleteBlock extends BaseJob
{
    /**
     * @var int
     */
    public $blockId;

    /**
     * @var int
     */
    public $siteId;

    /**
     * @var bool
     */
    public $deletedWithOwner;

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
        $block = $elementsService->getElementById($this->blockId, Block::class, $this->siteId);

        if ($block !== null) {
            $block->deletedWithOwner = $this->deletedWithOwner;
            $elementsService->deleteElement($block, $this->hardDelete);
        } elseif ($elementsService->getElementById($this->blockId, Block::class, '*') === null) {
            // The owner has already been hard deleted and the block table data no longer exists;
            // make sure the elements table data is cleaned up
            Craft::$app->getDb()
                ->createCommand()
                ->delete(
                    Table::ELEMENTS,
                    ['id' => $this->blockId]
                )
                ->execute();
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
