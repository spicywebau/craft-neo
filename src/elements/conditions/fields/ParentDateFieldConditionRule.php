<?php

namespace benf\neo\elements\conditions\fields;

use craft\fields\conditions\DateFieldConditionRule;

/**
 * Date field condition rule for parent Neo blocks.
 *
 * @package benf\neo\elements\conditions\fields
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.7.0
 */
class ParentDateFieldConditionRule extends DateFieldConditionRule
{
    use ParentFieldConditionRuleTrait;
}
