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
        $neoTopBlock = $entry->getFieldValue('neoField1')->one();

        $this->assertSame(
            1,
            (int)$neoTopBlock->getChildren()->count()
        );
    }
}
