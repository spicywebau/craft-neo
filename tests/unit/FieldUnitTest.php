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
        $expectedSortOrder = $this->_expectedSortOrder();
        $blockTypeData = $this->_blockTypes(true);
        $field = $this->_field();
        $field->setBlockTypes($blockTypeData);
        $this->_assertCorrectBlockTypeOrder($field->getBlockTypes(), $blockTypeData, $expectedSortOrder);
    }

    /**
     * Tests whether Field::setGroups() sets the block type groups in the correct order.
     */
    public function testSetGroups(): void
    {
        $expectedSortOrder = $this->_expectedSortOrder();
        $groupData = $this->_groups(true);
        $field = $this->_field();
        $field->setGroups($groupData);
        $this->_assertCorrectGroupOrder($field->getGroups(), $groupData, $expectedSortOrder);
    }

    /**
     * Tests whether Field::setItems() sets block types and groups in the correct order.
     */
    public function testSetItems(): void
    {
        $expectedSortOrder = $this->_expectedSortOrder();
        $blockTypeData = $this->_blockTypes(false);
        $groupData = $this->_groups(false);
        $field = $this->_field();
        $field->setItems([
            'sortOrder' => $expectedSortOrder,
            'blockTypes' => $blockTypeData,
            'groups' => $groupData,
        ]);
        $this->_assertCorrectBlockTypeOrder($field->getBlockTypes(), $blockTypeData, $expectedSortOrder);
        $this->_assertCorrectGroupOrder($field->getGroups(), $groupData, $expectedSortOrder);
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
     * Block type data for testSetBlockTypes() and testSetItems().
     *
     * @return array
     */
    private function _blockTypes(bool $populateExisting): array
    {
        return array_filter([
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
        ]);
    }

    /**
     * Block type group data for testSetGroups() and testSetItems().
     *
     * @return array
     */
    private function _groups(bool $populateExisting): array
    {
        return array_filter([
            1 => $populateExisting
                ? ['sortOrder' => 7] + Neo::$plugin->blockTypes->getGroupById(1)->getConfig()
                : null,
            'new1' => [
                'name' => 'Test New Group 1',
                'sortOrder' => '4',
            ],
            'new2' => [
                'name' => 'Test New Group 2',
                'sortOrder' => '1',
            ],
        ]);
    }

    /**
     * The expected sort order of the set block types and groups.
     *
     * @return array
     */
    private function _expectedSortOrder(): array
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

    /**
     * Tests whether block types are set in the correct order.
     */
    private function _assertCorrectBlockTypeOrder($blockTypeModels, $blockTypeData, $expectedSortOrder): void
    {
        foreach ($blockTypeModels as $blockType) {
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
     * Tests whether block type groups are set in the correct order.
     */
    private function _assertCorrectGroupOrder($groupModels, $groupData, $expectedSortOrder): void
    {
        foreach ($groupModels as $group) {
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
}
