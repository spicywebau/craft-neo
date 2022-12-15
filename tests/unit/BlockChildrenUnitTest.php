<?php

namespace spicyweb\neotests\unit;

use Craft;
use craft\elements\Entry;
use craft\test\TestCase;
use spicyweb\neotests\fixtures\EntriesFixture;
use UnitTester;

/**
 * Class BlockChildrenUnitTest
 *
 * @package spicyweb\neotests\unit
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.6.0
 */
class BlockChildrenUnitTest extends TestCase
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
     * Tests block children queries for non-eager-loaded Neo fields.
     */
    public function testNonEagerLoaded(): void
    {
        $entry = Craft::$app->getElements()->getElementByUid('entry-000000000000000000000000000001', Entry::class);
        $neoTopBlocks = $entry->getFieldValue('neoField1')->level(1)->all();

        // Not that this class is meant to test top level blocks, but there should only be two top level blocks
        $this->assertSame(
            2,
            count($neoTopBlocks)
        );

        // The first top level block has two children
        $this->assertSame(
            2,
            (int)$neoTopBlocks[0]->getChildren()->count()
        );

        // The second top level block also has two children, but only one of them is enabled
        $this->assertSame(
            1,
            (int)$neoTopBlocks[1]->getChildren()->count()
        );
        $this->assertSame(
            2,
            (int)$neoTopBlocks[1]->getChildren()->status(null)->count()
        );
    }
}
