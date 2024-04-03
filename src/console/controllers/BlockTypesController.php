<?php

namespace benf\neo\console\controllers;

use benf\neo\elements\Block;
use benf\neo\errors\BlockTypeNotFoundException;
use benf\neo\models\BlockType;
use benf\neo\Plugin as Neo;
use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use yii\console\ExitCode;

/**
 * Actions for managing Neo block types.
 *
 * @package benf\neo\console\controllers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.2.0
 */
class BlockTypesController extends Controller
{
    /**
     * @var int|null A Neo block type ID.
     */
    public ?int $typeId = null;

    /**
     * @var int|null A Neo field ID.
     */
    public ?int $fieldId = null;

    /**
     * @var string|null A Neo block type handle.
     */
    public ?string $handle = null;

    /**
     * @var string|null A new name to set for the block type.
     */
    public ?string $setName = null;

    /**
     * @var string|null A new handle to set for the block type.
     */
    public ?string $setHandle = null;

    /**
     * @var string|null A new description to set for the block type.
     */
    public ?string $setDescription = null;

    /**
     * @var bool Whether to remove the block type's description.
     */
    public bool $unsetDescription = false;

    /**
     * @var bool Whether to set the block type as being allowed to be used.
     * @since 3.3.0
     */
    public bool $setEnabled = false;

    /**
     * @var bool Whether to set the block type as not being allowed to be used.
     * @since 3.3.0
     */
    public bool $unsetEnabled = false;

    /**
     * @var int|null A new min blocks value to set for the block type.
     * @since 3.3.0
     */
    public ?int $setMinBlocks = null;

    /**
     * @var int|null A new max blocks value to set for the block type.
     */
    public ?int $setMaxBlocks = null;

    /**
     * @var int|null A new min sibling blocks value to set for the block type.
     * @since 3.3.0
     */
    public ?int $setMinSiblingBlocks = null;

    /**
     * @var int|null A new max sibling blocks value to set for the block type.
     */
    public ?int $setMaxSiblingBlocks = null;

    /**
     * @var int|null A new min child blocks value to set for the block type.
     * @since 3.3.0
     */
    public ?int $setMinChildBlocks = null;

    /**
     * @var int|null A new max child blocks value to set for the block type.
     */
    public ?int $setMaxChildBlocks = null;

    /**
     * @var bool Whether to set the block type's child block types as being shown in their groups.
     * @since 3.5.0
     */
    public bool $setGroupChildBlockTypes = false;

    /**
     * @var bool Whether to set the block type's child block types as not being shown in their groups.
     * @since 3.5.0
     */
    public bool $unsetGroupChildBlockTypes = false;

    /**
     * @var string|null The child block types of this block type, either as comma-separated block type handles, or the
     * string '*' representing all of the Neo field's block types.
     */
    public ?string $setChildBlocks = null;

    /**
     * @var bool Whether to set this block type as having no child block types.
     */
    public bool $unsetChildBlocks = false;

    /**
     * @var bool Whether to set the block type as being allowed at the top level.
     */
    public bool $setTopLevel = false;

    /**
     * @var bool Whether to set the block type as not being allowed at the top level.
     */
    public bool $unsetTopLevel = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'typeId';
        $options[] = 'fieldId';
        $options[] = 'handle';

        if ($actionID === 'edit') {
            $options[] = 'setName';
            $options[] = 'setHandle';
            $options[] = 'setDescription';
            $options[] = 'unsetDescription';
            $options[] = 'setEnabled';
            $options[] = 'unsetEnabled';
            $options[] = 'setMinBlocks';
            $options[] = 'setMaxBlocks';
            $options[] = 'setMinSiblingBlocks';
            $options[] = 'setMaxSiblingBlocks';
            $options[] = 'setMinChildBlocks';
            $options[] = 'setMaxChildBlocks';
            $options[] = 'setGroupChildBlockTypes';
            $options[] = 'unsetGroupChildBlockTypes';
            $options[] = 'setChildBlocks';
            $options[] = 'unsetChildBlocks';
            $options[] = 'setTopLevel';
            $options[] = 'unsetTopLevel';
        }

