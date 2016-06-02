<?php
namespace Craft;

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
			->leftJoin(
				'neoblockstructures neoblockstructures',
				[
					'and',
					'neoblockstructures.ownerId = neoblocks.ownerId',
					'neoblockstructures.fieldId = neoblocks.fieldId',
					[
						'or',
						'neoblockstructures.ownerLocale = neoblocks.ownerLocale',
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

		if($criteria->type)
		{
			$query->join('neoblocktypes neoblocktypes', 'neoblocktypes.id = neoblocks.typeId');
			$query->andWhere(DbHelper::parseParam('neoblocktypes.handle', $criteria->type, $query->params));
		}
	}

	public function populateElementModel($row)
	{
		return Neo_BlockModel::populateModel($row);
	}
}
