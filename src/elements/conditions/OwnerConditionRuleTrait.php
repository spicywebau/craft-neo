<?php

namespace benf\neo\elements\conditions;

use benf\neo\elements\Block;
use craft\elements\db\ElementQueryInterface;

/**
 * A trait used by all Neo block owner condition rule classes.
 *
 * @package benf\neo\elements\conditions
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.4.0
 */
trait OwnerConditionRuleTrait
{
    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        // Not used by Neo
        return [];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        // Not used by Neo
    }

    /**
     * Returns whether a Neo block matches condition rules based on its owner element.
     *
     * @var Block $element
     * @var string $ownerType The expected element type of the block's owner
     * @return bool
     */
    private function _matchElement(Block $element, string $ownerType): bool
    {
        $owner = $element->ownerId !== null ? $element->getOwner() : null;
        return $owner === null || $owner::class !== $ownerType || parent::matchElement($owner);
    }
}
