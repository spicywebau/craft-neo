<?php

namespace spicyweb\neotests\unit;

use benf\neo\elements\Block;
use Craft;
use craft\elements\Entry;
use craft\test\TestCase;
use spicyweb\neotests\fixtures\EntriesFixture;
use UnitTester;

/**
 * Class BlockQueryUnitTest
 *
 * @package spicyweb\neotests\unit
 * @author Spicy Web <plugins@spicyweb.com.au>
 */
class BlockQueryUnitTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @inheritdoc
     */
    public function _fixtures(): array
    {
        return [
            'entries' => EntriesFixture::class,
        ];
    }

    /**
     * Tests that block queries for a block ID return only the live block, and not any other data for that block on a
     * draft's block structure.
     */
    public function testIdGetsLiveBlock(): void
    {
        $entry = $this->_entry();
        $firstBlockId = $entry->getFieldValue('neoField1')->one()->id;
        $draft = $this->_draftOf($entry);
        $shouldHaveOneBlock = Block::find()
            ->id($firstBlockId)
            ->ownerId($entry->id)
            ->all();
        $this->assertSame(
            1,
            count($shouldHaveOneBlock)
        );
    }

    /**
     * Tests that a block query from an entry's only Neo field value, and a `Block::find()` query for that entry's
     * blocks, returns the same number of blocks if the entry has a draft.
     * Failure of this test indicates that the latter query is returning duplicate block data, due to incorrect joining
     * of structure data to block/element data.
     */
    public function testOwnerIdGetsLiveBlocks(): void
    {
        $entry = $this->_entry();
        // Ensure the existence of a draft, so the Neo blocks belong to more than one structure
        $draft = $this->_draftOf($entry);
        $entryNeoBlockCount1 = $entry->getFieldValue('neoField1')->count();
        $entryNeoBlockCount2 = Block::find()->owner($entry)->count();

        $this->assertSame($entryNeoBlockCount1, $entryNeoBlockCount2);
    }

    /**
     * Tests that block queries for revision blocks return the correct results.
     * Failure of this test indicates either an issue with BlockQuery's retrieval of revision blocks, or an issue with
     * the creation of block structures for revisions.
     */
    public function testGetsRevisionBlocks(): void
    {
        $entry = $this->_entry();
        $entryBlocks = $entry->getFieldValue('neoField1')
            ->status(null)
            ->all();
        Craft::$app->getElements()->saveElement($entry);

        // Ensure the block structure exists for the revision before proceeding
        Craft::$app->getQueue()->run();

        $revision = Entry::find()->revisionOf($entry)->one();
        $revisionBlocks = $revision->getFieldValue('neoField1')
            ->status(null)
            ->all();
        $this->assertSame(
            count($entryBlocks),
            count($revisionBlocks)
        );

        for ($i = 0; $i < min(count($entryBlocks), count($revisionBlocks)); $i++) {
            // Ensure correct canonical block
            $this->assertSame(
                $entryBlocks[$i]->id,
                $revisionBlocks[$i]->canonicalId
            );

            // Ensure correct level
            $this->assertSame(
                $entryBlocks[$i]->level,
                $revisionBlocks[$i]->level
            );

            // Ensure correct enabled state
            $this->assertSame(
                $entryBlocks[$i]->enabled,
                $revisionBlocks[$i]->enabled
            );
        }
    }

    /**
     * Gets the entry to use for block children tests.
     *
     * @return Entry
     */
    private function _entry(): Entry
    {
        return Craft::$app->getElements()->getElementByUid('entry-000000000000000000000000000002', Entry::class);
    }

    /**
     * Gets an existing draft version of a given entry, or creates one if it doesn't have any.
     *
     * @param Entry $entry
     * @return Entry draft of $entry
     */
    private function _draftOf(Entry $entry): Entry
    {
        return Entry::find()->draftOf($entry)->one() ?? Craft::$app->getDrafts()->createDraft($entry);
    }
}
