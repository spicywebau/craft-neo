<?php

namespace benf\neo\events;

use benf\neo\Field;
use craft\base\ElementInterface;

class FilterBlockTypesEvent extends \yii\base\Event
{
    /** @var Field */
    public $field;

    /** @var ElementInterface */
    public $element;

    /** @var array */
    public $blockTypes;

    /** @var array */
    public $blockTypeGroups;
}
