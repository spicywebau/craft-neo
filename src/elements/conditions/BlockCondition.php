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
    protected function selectableConditionRules(): array
    {
        $parentConditionRuleTypes = parent::conditionRuleTypes();
        $fieldConditionRuleTypes = [];

        foreach ($parentConditionRuleTypes as $ruleType) {
            if (!isset($ruleType['class'])) {
                continue;
            }

            $splitClass = explode('\\', $ruleType['class']);
            $className = __NAMESPACE__ . '\\fields\\Parent' . end($splitClass);

            if (class_exists($className)) {
                $fieldConditionRuleTypes[] = [
                    'class' => $className,
                    'fieldUid' => $ruleType['fieldUid'],
                ];
            }
        }

        return array_merge(
            $parentConditionRuleTypes,
            $fieldConditionRuleTypes,
            [
                LevelConditionRule::class,
                OwnerCategoryGroupConditionRule::class,
                OwnerEntryTypeConditionRule::class,
                OwnerSectionConditionRule::class,
                OwnerTagGroupConditionRule::class,
                OwnerUserGroupConditionRule::class,
                OwnerVolumeConditionRule::class,
            ],
        );
    }
}
