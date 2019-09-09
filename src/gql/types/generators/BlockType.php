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
				
				$contentFields = $blockType->getFields();
				$contentFieldGqlTypes = [];
				
				/** @var Field $contentField */
				foreach ($contentFields as $contentField) {
					$contentFieldGqlTypes[$contentField->handle] = $contentField->getContentGqlType();
				}
				
				$blockTypeFields = array_merge(NeoBlockInterface::getFieldDefinitions(), $contentFieldGqlTypes);
				
				// // Generate a type for each entry type
				$entity = GqlEntityRegistry::createEntity($typeName, new Block([
					'name' => $typeName,
					'fields' => function() use ($blockTypeFields) {
						return $blockTypeFields;
					}
				]));
			}
			
			$gqlTypes[$typeName] = $entity;
		}
		
		return $gqlTypes;
	}
}
