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
			'structureId' => AttributeType::Number,
			'collapsed'   => [AttributeType::String, 'default' => 'neoblocks.collapsed'],
			'ownerId'     => AttributeType::Number,
			'ownerLocale' => AttributeType::Locale,
			'type'        => AttributeType::Mixed,
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
				'structures structures',
				'structures.id = neoblocks.structureId'
			)
			->leftJoin(
				'structureelements structureelements',
				[
					'and',
					'structureelements.structureId = structures.id',
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
