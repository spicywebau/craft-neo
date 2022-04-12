<?php

namespace benf\neo\events;

use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use craft\base\ElementInterface;

class FilterBlockTypesEvent extends \yii\base\Event
{
    /**
     * @var Field
     */
    public Field $field;

    /**
     * @var ElementInterface
     */
    public ElementInterface $element;

    /**
     * @var BlockType[]
     */
    public array $blockTypes;

    /**
     * @var BlockTypeGroup[]
     */
    public array $blockTypeGroups;
}
