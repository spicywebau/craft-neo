<?php

namespace benf\neo\assets;

use benf\neo\elements\Block;
use benf\neo\Field;
use benf\neo\models\BlockTypeGroup;
use benf\neo\Plugin as Neo;
use Craft;
use craft\base\ElementInterface;
use craft\fieldlayoutelements\CustomField;
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
    public function init()
    {
        $this->sourcePath = '@benf/neo/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'neo-configurator.js',
            'neo-converter.js',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view)
    {
        $view->registerTranslations('neo', [
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
            'What this block type will be called in the CP.',
            'Handle',
            'How you&#8217;ll refer to this block type in the templates.',
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
        $fieldLayoutHtml = self::_renderFieldLayoutHtml();

        $jsSettings = [
            'namespace' => Craft::$app->getView()->getNamespace(),
            'blockTypes' => self::_getBlockTypesJsSettings($blockTypes),
            'groups' => self::_getBlockTypeGroupsJsSettings($blockTypeGroups),
            'fieldLayoutHtml' => $fieldLayoutHtml,
        ];

        $encodedJsSettings = Json::encode($jsSettings);

        return "Neo.createConfigurator($encodedJsSettings)";
    }

    /**
     * Returns the raw data from the given block types, in the format used by the settings generator JavaScript.
     *
     * @param array $blockTypes
     * @return array
     */
    private static function _getBlockTypesJsSettings(array $blockTypes): array {
        $view = Craft::$app->getView();
        $jsBlockTypes = [];

        foreach ($blockTypes as $blockType) {
            $fieldLayout = $blockType->getFieldLayout();
            $oldNamespace = $view->getNamespace();
            $view->setNamespace('neoBlockType' . $blockType['id']);
            $fieldLayoutHtml = self::_renderFieldLayoutHtml($fieldLayout);
            $view->setNamespace($oldNamespace);

            $fieldTypes = [];

            $jsBlockType = [
                'id' => $blockType->id,
                'sortOrder' => $blockType->sortOrder,
                'name' => Craft::t('neo', $blockType->name),
                'handle' => $blockType->handle,
                'maxBlocks' => $blockType->maxBlocks,
                'maxSiblingBlocks' => $blockType->maxSiblingBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'childBlocks' => is_string($blockType->childBlocks) ? Json::decodeIfJson($blockType->childBlocks) : $blockType->childBlocks,
                'topLevel' => (bool)$blockType->topLevel,
                'errors' => $blockType->getErrors(),
                'fieldLayout' => $fieldLayout->getConfig(),
                'fieldLayoutHtml' => $fieldLayoutHtml,
                'fieldLayoutId' => $fieldLayout->id,
                'fieldTypes' => $fieldTypes,
                'groupId' => $blockType->groupId,
            ];

            $jsBlockTypes[] = $jsBlockType;
        }

        return $jsBlockTypes;
    }

    /**
     * Returns the raw data from the given block type groups.
     *
     * This converts block type groups into the format used by the input generator Javascript.
     *
     * @param array $blockTypeGroups The Neo block type groups.
     * @return array
     */
    private static function _getBlockTypeGroupsJsSettings(array $blockTypeGroups): array
    {
        $jsBlockTypeGroups = [];

        foreach ($blockTypeGroups as $blockTypeGroup) {
            if ($blockTypeGroup instanceof BlockTypeGroup) {
                $jsBlockTypeGroups[] = [
                    'id' => $blockTypeGroup->id,
                    'sortOrder' => $blockTypeGroup->sortOrder,
                    'name' => Craft::t('neo', $blockTypeGroup->name),
                ];
            } elseif (is_array($blockTypeGroup)) {
                $jsBlockTypeGroups[] = $blockTypeGroup;
            }
        }

        return $jsBlockTypeGroups;
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
        $html = Craft::$app->getView()->renderTemplate('_includes/fieldlayoutdesigner', [
            'fieldLayout' => $fieldLayout ?? new FieldLayout(['type' => Block::class]),
            'customizableUi' => true,
        ]);
        $view->clearJsBuffer();

        return $html;
    }
}
