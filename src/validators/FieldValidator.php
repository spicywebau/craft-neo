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
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $this->_setDefaultErrorMessages();

        $value = $model->$attribute;

        // Check for max top-level blocks
        if ($this->maxTopBlocks !== null) {
            $topBlocks = array_filter($value->all(), function($block) {
                return (int)$block->level === 1;
            });

            if (count($topBlocks) > $this->maxTopBlocks) {
                $this->addError($model, $attribute, $this->tooManyTopBlocks, ['maxTopBlocks' => $this->maxTopBlocks]);
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
    }
}
