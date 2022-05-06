<?php

namespace benf\neo\assets;

use benf\neo\elements\Block;
use benf\neo\events\FilterBlockTypesEvent;
use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\Plugin as Neo;
use Craft;
use craft\base\ElementInterface;
use craft\helpers\Json;
use craft\web\assets\cp\CpAsset;
use yii\base\Event;

/**
 * Class InputAsset
 *
 * @package benf\neo\assets
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 3.0.0
 */
class InputAsset extends FieldAsset
{
    /**
     * Event that allows filtering what block types are available for a given field.
     *
     * @event FilterBlockTypesEvent
     *
     * ```php
     * use benf\neo\assets\InputAsset;
     * use benf\neo\events\FilterBlockTypesEvent;
     * use yii\base\Event;
     *
     * Event::on(InputAsset::class, InputAsset::EVENT_FILTER_BLOCK_TYPES, function (FilterBlockTypesEvent $event) {
     *     $filtered = [];
     *     foreach ($event->blockTypes as $type) {
     *         if ($type->handle === 'cards') {
     *             $filtered[] = $type;
     *         }
     *     }
     *
     *     $event->blockTypes = $filtered;
     * });
     * ```
     */
    public const EVENT_FILTER_BLOCK_TYPES = 'filterBlockTypes';

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@benf/neo/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = ['neo-main.css'];
        $this->js = ['neo-main.js'];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        $view->registerTranslations('neo', [
            'Tabs',
            'Select',
            'Disabled',
            'Actions',
            'Collapse',
            'Expand',
            'Disable',
            'Enable',
            'Add block above',
            'Copy',
            'Paste',
            'Clone',
            'Delete',
            'Reorder',
            'Add a block',
            'Move up',
            'Move down',
            'Name',
            'Handle',
            'Max Blocks',
            'All',
            'Child Blocks',
            'Max Child Blocks',
            'Top Level',
            'This can be left blank if you just want an unlabeled separator.',
            'Block Types',
            'Block type',
            'Group',
            'Settings',
            'Field Layout',
        ]);

        parent::registerAssetFiles($view);
    }

    /**
     * Sets up the field block inputs for a given Neo field.
     *
     * @param Field $field The Neo field.
     * @param ElementInterface|null $owner
     * @return string
     */
    public static function createInputJs(Field $field, ?ElementInterface $owner = null): string
    {
        $view = Craft::$app->getView();
        $name = $field->handle;
        $id = $view->formatInputId($name);
        $blockTypes = $field->getBlockTypes();
        $blockTypeGroups = $field->getGroups();

        $event = new FilterBlockTypesEvent([
            'field' => $field,
            'element' => $owner,
            'blockTypes' => $blockTypes,
            'blockTypeGroups' => $blockTypeGroups,
        ]);
        Event::trigger(self::class, self::EVENT_FILTER_BLOCK_TYPES, $event);

        $jsSettings = [
            'name' => $name,
            'namespace' => $view->namespaceInputName($name) . '[blocks]',
            'blockTypes' => self::_getBlockTypesJsSettings($field, $event->blockTypes, $owner),
            'groups' => self::_getBlockTypeGroupsJsSettings($event->blockTypeGroups),
            'inputId' => $view->namespaceInputId($id),
            'minBlocks' => $field->minBlocks,
            'maxBlocks' => $field->maxBlocks,
            'maxTopBlocks' => $field->maxTopBlocks,
            'maxLevels' => (int)$field->maxLevels,
        ];

        $encodedJsSettings = Json::encode($jsSettings, JSON_UNESCAPED_UNICODE);

        return "Neo.createInput($encodedJsSettings)";
    }

    /**
     * Returns the raw data from the given block types, in the format used by the input JavaScript.
     *
     * @param Field $field
     * @param BlockType[] $blockTypes
     * @param ElementInterface|null $owner
     * @return array
     */
    private static function _getBlockTypesJsSettings(Field $field, array $blockTypes, ?ElementInterface $owner = null): array
    {
        $jsBlockTypes = [];

        foreach ($blockTypes as $blockType) {
            $block = new Block();
            $block->fieldId = $field->id;
            $block->typeId = $blockType->id;

            if ($owner) {
                $block->setOwner($owner);
                $block->siteId = $owner->siteId;
            }

            $jsBlockTypes[] = [
                'id' => $blockType->id,
                'sortOrder' => $blockType->sortOrder,
                'name' => Craft::t('site', $blockType->name),
                'handle' => $blockType->handle,
                'maxBlocks' => $blockType->maxBlocks,
                'maxSiblingBlocks' => $blockType->maxSiblingBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'childBlocks' => is_string($blockType->childBlocks) ? Json::decodeIfJson($blockType->childBlocks) : $blockType->childBlocks,
                'topLevel' => (bool)$blockType->topLevel,
                'tabs' => Neo::$plugin->blocks->renderTabs($block),
                'fieldLayoutId' => $blockType->fieldLayoutId,
                'groupId' => $blockType->groupId,
                'hasChildBlocksUiElement' => $blockType->hasChildBlocksUiElement(),
            ];
        }

        return $jsBlockTypes;
    }

    /**
     * Returns the raw data from the given block type groups, in the format used by the input Javascript.
     *
     * @param BlockTypeGroup[] $blockTypeGroups The Neo block type groups.
     * @return array
     */
    private static function _getBlockTypeGroupsJsSettings(array $blockTypeGroups): array
    {
        $jsBlockTypeGroups = [];

        foreach ($blockTypeGroups as $blockTypeGroup) {
            $jsBlockTypeGroups[] = [
                'id' => $blockTypeGroup->id,
                'sortOrder' => $blockTypeGroup->sortOrder,
                'name' => Craft::t('site', $blockTypeGroup->name),
                'alwaysShowDropdown' => $blockTypeGroup->alwaysShowDropdown ?? Neo::$plugin->settings->defaultAlwaysShowGroupDropdowns,
            ];
        }

        return $jsBlockTypeGroups;
    }
}
