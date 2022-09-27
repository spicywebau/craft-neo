<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\entries\TypeConditionRule;
use craft\elements\Entry;

/**
 * Class OwnerEntryTypeConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
class OwnerEntryTypeConditionRule extends TypeConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner Entry Type');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $owner = $element->getOwner();
        return !($owner instanceof Entry) || parent::matchElement($owner);
    }
}
