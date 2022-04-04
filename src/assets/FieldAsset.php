<?php

namespace benf\neo\assets;

use craft\web\AssetBundle;

/**
 * Class FieldAsset
 *
 * @package benf\neo\assets
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.0.0
 * @deprecated in 3.0.0; users of `EVENT_FILTER_BLOCK_TYPES` should use `InputAsset` instead
 */
class FieldAsset extends AssetBundle
{
    public const EVENT_FILTER_BLOCK_TYPES = 'filterBlockTypes';
}
