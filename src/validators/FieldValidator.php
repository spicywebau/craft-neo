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
     * @var string|null A user-defined error message to be used if the field's `maxTopBlocks` is exceeded.
     */
    public $tooManyTopBlocks;

    /**
     * @var string|null A user-defined error message to be used if a block type's `maxBlocks` is exceeded.
     */
    public $tooManyBlocksOfType;

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $this->_setDefaultErrorMessages();

        $field = $model->getFieldLayout()->getFieldByHandle(substr($attribute, 6));
        $value = $model->$attribute;
        $blocks = $value->all();

        // Check for max top-level blocks
        if ($this->maxTopBlocks !== null) {
            $topBlocks = array_filter($blocks, function($block) {
                return (int)$block->level === 1;
            });

            if (count($topBlocks) > $this->maxTopBlocks) {
                $this->addError($model, $attribute, $this->tooManyTopBlocks, ['maxTopBlocks' => $this->maxTopBlocks]);
            }
        }

        // Check for max blocks by block type
        $blockTypesCount = [];
        $blockTypesById = [];

        foreach ($field->getBlockTypes() as $blockType) {
            $blockTypesCount[$blockType->id] = 0;
            $blockTypesById[$blockType->id] = $blockType;
        }

        foreach ($blocks as $block) {
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
     * Sets default error messages for any error messages that have not already been set.
     */
    private function _setDefaultErrorMessages()
    {
        if ($this->tooManyTopBlocks === null) {
            $this->tooManyTopBlocks = Craft::t('neo', '{attribute} should contain at most {maxTopBlocks, number} top-level {maxTopBlocks, plural, one{block} other{blocks}}.');
        }

        if ($this->tooManyBlocksOfType === null) {
            $this->tooManyBlocksOfType = Craft::t('neo', '{attribute} should contain at most {maxBlockTypeBlocks, number} {maxBlockTypeBlocks, plural, one{block} other{blocks}} of type {blockType}.');
        }
    }
}
