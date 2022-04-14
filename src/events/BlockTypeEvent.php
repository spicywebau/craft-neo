<?php

namespace benf\neo\events;

use benf\neo\models\BlockType;
use yii\base\Event;

/**
 * Class BlockTypeEvent
 *
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.3.0
 */
class BlockTypeEvent extends Event
{
    /**
     * @var BlockType
     */
    public BlockType $blockType;

    /**
     * @var bool
     */
    public bool $isNew = false;
}
