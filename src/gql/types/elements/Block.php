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
			ElementInterface::getType()
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
		
		if ($fieldName === 'children') {
			
			$newBlocks = [];
			$blocks = $source->$fieldName;
			$sourceLevel = (int)$source->level + 1;
			
			// blocks array cannot be trusted. it will most likely be out of order and cached.
			// we should retrieve the children blocks by query instead so it'll always be in the correct order.
			
			// -- old comment --
			// because of how the children is retrieve the blocks are located in the parent,
			// which is why we now have to retrieve them by query
			// if there's none return the default.
			// -- old comment --
			$children = $source->getDescendants()->level($sourceLevel)->all();
			
			if(count($children) and is_array($children)) {
				
				foreach ($children as $block) {
					if ((int)$block->level === $sourceLevel) {
						$newBlocks[] = $block;
					}
				}
			}
			
			if (count($newBlocks)) {
				return $newBlocks;
			}
			
		}
		
		return $source->$fieldName;
	}
	
}
