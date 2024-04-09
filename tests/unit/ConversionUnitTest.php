<?php

namespace spicyweb\neotests\unit;

use benf\neo\Plugin as Neo;
use Craft;
use craft\fields\Matrix;
use craft\test\TestCase;
use spicyweb\neotests\fixtures\EntriesFixture;
use UnitTester;

/**
 * Class ConversionUnitTest
 *
 * @package spicyweb\neotests\unit
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 4.1.2
 */
class ConversionUnitTest extends TestCase
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
     * Tests Neo-to-Matrix conversion.
     */
    public function testConvertFieldToMatrix(): void
    {
        $fieldsService = Craft::$app->getFields();
        $fieldHandle = 'neoConversionField';
        Neo::$plugin->conversion->convertFieldToMatrix($fieldsService->getFieldByHandle($fieldHandle));
        $this->assertTrue($fieldsService->getFieldByHandle($fieldHandle) instanceof Matrix);
    }
}
