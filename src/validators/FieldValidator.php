<?php

namespace benf\neo\validators;

use benf\neo\elements\Block;
use Craft;
use yii\validators\Validator;

/**
 * Class FieldValidator
 *
 * @package benf\neo\validators
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.3.0
 */
class FieldValidator extends Validator
{
    /**
     * @var int|null The maximum top-level blocks the field can have.  If not set, there is no top-level block limit.
     */
    public ?int $maxTopBlocks = null;

    /**
     * @var int|null The maximum level blocks can be nested in the field.  If not set, there is no limit.
     * @since 2.9.0
     */
    public ?int $maxLevels = null;

    /**
     * @var string|null A user-defined error message to be used if the field's `maxTopBlocks` is exceeded.
     */
    public ?string $tooManyTopBlocks = null;

    /**
     * @var string|null A user-defined error message to be used if the field's `maxLevels` is exceeded.
     * @since 2.9.0
     */
    public ?string $exceedsMaxLevels = null;

    /**
     * @var string|null A user-defined error message to be used if a block type's `minBlocks` setting is violated.
     * @since 3.3.0
     */
    public ?string $tooFewBlocksOfType = null;

    /**
     * @var string|null A user-defined error message to be used if a block type's `maxBlocks` is exceeded.
     * @since 3.0.0
     */
    public ?string $tooManyBlocksOfType = null;

    /**
     * @var Block[]
     */
    private array $_blocks = [];

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute): void
    {
        $this->_setDefaultErrorMessages();

        $field = $model->getFieldLayout()->getFieldByHandle($attribute);
        $value = $model->$attribute;
        $this->_blocks = $value->all();

        $this->_checkMaxTopLevelBlocks($model, $attribute);
        $this->_checkMaxLevels($model, $attribute);

        // Check for min/max blocks by block type
        $blockTypesCount = [];
        $blockTypesById = [];

        foreach ($field->getBlockTypes() as $blockType) {
            $blockTypesCount[$blockType->id] = 0;
            $blockTypesById[$blockType->id] = $blockType;
        }

        foreach ($this->_blocks as $block) {
            $blockTypesCount[$block->typeId] += 1;
        }

        foreach ($blockTypesCount as $blockTypeId => $blockTypeCount) {
            $blockType = $blockTypesById[$blockTypeId];

            if ($blockType->minBlocks > 0 && $blockTypeCount < $blockType->minBlocks) {
                $this->addError(
                    $model,
                    $attribute,
                    $this->tooFewBlocksOfType,
                    [
                        'minBlockTypeBlocks' => $blockType->minBlocks,
                        'blockType' => $blockType->name,
                    ]
                );
            }

            if ($blockType->maxBlocks > 0 && $blockTypeCount > $blockType->maxBlocks) {
                $this->addError(
                    $model,
                    $attribute,
                    $this->tooManyBlocksOfType,
                    [
                        'maxBlockTypeBlocks' => $blockType->maxBlocks,
                        'blockType' => $blockType->name,
                    ]
                );
            }
        }
    }

    /**
     * Adds an error if the field exceeds its max top-level blocks.
     */
    private function _checkMaxTopLevelBlocks($model, $attribute): void
    {
        if ($this->maxTopBlocks !== null) {
            $topBlocks = array_filter($this->_blocks, fn($block) => (int)$block->level === 1);

            if (count($topBlocks) > $this->maxTopBlocks) {
                $this->addError($model, $attribute, $this->tooManyTopBlocks, ['maxTopBlocks' => $this->maxTopBlocks]);
            }
        }
    }

    /**
     * Adds an error if the field exceeds its max levels.
     */
    private function _checkMaxLevels($model, $attribute): void
    {
        $maxLevels = $this->maxLevels;

        if ($maxLevels !== null) {
            $tooHighBlocks = array_filter($this->_blocks, fn($block) => ((int)$block->level) > $maxLevels);

            if (!empty($tooHighBlocks)) {
                $this->addError($model, $attribute, $this->exceedsMaxLevels, ['maxLevels' => $this->maxLevels]);
            }
        }
    }

    /**
     * Sets default error messages for any error messages that have not already been set.
     */
    private function _setDefaultErrorMessages(): void
    {
        if ($this->tooManyTopBlocks === null) {
            $this->tooManyTopBlocks = Craft::t('neo', '{attribute} should contain at most {maxTopBlocks, number} top-level {maxTopBlocks, plural, one{block} other{blocks}}.');
        }

        if ($this->exceedsMaxLevels === null) {
            $this->exceedsMaxLevels = Craft::t('neo', '{attribute} blocks must not be nested deeper than level {maxLevels, number}.');
        }

        if ($this->tooFewBlocksOfType === null) {
            $this->tooFewBlocksOfType = Craft::t('neo', '{attribute} should contain at least {minBlockTypeBlocks, number} {minBlockTypeBlocks, plural, one{block} other{blocks}} of type {blockType}.');
        }

        if ($this->tooManyBlocksOfType === null) {
            $this->tooManyBlocksOfType = Craft::t('neo', '{attribute} should contain at most {maxBlockTypeBlocks, number} {maxBlockTypeBlocks, plural, one{block} other{blocks}} of type {blockType}.');
        }
    }
}
