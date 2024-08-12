<?php

namespace benf\neo\elements\conditions;

use benf\neo\Plugin as Neo;
use craft\elements\conditions\ElementCondition;
use craft\elements\conditions\LevelConditionRule;
use craft\models\FieldLayout;

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
        $parentConditionRuleTypes = parent::selectableConditionRules();
        $fieldConditionRuleTypes = [];

        // Get all field layouts associated with this object's associated Neo field(s), then temporarily replace this
        // object's field layouts so we get all possible parent block condition rules
        $layoutBlockTypes = Neo::$plugin->blockTypes->getByCriteria([
            'fieldLayoutId' => array_map(fn($layout) => $layout->id, $this->getFieldLayouts()),
        ]);
        $fieldBlockTypes = Neo::$plugin->blockTypes->getByCriteria([
            'fieldId' => array_values(array_unique(array_map(fn($blockType) => $blockType->fieldId, $layoutBlockTypes))),
        ]);
        $fieldLayouts = array_map(fn($blockType) => $blockType->getFieldLayout(), $fieldBlockTypes);
        $fieldConditionRuleTypes = array_values(array_filter(array_map(
            function($ruleType) {
                if (!isset($ruleType['class'])) {
                    return null;
                }

                $splitClass = explode('\\', $ruleType['class']);
                $className = __NAMESPACE__ . '\\fields\\Parent' . end($splitClass);

                if (class_exists($className)) {
                    return [
                        'class' => $className,
                        'fieldUid' => $ruleType['fieldUid'],
                        'layoutElementUid' => $ruleType['layoutElementUid'],
                    ];
                }
            },
            $this->_swapFieldLayoutsWithThen(
                $fieldLayouts,
                fn() => parent::selectableConditionRules(),
            ),
        )));

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

    /**
     * Temporarily swaps the condition field layouts before calling a given function.
     *
     * @param FieldLayout[]|null $with An array of field layouts to swap with the condition field layouts
     * @param callable $then A function to run while the condition field layouts are swapped
     * @return mixed The return value from $then
     */
    private function _swapFieldLayoutsWithThen(?array $with, callable $then): mixed
    {
        if ($with) {
            $fieldLayouts = $this->getFieldLayouts();
            $this->setFieldLayouts($with);
            $returnVal = $then();
            $this->setFieldLayouts($fieldLayouts);

            return $returnVal;
        }

        return $then();
    }
}
