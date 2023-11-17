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
     * @var BlockType[]
     * @since 3.3.8
     */
    public static array $filteredBlockTypes = [];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@benf/neo/assets/dist';

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
            'Error',
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
        $conditionsService = Craft::$app->getConditions();
        $view = Craft::$app->getView();
        $name = $field->handle;
        $id = $view->formatInputId($name);
        $blockTypeGroups = $field->getGroups();

        if ($owner) {
            // Filter block types based on the block types' condition rules
            $ownerClass = get_class($owner);
            $blockTypes = array_filter(
                $field->getBlockTypes(),
                function($blockType) use ($conditionsService, $owner, $ownerClass) {
                    if (isset($blockType->conditions[$ownerClass])) {
                        $condition = $conditionsService->createCondition($blockType->conditions[$ownerClass]);
                        return $condition->matchElement($owner);
                    }

                    return true;
                }
            );
        } else {
            $blockTypes = $field->getBlockTypes();
        }

        // Filter block types based on the event
        $event = new FilterBlockTypesEvent([
            'field' => $field,
            'element' => $owner,
            'blockTypes' => $blockTypes,
            'blockTypeGroups' => $blockTypeGroups,
        ]);
        Event::trigger(self::class, self::EVENT_FILTER_BLOCK_TYPES, $event);

        self::$filteredBlockTypes = $event->blockTypes;

        $jsSettings = [
            'id' => $field->id,
            'ownerId' => $owner?->id,
            'name' => $name,
            'namespace' => $view->namespaceInputName($name) . '[blocks]',
            'blockTypes' => self::_getBlockTypesJsSettings($field, $event->blockTypes, $owner),
            'groups' => self::_getBlockTypeGroupsJsSettings($event->blockTypeGroups),
            'inputId' => $view->namespaceInputId($id),
            'minBlocks' => $field->minBlocks,
            'maxBlocks' => $field->maxBlocks,
            'maxTopBlocks' => $field->maxTopBlocks,
            'minLevels' => (int)$field->minLevels,
            'maxLevels' => (int)$field->maxLevels,
            'showBlockTypeHandles' => Craft::$app->getUser()->getIdentity()->getPreference('showFieldHandles'),
            'newBlockMenuStyle' => Neo::$plugin->getSettings()->newBlockMenuStyle,
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
        $user = Craft::$app->getUser()->getIdentity();
        $pluginSettings = Neo::$plugin->getSettings();
        $loadTabs = !$pluginSettings->enableLazyLoadingNewBlocks;
        $disablePermissions = !$pluginSettings->enableBlockTypeUserPermissions;
        $jsBlockTypes = [];

        foreach ($blockTypes as $blockType) {
            $block = new Block();
            $block->fieldId = $field->id;
            $block->typeId = $blockType->id;

            if ($owner) {
                $block->setOwner($owner);
                $block->siteId = $owner->siteId;
            }

            $ignorePermissions = $disablePermissions || $blockType->ignorePermissions;
            $jsBlockTypes[] = [
                'id' => $blockType->id,
                'sortOrder' => $blockType->sortOrder,
                'name' => Craft::t('site', $blockType->name),
                'handle' => $blockType->handle,
                'enabled' => $blockType->enabled,
                'description' => $blockType->description,
                'minBlocks' => $blockType->minBlocks,
                'maxBlocks' => $blockType->maxBlocks,
                'minSiblingBlocks' => $blockType->minSiblingBlocks,
                'maxSiblingBlocks' => $blockType->maxSiblingBlocks,
                'minChildBlocks' => $blockType->minChildBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'groupChildBlockTypes' => (bool)$blockType->groupChildBlockTypes,
                'childBlocks' => is_string($blockType->childBlocks) ? Json::decodeIfJson($blockType->childBlocks) : $blockType->childBlocks,
                'topLevel' => (bool)$blockType->topLevel,
                'tabNames' => array_map(
                    fn($tab) => Craft::t('site', $tab->name),
                    $blockType->getFieldLayout()->getTabs()
                ),
                'tabs' => $loadTabs ? Neo::$plugin->blocks->renderTabs($block) : null,
                'fieldLayoutId' => $blockType->fieldLayoutId,
                'groupId' => $blockType->groupId,
                'hasChildBlocksUiElement' => $blockType->hasChildBlocksUiElement(),
                'creatableByUser' => $ignorePermissions || $user->can("neo-createBlocks:{$blockType->uid}"),
                'deletableByUser' => $ignorePermissions || $user->can("neo-deleteBlocks:{$blockType->uid}"),
                'editableByUser' => $ignorePermissions || $user->can("neo-editBlocks:{$blockType->uid}"),
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
