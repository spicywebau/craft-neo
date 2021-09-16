<?php

namespace benf\neo\models;

use craft\base\Model;

/**
 * Class Settings
 *
 * @package benf\neo\models
 * @author Spicy Web <craft@spicyweb.com.au>
 * @since 2.3.0
 */
class Settings extends Model
{
    /**
     * @var bool
     */
    public $collapseAllBlocks = false;

    /**
     * @var bool
     * @since 2.10.0
     */
    public $optimiseSearchIndexing = true;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['collapseAllBlocks', 'optimiseSearchIndexing'], 'boolean'],
        ];
    }
}
