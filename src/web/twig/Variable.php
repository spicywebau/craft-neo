<?php

namespace benf\neo\web\twig;

use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;
use Craft;

/**
 * Class Variable
 *
 * @package benf\neo\web\twig
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 5.0.0
 */
class Variable
{
    /**
     * Get Neo blocks on their own, without requiring an owner element.
     *
     * If possible, avoid using this function. Neo blocks are supposed to be tied explicitly to elements, and the use of
     * this function ignores this relationship. Using this function can open you up to all kinds of performance and
     * unexpected behavioural issues if you're not careful.
     *
     * @param array|null $criteria
     * @return BlockQuery
     */
    public function blocks(?array $criteria = null): BlockQuery
    {
        return Craft::configure(Block::find(), ($criteria ?? []));
    }
}
