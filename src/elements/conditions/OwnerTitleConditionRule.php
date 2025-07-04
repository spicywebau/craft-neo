<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\TitleConditionRule;

/**
 * Class OwnerTitleConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 5.5.0
 */
class OwnerTitleConditionRule extends TitleConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner Title');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->_matchElement($element);
    }
}
