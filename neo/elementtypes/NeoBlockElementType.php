<?php
namespace Craft;

class NeoBlockElementType extends BaseElementType
{
	public function getName()
	{
		return Craft::t('Neo Blocks');
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
		return array(
			'fieldId'     => AttributeType::Number,
			'order'       => array(AttributeType::String, 'default' => 'neoblocks.sortOrder'),
			'ownerId'     => AttributeType::Number,
			'ownerLocale' => AttributeType::Locale,
			'type'        => AttributeType::Mixed,
		);
	}

	public function getContentTableForElementsQuery(ElementCriteriaModel $criteria)
	{
		if(!$criteria->fieldId && $criteria->id && is_numeric($criteria->id))
		{
			$criteria->fieldId = craft()->db->createCommand()
				->select('fieldId')
				->from('neoblocks')
				->where('id = :id', array(':id' => $criteria->id))
				->queryScalar();
		}

		if($criteria->fieldId && is_numeric($criteria->fieldId))
		{
			$neoField = craft()->fields->getFieldById($criteria->fieldId);

			if($neoField)
			{
				return craft()->neo->getContentTableName($neoField);
			}
		}

		return null;
	}

	public function getFieldsForElementsQuery(ElementCriteriaModel $criteria)
	{
		$blockTypes = craft()->neo->getBlockTypesByFieldId($criteria->fieldId);

		// Preload all of the fields up front to save ourselves some DB queries, and discard
		craft()->fields->getAllFields(null, 'global');

		// Now assemble the actual fields list
		$fields = array();

		foreach($blockTypes as $blockType)
		{
			$fieldColumnPrefix = 'field_' . $blockType->handle . '_';

			foreach($blockType->getFields() as $field)
			{
				$field->columnPrefix = $fieldColumnPrefix;
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('neoblocks.fieldId, neoblocks.ownerId, neoblocks.ownerLocale, neoblocks.typeId, neoblocks.sortOrder')
			->join('neoblocks neoblocks', 'neoblocks.id = elements.id');

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
		return NeoBlockModel::populateModel($row);
	}
}
