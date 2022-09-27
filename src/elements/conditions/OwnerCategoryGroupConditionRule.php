<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\categories\GroupConditionRule;
use craft\elements\Category;

/**
 * Class OwnerCategoryGroupConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
class OwnerCategoryGroupConditionRule extends GroupConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner Category Group');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $owner = $element->getOwner();
        return !($owner instanceof Category) || parent::matchElement($owner);
    }
}
