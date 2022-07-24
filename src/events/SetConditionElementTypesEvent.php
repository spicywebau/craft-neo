<?php

namespace benf\neo\events;

use yii\base\Event;

/**
 * Class SetConditionElementTypesEvent
 *
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.2.0
 */
class SetConditionElementTypesEvent extends Event
{
    /**
     * @var string[]
     */
    public array $elementTypes;
}
