<?php

namespace benf\neo\models;

use craft\base\Model;

/**
 * Class Settings
 *
 * @package benf\neo\models
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.3.0
 */
class Settings extends Model
{
    /**
     * @var bool
     */
    public bool $collapseAllBlocks = false;

    /**
     * @var bool
     * @since 2.10.0
     */
    public bool $optimiseSearchIndexing = true;

    /**
     * @var bool
     * @since 3.0.0
     */
    public bool $defaultAlwaysShowGroupDropdowns = true;

    /**
     * @var bool
     * @since 3.6.0
     */
    public bool $useNewBlockGrid = false;

    /**
     * @var string|array|null The asset sources block type icons can be selected from, if using the new block grid.
     * @since 3.6.0
     */
    public string|array|null $blockTypeIconSources = '*';

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [
                [
                    'collapseAllBlocks',
                    'optimiseSearchIndexing',
                    'defaultAlwaysShowGroupDropdowns',
                    'useNewBlockGrid',
                ],
                'boolean',
            ],
        ];
    }
}
