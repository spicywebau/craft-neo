<?php

namespace benf\neo\elements\conditions\fields;

use Craft;
use craft\base\ElementInterface;

/**
 * Trait for field condition rules for parent Neo blocks.
 *
 * @package benf\neo\elements\conditions\fields
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.7.0
 */
trait ParentFieldConditionRuleTrait
{
    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $parentBlock = $element->getParent();

        // If no parent block, then disregard this rule
        return $parentBlock ? parent::matchElement($parentBlock) : true;
    }

    /**
     * @inheritdoc
     */
    public function getGroupLabel(): ?string
    {
        return Craft::t('neo', 'Parent block fields');
    }
}
