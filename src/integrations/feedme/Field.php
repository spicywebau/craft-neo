<?php

namespace benf\neo\integrations\feedme;

use benf\neo\Field as NeoField;
use craft\feedme\fields\Matrix;

/**
 * Neo field class for Feed Me.
 *
 * @package benf\neo\integrations\feedme
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.5.0
 */
class Field extends Matrix
{
    /**
     * @inheritdoc
     */
    public static string $name = 'Neo';

    /**
     * @inheritdoc
     */
    public static string $class = NeoField::class;
}
