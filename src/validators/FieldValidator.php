<?php

namespace benf\neo\validators;

use Craft;
use yii\validators\Validator;

/**
 * Class FieldValidator
 *
 * @package benf\neo\validators
 * @author Spicy Web <craft@spicyweb.com.au>
 * @since 2.3.0
 */
class FieldValidator extends Validator
{
    /**
     * @var int|null The maximum top-level blocks the field can have.  If not set, there is no top-level block limit.
     */
    public $maxTopBlocks;

    /**
     * @var int|null The maximum level blocks can be nested in the field.  If not set, there is no limit.
     */
    public $maxLevel;

    /**
     * @var string|null A user-defined error message to be used if the field's `maxTopBlocks` is exceeded.
     */
    public $tooManyTopBlocks;

    /**
     * @var string|null A user-defined error message to be used if the field's `maxLevel` is exceeded.
     */
    public $exceedsMaxLevel;

    /**
     * @var string|null A user-defined error message to be used if a block type's `maxBlocks` is exceeded.
     */
    public $tooManyBlocksOfType;

    /**
     * @var array of Neo blocks
     */
    private $_blocks = [];

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $this->_setDefaultErrorMessages();

        $field = $model->getFieldLayout()->getFieldByHandle(substr($attribute, 6));
        $value = $model->$attribute;
        $this->_blocks = $value->all();

        $this->_checkMaxTopLevelBlocks($model, $attribute);
        $this->_checkMaxLevel($model, $attribute);

        // Check for max blocks by block type
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

            if ($blockType->maxBlocks > 0 && $blockTypeCount > $blockType->maxBlocks) {
                $this->addError(
                    $model,
                    $attribute,
                    $this->tooManyBlocksOfType,
                    [
                        'maxBlockTypeBlocks' => $blockType->maxBlocks,
                        'blockType' => $blockType->name
                    ]
                );
            }
        }
    }

    /**
     * Adds an error if the field exceeds its max top-level blocks.
     */
    private function _checkMaxTopLevelBlocks($model, $attribute)
    {
        if ($this->maxTopBlocks !== null) {
            $topBlocks = array_filter($this->_blocks, function($block) {
                return (int)$block->level === 1;
            });

            if (count($topBlocks) > $this->maxTopBlocks) {
                $this->addError($model, $attribute, $this->tooManyTopBlocks, ['maxTopBlocks' => $this->maxTopBlocks]);
            }
        }
    }

    /**
     * Adds an error if the field exceeds its max level.
     */
    private function _checkMaxLevel($model, $attribute)
    {
        $maxLevel = $this->maxLevel;

        if ($maxLevel !== null) {
            $tooHighBlocks = array_filter($this->_blocks, function($block) use($maxLevel) {
                return ((int)$block->level) > $maxLevel;
            });

            if (!empty($tooHighBlocks)) {
                $this->addError($model, $attribute, $this->exceedsMaxLevel, ['maxLevel' => $this->maxLevel]);
            }
        }
    }

    /**
     * Sets default error messages for any error messages that have not already been set.
     */
    private function _setDefaultErrorMessages()
    {
        if ($this->tooManyTopBlocks === null) {
            $this->tooManyTopBlocks = Craft::t('neo', '{attribute} should contain at most {maxTopBlocks, number} top-level {maxTopBlocks, plural, one{block} other{blocks}}.');
        }

        if ($this->exceedsMaxLevel === null) {
            $this->exceedsMaxLevel = Craft::t('neo', '{attribute} has a max level of {maxLevel, number}, but has blocks exceeding that level.');
        }

        if ($this->tooManyBlocksOfType === null) {
            $this->tooManyBlocksOfType = Craft::t('neo', '{attribute} should contain at most {maxBlockTypeBlocks, number} {maxBlockTypeBlocks, plural, one{block} other{blocks}} of type {blockType}.');
        }
    }
}
