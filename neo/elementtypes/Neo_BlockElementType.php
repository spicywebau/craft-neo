<?php
namespace Craft;

/**
 * The Neo_BlockElementType class is responsible for implementing and defining Neo blocks as a native element type
 * in Craft.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://craftcms.com/license Craft License Agreement
 * @see       http://craftcms.com
 * @package   craft.app.elementtypes
 * @since     1.3
 */
class Neo_BlockElementType extends BaseElementType
{
	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t('Neo Blocks');
	}

	/**
	 * @inheritDoc IElementType::hasContent()
	 *
	 * @return bool
	 */
	public function hasContent()
	{
		return true;
	}

	/**
	 * @inheritDoc IElementType::isLocalized()
	 *
	 * @return bool
	 */
	public function isLocalized()
	{
		return true;
	}

	/**
	 * @inheritDoc IElementType::defineCriteriaAttributes()
	 *
	 * @return array
	 */
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

	/**
	 * @inheritDoc IElementType::getContentTableForElementsQuery()
	 *
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return string
	 */
	public function getContentTableForElementsQuery(ElementCriteriaModel $criteria)
	{
		if (!$criteria->fieldId && $criteria->id && is_numeric($criteria->id))
		{
			$criteria->fieldId = craft()->db->createCommand()
				->select('fieldId')
				->from('neoblocks')
				->where('id = :id', array(':id' => $criteria->id))
				->queryScalar();
		}

		if ($criteria->fieldId && is_numeric($criteria->fieldId))
		{
			$neoField = craft()->fields->getFieldById($criteria->fieldId);

			if ($neoField)
			{
				return craft()->neo->getContentTableName($neoField);
			}
		}
	}

	/**
	 * @inheritDoc IElementType::getFieldsForElementsQuery()
	 *
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return FieldModel[]
	 */
	public function getFieldsForElementsQuery(ElementCriteriaModel $criteria)
	{
		$blockTypes = craft()->neo->getBlockTypesByFieldId($criteria->fieldId);

		// Preload all of the fields up front to save ourselves some DB queries, and discard
		$contexts = array();

		foreach ($blockTypes as $blockType)
		{
			$contexts[] = 'neoBlockType:'.$blockType->id;
		}

		craft()->fields->getAllFields(null, $contexts);

		// Now assemble the actual fields list
		$fields = array();

		foreach ($blockTypes as $blockType)
		{
			$fieldColumnPrefix = 'field_'.$blockType->handle.'_';

			foreach ($blockType->getFields() as $field)
			{
				$field->columnPrefix = $fieldColumnPrefix;
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * @inheritDoc IElementType::modifyElementsQuery()
	 *
	 * @param DbCommand            $query
	 * @param ElementCriteriaModel $criteria
	 *
	 * @return mixed
	 */
	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
		$query
			->addSelect('neoblocks.fieldId, neoblocks.ownerId, neoblocks.ownerLocale, neoblocks.typeId, neoblocks.sortOrder')
			->join('neoblocks neoblocks', 'neoblocks.id = elements.id');

		if ($criteria->fieldId)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.fieldId', $criteria->fieldId, $query->params));
		}

		if ($criteria->ownerId)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.ownerId', $criteria->ownerId, $query->params));
		}

		if ($criteria->ownerLocale)
		{
			$query->andWhere(DbHelper::parseParam('neoblocks.ownerLocale', $criteria->ownerLocale, $query->params));
		}

		if ($criteria->type)
		{
			$query->join('neoblocktypes neoblocktypes', 'neoblocktypes.id = neoblocks.typeId');
			$query->andWhere(DbHelper::parseParam('neoblocktypes.handle', $criteria->type, $query->params));
		}
	}

	/**
	 * @inheritDoc IElementType::populateElementModel()
	 *
	 * @param array $row
	 *
	 * @return array
	 */
	public function populateElementModel($row)
	{
		return Neo_BlockModel::populateModel($row);
	}
}
