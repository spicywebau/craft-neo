<?php

namespace benf\neo\elements\conditions;

use Craft;
use craft\base\ElementInterface;
use craft\elements\conditions\DateCreatedConditionRule;

/**
 * Class OwnerDateCreatedConditionRule
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 5.5.0
 */
class OwnerDateCreatedConditionRule extends DateCreatedConditionRule
{
    use OwnerConditionRuleTrait;

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('neo', 'Owner Date Created');
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        return $this->_matchElement($element);
    }
}
