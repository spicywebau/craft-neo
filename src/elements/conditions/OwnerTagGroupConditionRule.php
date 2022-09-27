<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\tags\GroupConditionRule;
use craft\elements\Tag;

/**
 * Class OwnerTagGroupConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
class OwnerTagGroupConditionRule extends GroupConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner Tag Group');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $owner = $element->getOwner();
        return !($owner instanceof Tag) || parent::matchElement($owner);
    }
}
