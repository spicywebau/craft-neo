<?php

namespace spicyweb\neotests\unit;

use Craft;
use craft\elements\Entry;
use craft\test\TestCase;
use spicyweb\neotests\fixtures\EntriesFixture;
use UnitTester;

/**
 * Class BlockStructureUnitTest
 *
 * @package spicyweb\neotests\unit
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 4.0.0
 */
class BlockStructureUnitTest extends TestCase
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
     * Tests whether a Neo block structure stays correct after saving the entry.
     */
    public function testSave(): void
    {
        $entry = $this->_entry();
        $blocksBeforeSave = (clone $entry)->getFieldValue('neoField1')->status(null)->all();
        $blocksToSet = [];

        // Ensure some content is changed on each block so they are resaved
        foreach ($blocksBeforeSave as $block) {
            $blocksToSet[$block->id] = ['plainTextField' => 'Test'];
        }

        $entry->setFieldValue('neoField1', [
            'blocks' => $blocksToSet,
            'sortOrder' => array_map(fn($block) => $block->id, $blocksBeforeSave),
        ]);
        Craft::$app->getElements()->saveElement($entry);
        $blocksAfterSave = (clone $entry)->getFieldValue('neoField1')->status(null)->all();

        // Ensure all blocks still have the same general structure
        $this->assertSame(
            count($blocksBeforeSave),
            count($blocksAfterSave)
        );

        foreach ($blocksBeforeSave as $i => $beforeBlock) {
            $afterBlock = $blocksAfterSave[$i];
            $this->assertSame($beforeBlock->level, $afterBlock->level);
            $this->assertSame($beforeBlock->lft, $afterBlock->lft);
            $this->assertSame($beforeBlock->rgt, $afterBlock->rgt);
        }
    }

    /**
     * Gets the entry to use for tests.
     *
     * @return Entry
     */
    private function _entry(): Entry
    {
        return Craft::$app->getElements()->getElementByUid('entry-000000000000000000000000000002', Entry::class);
    }
}
