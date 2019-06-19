<?php
namespace benf\neo\events;

use yii\base\Event;

use benf\neo\models\BlockType;

/**
 * Class BlockTypeEvent
 *
 * @author Spicy Web <craft@spicyweb.com.au>
 * @since 2.3.0
 */
Class BlockTypeEvent extends Event
{
    /**
     * @var BlockType
     */
    public $blockType;

    /**
     * @var bool
     */
    public $isNew = false;
}
