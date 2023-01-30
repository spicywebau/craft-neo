<?php

namespace benf\neo\elements\conditions;

use craft\elements\conditions\ElementCondition;
use craft\elements\conditions\LevelConditionRule;

/**
 * Class BlockCondition
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
class BlockCondition extends ElementCondition
{
    /**
     * @inheritdoc
     */
    protected function conditionRuleTypes(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            LevelConditionRule::class,
            OwnerCategoryGroupConditionRule::class,
            OwnerEntryTypeConditionRule::class,
            OwnerSectionConditionRule::class,
            OwnerTagGroupConditionRule::class,
            OwnerUserGroupConditionRule::class,
            OwnerVolumeConditionRule::class,
        ]);
    }
}
