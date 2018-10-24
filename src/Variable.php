<?php
namespace benf\neo;

use benf\neo\elements\Block;
use benf\neo\elements\db\BlockQuery;

/**
 * Class Variable
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
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
	public function blocks(array $criteria = null): BlockQuery
	{
		$query = Block::find();

		if ($criteria !== null)
		{
			foreach ($criteria as $param => $value)
			{
				$query->$param = $value;
			}
		}

		return $query;
	}
}
