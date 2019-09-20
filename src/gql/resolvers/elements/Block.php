<?php

namespace benf\neo\gql\resolvers\elements;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block as BlockElement;
use craft\gql\base\ElementResolver;

/**
 * Class Block
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class Block extends ElementResolver
{
	/**
	 * @inheritdoc
	 */
	public static function prepareQuery($source, array $arguments, $fieldName = null)
	{
		// If this is the beginning of a resolver chain, start fresh
		if ($source === null) {
			// get the first level
			$query = BlockElement::find()->level(1);
			
			// If not, get the prepared element query
		} else {
			$query = $source->$fieldName;
		}
		
		// If it's preloaded, it's preloaded.
		if (is_array($query)) {
			
			// if it's preloaded, return the first level of the neo field only (child elements will be retrieved using `children`.
			$newQuery = [];
			
			foreach ($query as $q) {
				if ((int)$q->level === 1) {
					$newQuery[] = $q;
				}
			}
			
			// if any level 1 blocks
			if (count($newQuery)) {
				return $newQuery;
			}
			
			return $query;
		}
		
		foreach ($arguments as $key => $value) {
			$query->$key($value);
		}
		
		return $query;
	}
}
