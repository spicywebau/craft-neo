<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\users\GroupConditionRule;
use craft\elements\User;

/**
 * Class OwnerUserGroupConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
class OwnerUserGroupConditionRule extends GroupConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner User Group');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $owner = $element->getOwner();
        return !($owner instanceof User) || parent::matchElement($owner);
    }
}
