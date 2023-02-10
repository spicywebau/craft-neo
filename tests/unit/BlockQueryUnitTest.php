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
        $draft = Craft::$app->getDrafts()->createDraft($entry);
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
     * Gets the entry to use for block children tests.
     *
     * @return Entry
     */
    private function _entry(): Entry
    {
        return Craft::$app->getElements()->getElementByUid('entry-000000000000000000000000000002', Entry::class);
    }
}
