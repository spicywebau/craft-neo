<?php

namespace benf\neo\elements\conditions;

use craft\elements\db\ElementQueryInterface;

/**
 * A trait used by all Neo block owner condition rule classes.
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
trait OwnerConditionRuleTrait
{
    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        // Not used by Neo
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        // Not used by Neo
    }
}
