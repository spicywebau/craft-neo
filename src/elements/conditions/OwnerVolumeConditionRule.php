<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\assets\VolumeConditionRule;
use craft\elements\Asset;

/**
 * Class OwnerVolumeConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
class OwnerVolumeConditionRule extends VolumeConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner Volume');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $owner = $element->getOwner();
        return !($owner instanceof Asset) || parent::matchElement($owner);
    }
}
