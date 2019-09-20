<?php

namespace benf\neo\gql\types\generators;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block as BlockElement;
use benf\neo\gql\interfaces\elements\Block as NeoBlockInterface;
use benf\neo\gql\types\elements\Block;
use benf\neo\models\BlockType as NeoBlockTypeModel;

use Craft;
use craft\base\Field;
use craft\gql\base\GeneratorInterface;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class BlockType
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3.0
 */
class BlockType implements GeneratorInterface
{
	/**
	 * @inheritdoc
	 */
	public static function generateTypes($context = null): array
	{
		if ($context) {
			$blockTypes = $context->getBlockTypes();
		} else {
			$blockTypes = Neo::$plugin->blockTypes->getAllBlockTypes();
		}
		
		$gqlTypes = [];
		
		foreach ($blockTypes as $blockType) {
			
			$typeName = BlockElement::gqlTypeNameByContext($blockType);
			
			if (!($entity = GqlEntityRegistry::getEntity($typeName))) {
				
				// // Generate a type for each
				$entity = self::generateType($blockType, $blockTypes);
			}
			
			$gqlTypes[$typeName] = $entity;
		}
		
		return $gqlTypes;
	}
	
	private static function generateType($blockType, $blockTypes)
	{
		$typeName = BlockElement::gqlTypeNameByContext($blockType);
		
		$contentFields = $blockType->getFields();
		$contentFieldGqlTypes = [];
		
		/** @var Field $contentField */
		foreach ($contentFields as $contentField) {
			$contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
		}
		
		if (!empty($blockType->childBlocks)) {
			$contentFieldGqlTypes['children'] = [
				'name' => 'children',
				'type' => Type::listOf(NeoBlockInterface::getType()),
				'description' => 'The children block types for this block',
			];
		}
		
		$blockTypeFields = array_merge(NeoBlockInterface::getFieldDefinitions(), $contentFieldGqlTypes);
		
		return GqlEntityRegistry::createEntity($typeName, new Block([
			'name' => $typeName,
			'fields' => static function () use ($blockTypeFields) {
				return $blockTypeFields;
			}
		]));
	}
}
