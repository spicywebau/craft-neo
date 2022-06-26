<?php

namespace benf\neo\console\controllers;

use benf\neo\models\BlockType;
use benf\neo\errors\BlockTypeNotFoundException;
use benf\neo\Plugin as Neo;
use Craft;
use craft\console\Controller;
use craft\helpers\Console;
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
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'typeId';
        $options[] = 'fieldId';
        $options[] = 'handle';

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
        Craft::$app->getProjectConfig()->remove('neoBlockTypes.' . $blockType->uid);
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
}
