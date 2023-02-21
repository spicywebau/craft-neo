<?php

namespace benf\neo\models;

use benf\neo\enums\NewBlockMenuStyle;
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
     * @var string
     * @since 3.6.0
     */
    public string $newBlockMenuStyle = NewBlockMenuStyle::Classic;

    /**
     * @var string|array|null The asset sources block type icons can be selected from.
     * @since 3.6.0
     */
    public string|array|null $blockTypeIconSources = '*';

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [
            [
                'collapseAllBlocks',
                'optimiseSearchIndexing',
                'defaultAlwaysShowGroupDropdowns',
            ],
            'boolean',
        ];
        $rules[] = [
            [
                'newBlockMenuStyle',
            ],
            'in',
            'range' => [
                NewBlockMenuStyle::Classic,
                NewBlockMenuStyle::Grid,
                NewBlockMenuStyle::List,
            ],
        ];

        return $rules;
    }
}
