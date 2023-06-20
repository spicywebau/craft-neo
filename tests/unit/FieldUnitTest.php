<?php

namespace spicyweb\neotests\unit;

use benf\neo\Field;
use benf\neo\Plugin as Neo;
use Craft;
use craft\test\TestCase;
use UnitTester;

/**
 * Class FieldUnitTest
 *
 * @package spicyweb\neotests\unit
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.8.0
 */
class FieldUnitTest extends TestCase
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * Tests whether Field::setBlockTypes() sets the block types in the correct order.
     */
    public function testSetBlockTypes(): void
    {
        $expectedSortOrder = $this->_expectedSortOrderOld();
        $blockTypeData = $this->_blockTypes(true);
        $field = $this->_field();
        $field->setBlockTypes($blockTypeData);

        foreach ($field->getBlockTypes() as $blockType) {
            if ($blockType->id) {
                $blockTypeId = $blockType->id;
            } else {
                // For new block types we'll need to get the newX ID from $blockTypeData
                foreach ($blockTypeData as $id => $bt) {
                    if ($bt['handle'] === $blockType->handle) {
                        $blockTypeId = $id;
                        break;
                    }
                }
            }

            $this->assertSame("blocktype:$blockTypeId", $expectedSortOrder[$blockType->sortOrder - 1]);
        }
    }

    /**
     * Tests whether Field::setGroups() sets the block type groups in the correct order.
     */
    public function testSetGroups(): void
    {
        $expectedSortOrder = $this->_expectedSortOrderOld();
        $groupData = $this->_groups(true);
        $field = $this->_field();
        $field->setGroups($groupData);

        foreach ($field->getGroups() as $group) {
            if ($group->id) {
                $groupId = $group->id;
            } else {
                // For new groups we'll need to get the newX ID from $groupData
                foreach ($groupData as $id => $g) {
                    if ($g['name'] === $group->name) {
                        $groupId = $id;
                        break;
                    }
                }
            }

            $this->assertSame("group:$groupId", $expectedSortOrder[$group->sortOrder - 1]);
        }
    }

    /**
     * Gets a Neo field.
     *
     * @return Field
     */
    private function _field(): Field
    {
        return Craft::$app->getFields()->getFieldByUid('field-000000000000000000000000000001');
    }

    /**
     * Block type data for testSetBlockTypes().
     *
     * @return array
     */
    private function _blockTypes(bool $populateExisting): array
    {
        return [
            1 => $populateExisting
                ? ['sortOrder' => 5] + Neo::$plugin->blockTypes->getById(1)->getConfig()
                : null,
            'new1' => [
                'name' => 'Test Block Type 1',
                'handle' => 'testBlockType1',
                'sortOrder' => '3',
                'childBlocks' => false,
            ],
            'new2' => [
                'name' => 'Test Block Type 2',
                'handle' => 'testBlockType2',
                'sortOrder' => '6',
                'childBlocks' => false,
            ],
            'new3' => [
                'name' => 'Test Block Type 3',
                'handle' => 'testBlockType3',
                'sortOrder' => '2',
                'childBlocks' => false,
            ],
            'new4' => [
                'name' => 'Test Block Type 4',
                'handle' => 'testBlockType4',
                'sortOrder' => '8',
                'childBlocks' => false,
            ],
        ];
    }

    /**
     * Block type group data for testSetGroups().
     *
     * @return array
     */
    private function _groups(bool $populateExisting): array
    {
        return [
            1 => $populateExisting
                ? ['sortOrder' => 7] + Neo::$plugin->blockTypes->getById(1)->getConfig()
                : null,
            'new1' => [
                'name' => 'Test Group 1',
                'sortOrder' => '4',
            ],
            'new2' => [
                'name' => 'Test Group 2',
                'sortOrder' => '1',
            ],
        ];
    }

    /**
     * The expected sort order for the old style of setting block types and groups (setBlockTypes()/setGroups()).
     *
     * @return array
     */
    private function _expectedSortOrderOld(): array
    {
        $expectedSortOrder = [];

        foreach ($this->_blockTypes(true) as $id => $blockType) {
            $expectedSortOrder[$blockType['sortOrder'] - 1] = "blocktype:$id";
        }

        foreach ($this->_groups(true) as $id => $group) {
            $expectedSortOrder[$group['sortOrder'] - 1] = "group:$id";
        }

        return $expectedSortOrder;
    }
}
