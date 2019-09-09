<?php

namespace benf\neo\gql\resolvers\elements;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block as BlockElement;
use craft\gql\base\ElementResolver;

/**
 * Class MatrixBlock
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
			$query = BlockElement::find();
			// If not, get the prepared element query
		} else {
			$query = $source->$fieldName;
		}
		
		// If it's preloaded, it's preloaded.
		if (is_array($query)) {
			return $query;
		}
		
		foreach ($arguments as $key => $value) {
			$query->$key($value);
		}
		
		return $query;
	}
}
