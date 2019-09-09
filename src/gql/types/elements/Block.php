<?php

namespace benf\neo\gql\types\elements;


use benf\neo\Plugin as Neo;
use benf\neo\elements\Block as BlockElement;
use benf\neo\gql\interfaces\elements\Block as NeoBlockInterface;

use craft\gql\interfaces\Element as ElementInterface;
use craft\gql\base\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class MatrixBlock
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class Block extends ObjectType
{
	/**
	 * @inheritdoc
	 */
	public function __construct(array $config)
	{
		$config['interfaces'] = [
			NeoBlockInterface::getType(),
			ElementInterface::getType(),
		];
		
		parent::__construct($config);
	}
	
	/**
	 * @inheritdoc
	 */
	protected function resolve($source, $arguments, $context, ResolveInfo $resolveInfo)
	{
		$fieldName = $resolveInfo->fieldName;
		
		if ($fieldName === 'typeHandle') {
			return $source->getType()->handle;
		}
		
		return $source->$fieldName;
	}
	
}