        return $options;
    }

    /**
     * Deletes a Neo block type.
     *
     * @return int
     */
    public function actionDelete(): int
    {
        try {
            $blockType = $this->_getBlockType();
        } catch (BlockTypeNotFoundException $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $this->stdout('Deleting the block type...' . PHP_EOL);
        Craft::$app->getProjectConfig()->remove('neo.blockTypes.' . $blockType->uid);
        $this->stdout('Done.' . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Edits a Neo block type.
     *
     * @return int
     */
    public function actionEdit(): int
    {
        try {
            $blockType = $this->_getBlockType();
        } catch (BlockTypeNotFoundException $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $typePath = 'neo.blockTypes.' . $blockType->uid;
        $typeConfig = $projectConfig->get($typePath);

        if ($this->setName) {
            $typeConfig['name'] = $this->setName;
        }

        if ($this->setHandle) {
            $typeConfig['handle'] = $this->setHandle;
        }

        $blockTypeConstraintProperties = [
            'minBlocks',
            'maxBlocks',
            'minSiblingBlocks',
            'maxSiblingBlocks',
            'minChildBlocks',
            'maxChildBlocks',
        ];

        foreach ($blockTypeConstraintProperties as $btProperty) {
            $controllerProperty = 'set' . ucfirst($btProperty);

            if ($this->$controllerProperty !== null) {
                $typeConfig[$btProperty] = $this->$controllerProperty;
            }
        }

        foreach (['description', 'enabled', 'topLevel', 'groupChildBlockTypes'] as $btProperty) {
            $setProperty = 'set' . ucfirst($btProperty);
            $unsetProperty = 'unset' . ucfirst($btProperty);

            if ($this->$setProperty && $this->$unsetProperty) {
                $optionKebab = StringHelper::toKebabCase($btProperty);
                $this->stderr($this->_getSetUnsetError($optionKebab), Console::FG_RED);
            } elseif ($this->$setProperty) {
                $typeConfig[$btProperty] = $this->$setProperty;
            } elseif ($this->$unsetProperty) {
                $typeConfig[$btProperty] = is_bool($this->$setProperty) ? false : null;
            }
        }

        if ($this->setChildBlocks && $this->unsetChildBlocks) {
            $this->stderr($this->_getSetUnsetError('child-blocks'), Console::FG_RED);
        } elseif ($this->setChildBlocks) {
            $childBlockTypes = explode(',', $this->setChildBlocks);

            if ($childBlockTypes !== ['*']) {
                $existingHandles = (new Query())
                    ->select(['handle'])
                    ->from(['{{%neoblocktypes}}'])
                    ->where([
                        'handle' => $childBlockTypes,
                        'fieldId' => $blockType->fieldId,
                    ])
                    ->column();
                $nonExistingHandles = array_diff($childBlockTypes, $existingHandles);

                if (empty($nonExistingHandles)) {
                    $typeConfig['childBlocks'] = $childBlockTypes;
                } else {
                    $this->stderr('Invalid handles ' . implode(', ', $nonExistingHandles) . ' given for --set-child-blocks.' . PHP_EOL, Console::FG_RED);
                }
            } else {
                $typeConfig['childBlocks'] = '*';
            }
        } elseif ($this->unsetChildBlocks) {
            $typeConfig['childBlocks'] = null;
        }

        $projectConfig->set($typePath, $typeConfig);
        $this->stdout('Done.' . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Converts block type icon settings from the asset format to the filename format.
     *
     * @return int
     */
    public function actionConvertIcons(): int
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $assetIdFilenameMap = [];
        $iconFolderPath = App::parseEnv(Neo::$plugin->getSettings()->blockTypeIconPath);

        // Get all block types that have an icon asset set, that don't already have a filename set
        // in the new format that we don't want to overwrite
        $blockTypes = array_filter(
            Neo::$plugin->blockTypes->getAllBlockTypes(),
            fn($blockType) => $blockType->iconId !== null && empty($blockType->iconFilename)
        );
        $this->stdout('Converting the icons of ' . count($blockTypes) . ' block types... ');

        foreach ($blockTypes as $blockType) {
            if (!isset($assetIdFilenameMap[$blockType->iconId])) {
                $assetIdFilenameMap[$blockType->iconId] = $blockType->id . '.svg';
                $fileContents = $blockType->getIcon()->getContents();
                FileHelper::writeToFile(
                    $iconFolderPath . DIRECTORY_SEPARATOR . $assetIdFilenameMap[$blockType->iconId],
                    $fileContents
                );
            }

            $projectConfig->set(
                'neo.blockTypes.' . $blockType->uid . '.iconFilename',
                $assetIdFilenameMap[$blockType->iconId]
            );
        }

        $this->stdout('Done.' . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Ensures each block type has a unique field layout.
     *
     * @return int
     * @since 4.1.0
     */
    public function actionFixFieldLayouts(): int
    {
        $fieldsService = Craft::$app->getFields();
        $projectConfig = Craft::$app->getProjectConfig();
        $processedFieldLayouts = [];
        $newLayouts = [];

        foreach (Neo::$plugin->blockTypes->getAllBlockTypes() as $blockType) {
            if ($blockType->fieldLayoutId === null) {
                continue;
            }

            if (!isset($processedFieldLayouts[$blockType->fieldLayoutId])) {
                $processedFieldLayouts[$blockType->fieldLayoutId] = true;
                continue;
            }

            $layout = $blockType->getFieldLayout();

            // Reset all tab/element UUIDs
            foreach ($layout->tabs as $tab) {
                $tab->uid = StringHelper::UUID();

                foreach ($tab->elements as $element) {
                    $element->uid = StringHelper::UUID();
                }
            }

            $newLayouts[$blockType->uid] = [
                StringHelper::UUID(),
                $layout->getConfig(),
            ];
        }

        // Clear out the duplicate layout IDs
        Db::update('{{%neoblocktypes}}', [
            'fieldLayoutId' => null,
        ], [
            'uid' => array_keys($newLayouts),
        ]);

        // Set the new layout in the project config
        foreach ($newLayouts as $blockTypeUuid => $layoutData) {
            $projectConfig->set("neoBlockTypes.$blockTypeUuid.fieldLayouts", [
                $layoutData[0] => $layoutData[1],
            ]);
        }

        $this->stdout('Done.' . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * @return BlockType
     * @throws BlockTypeNotFoundException
     */
    private function _getBlockType(): BlockType
    {
        // Prioritise the block type ID
        if ($this->typeId) {
            return Neo::$plugin->blockTypes->getById($this->typeId) ??
                throw new BlockTypeNotFoundException("Block type with ID $this->typeId not found.");
        }

        if ($this->handle === null) {
            throw new BlockTypeNotFoundException('Block type ID or handle not specified.');
        }

        $blockTypes = $this->fieldId
            ? Neo::$plugin->blockTypes->getByFieldId($this->fieldId)
            : Neo::$plugin->blockTypes->getAllBlockTypes();

        $blockTypes = array_filter($blockTypes, fn($bt) => $bt->handle === $this->handle);
        $count = count($blockTypes);

        if ($count > 1) {
            throw new BlockTypeNotFoundException("Field ID not specified for block type handle $this->handle with $count matches.");
        }

        if ($count === 0) {
            throw new BlockTypeNotFoundException(
                $this->fieldId === null
                    ? "Block type with handle $this->handle not found."
                    : "Block type with handle $this->handle and field ID $this->fieldId not found."
            );
        }

        return reset($blockTypes);
    }

    private function _getSetUnsetError(string $option): string
    {
        return "At most one of --set-$option and --unset-$option may be used." . PHP_EOL;
    }
}
