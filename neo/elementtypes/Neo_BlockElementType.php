<?php
namespace Craft;

/**
 * Class Neo_BlockElementType
 *
 * @package Craft
 */
class Neo_BlockElementType extends BaseElementType
{
	public function getName()
	{
		return Craft::t("Neo Blocks");
	}

	public function hasContent()
	{
		return true;
	}

	public function isLocalized()
	{
		return true;
	}

	public function defineCriteriaAttributes()
	{
		return [
			'fieldId'     => AttributeType::Number,
			'collapsed'   => [AttributeType::String, 'default' => 'neoblocks.collapsed'],
			'ownerId'     => AttributeType::Number,
			'ownerLocale' => AttributeType::Locale,
			'typeId'      => AttributeType::Number,
			'type'        => AttributeType::Mixed,
			'order'       => [AttributeType::String, 'default' => 'lft'],
		];
	}

	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('
				neoblocks.fieldId,
				neoblocks.ownerId,
				neoblocks.ownerLocale,
				neoblocks.typeId,
				neoblocks.collapsed
			')
			->join(
				'neoblocks neoblocks',
				'neoblocks.id = elements.id'
			)
			// Join structural information, such as level and order properties, to blocks
			->leftJoin(
				'neoblockstructures neoblockstructures',
				[
					'and',
					'neoblockstructures.ownerId = neoblocks.ownerId',
					'neoblockstructures.fieldId = neoblocks.fieldId',
					[
						'or',
						'neoblockstructures.ownerLocale = neoblocks.ownerLocale',

						// If there is no locale set (in other words, `ownerLocale` is `null`), then the above
						// comparison will not be true for some reason. So if it's not evaluated to true, then check
						// to see if both `ownerLocale` properties are `null`.
						[
							'and',
							'neoblockstructures.ownerLocale is null',
							'neoblocks.ownerLocale is null',
						],
					],
				]
			)
			->leftJoin(
				'structureelements structureelements',
				[
					'and',
					'structureelements.structureId = neoblockstructures.structureId',
					'structureelements.elementId = neoblocks.id',
				]
			);

		if($criteria->fieldId)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.fieldId', $criteria->fieldId, $query->params));
		}

		if($criteria->ownerId)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.ownerId', $criteria->ownerId, $query->params));
		}

		if($criteria->ownerLocale)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.ownerLocale', $criteria->ownerLocale, $query->params));
		}

		if($criteria->typeId)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.typeId', $criteria->typeId, $query->params));
		}
		else if($criteria->type)
		{
			$query->join('neoblocktypes neoblocktypes', 'neoblocktypes.id = neoblocks.typeId');
			$query->andWhere(DbHelper::parseParam('neoblocktypes.handle', $criteria->type, $query->params));
		}
	}

	public function populateElementModel($row)
	{
		return Neo_BlockModel::populateModel($row);
	}

	/**
	 * @inheritDoc IElementType::getEagerLoadingMap()
	 *
	 * @param BaseElementModel[] $sourceElements
	 * @param string $handle
	 * @return array|false
	 */
	public function getEagerLoadingMap($sourceElements, $handle)
	{
		// $handle *must* be set as "blockTypeHandle:fieldHandle" so we know _which_ myRelationalField to resolve to
		$handleParts = explode(':', $handle);

		if(count($handleParts) != 2)
		{
			return false;
		}

		list($blockTypeHandle, $fieldHandle) = $handleParts;

		// Get the block type
		$neoFieldId = $sourceElements[0]->fieldId;
		$blockTypes = craft()->neo->getBlockTypesByFieldId($neoFieldId, 'handle');

		if(!isset($blockTypes[$blockTypeHandle]))
		{
			// Not a valid block type handle (assuming all $sourceElements are blocks from the same Neo field)
			return false;
		}

		return parent::getEagerLoadingMap($sourceElements, $fieldHandle);
	}
}
