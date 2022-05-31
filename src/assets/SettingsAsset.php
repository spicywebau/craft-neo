<?php

namespace benf\neo\assets;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\Plugin as Neo;
use Craft;
use craft\helpers\Json;
use craft\models\FieldLayout;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Class SettingsAsset
 *
 * @package benf\neo\assets
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 3.0.0
 */
class SettingsAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@benf/neo/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = ['neo-configurator.css'];
        $this->js = [
            'neo-configurator.js',
            'neo-converter.js',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        $view->registerTranslations('neo', [
            'Select',
            'Disabled',
            'Actions',
            'Collapse',
            'Expand',
            'Disable',
            'Enable',
            'Copy',
            'Paste',
            'Clone',
            'Delete',
            'Reorder',
            'Add a block',
            'Name',
            'What this block type will be called in the CP.',
            'Handle',
            'How you’ll refer to this block type in the templates.',
            'Description',
            'Max Blocks',
            'The maximum number of blocks of this type the field is allowed to have.',
            'All',
            'Child Blocks',
            'Which block types do you want to allow as children?',
            'Max Child Blocks',
            'The maximum number of child blocks this block type is allowed to have.',
            'Top Level',
            'Will this block type be allowed at the top level?',
            'Delete block type',
            'This can be left blank if you just want an unlabeled separator.',
            'Show',
            'Hide',
            'Use global setting (Show)',
            'Use global setting (Hide)',
            'Always Show Dropdown?',
            'Whether to show the dropdown for this group if it only has one available block type.',
            'Delete group',
            'Block Types',
            'Block type',
            'Group',
            'Settings',
            'Field Layout',
        ]);

        parent::registerAssetFiles($view);
    }

    /**
     * Sets up the field layout designer for a given Neo field.
     *
     * @param Field $field The Neo field.
     * @return string
     */
    public static function createSettingsJs(Field $field): string
    {
        $blockTypes = $field->getBlockTypes();
        $blockTypeGroups = $field->getGroups();
        [$blockTypeSettingsHtml, $blockTypeSettingsJs] = self::_renderBlockTypeSettings();
        $fieldLayoutHtml = self::_renderFieldLayoutHtml();

        $jsSettings = [
            'namespace' => Craft::$app->getView()->getNamespace(),
            'blockTypes' => self::_getBlockTypesJsSettings($blockTypes),
            'groups' => self::_getBlockTypeGroupsJsSettings($blockTypeGroups),
            'blockTypeSettingsHtml' => $blockTypeSettingsHtml,
            'blockTypeSettingsJs' => $blockTypeSettingsJs,
            'fieldLayoutHtml' => $fieldLayoutHtml,
            'defaultAlwaysShowGroupDropdowns' => Neo::$plugin->settings->defaultAlwaysShowGroupDropdowns,
        ];

        $encodedJsSettings = Json::encode($jsSettings, JSON_UNESCAPED_UNICODE);

        return "Neo.createConfigurator($encodedJsSettings)";
    }

    /**
     * Returns the raw data from the given block types, in the format used by the settings generator JavaScript.
     *
     * @param BlockType[] $blockTypes
     * @return array
     */
    private static function _getBlockTypesJsSettings(array $blockTypes): array
    {
        $view = Craft::$app->getView();
        $jsBlockTypes = [];

        foreach ($blockTypes as $blockType) {
            [$blockTypeSettingsHtml, $blockTypeSettingsJs] = self::_renderBlockTypeSettings($blockType);
            $fieldLayout = $blockType->getFieldLayout();
            $oldNamespace = $view->getNamespace();
            $view->setNamespace('neoBlockType' . $blockType['id']);
            $fieldLayoutHtml = self::_renderFieldLayoutHtml($fieldLayout);
            $view->setNamespace($oldNamespace);

            $jsBlockType = [
                'id' => $blockType->id,
                'sortOrder' => $blockType->sortOrder,
                'name' => Craft::t('site', $blockType->name),
                'handle' => $blockType->handle,
                'description' => $blockType->description,
                'maxBlocks' => $blockType->maxBlocks,
                'maxSiblingBlocks' => $blockType->maxSiblingBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'childBlocks' => is_string($blockType->childBlocks) ? Json::decodeIfJson($blockType->childBlocks) : $blockType->childBlocks,
                'topLevel' => (bool)$blockType->topLevel,
                'errors' => $blockType->getErrors(),
                'fieldLayout' => $fieldLayout->getConfig(),
                'fieldLayoutHtml' => $fieldLayoutHtml,
                'settingsHtml' => $blockTypeSettingsHtml,
                'settingsJs' => $blockTypeSettingsJs,
                'fieldLayoutId' => $fieldLayout->id,
                'groupId' => $blockType->groupId,
            ];

            $jsBlockTypes[] = $jsBlockType;
        }

        return $jsBlockTypes;
    }

    /**
     * Returns the raw data from the given block type groups, in the format used by the settings generator JavaScript.
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
                'alwaysShowDropdown' => $blockTypeGroup->alwaysShowDropdown,
            ];
        }

        return $jsBlockTypeGroups;
    }

    /**
     * @param BlockType|null $blockType
     * @return array
     */
    private static function _renderBlockTypeSettings(?BlockType $blockType = null): array
    {
        $view = Craft::$app->getView();
        $blockTypeId = $blockType?->id ?? '__NEOBLOCKTYPE_ID__';
        $oldNamespace = $view->getNamespace();
        $newNamespace = $oldNamespace . '[blockTypes][' . $blockTypeId . ']';
        $view->setNamespace($newNamespace);
        $view->startJsBuffer();

        $html = $view->namespaceInputs($view->renderTemplate('neo/block-type-settings', [
            'blockType' => $blockType,
        ]));

        $js = $view->clearJsBuffer();
        $view->setNamespace($oldNamespace);

        return [$html, $js];
    }

    /**
     * @param FieldLayout|null $fieldLayout
     * @return string
     */
    private static function _renderFieldLayoutHtml(?FieldLayout $fieldLayout = null): string
    {
        $view = Craft::$app->getView();

        // Render the field layout designer HTML, but disregard any JavaScript it outputs, as that'll be handled by Neo
        $view->startJsBuffer();
        $html = $view->renderTemplate('_includes/fieldlayoutdesigner', [
            'fieldLayout' => $fieldLayout ?? new FieldLayout(['type' => Block::class]),
            'customizableUi' => true,
        ]);
        $view->clearJsBuffer();

        return $html;
    }
}
