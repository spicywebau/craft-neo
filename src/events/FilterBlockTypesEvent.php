<?php

namespace benf\neo\events;

use benf\neo\Field;

class FilterBlockTypesEvent extends \yii\base\Event
{
    /** @var Field */
    public $field;

    /** @var array */
    public $blockTypes;

    /** @var array */
    public $blockTypeGroups;
}
