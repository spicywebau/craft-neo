<?php
namespace Craft;

/**
 * Class Neo_BlockCacheDependency
 *
 * @package Craft
 */
class Neo_BlockCacheDependency implements \ICacheDependency
{
	// Private properties

	private static $_memoizedFieldDates;

	private $_blockTypeId;
	private $_blockTypeDate;
	private $_blockId;
	private $_blockDate;
	private $_fieldDates;


	// Public methods

	/**
	 * @param Neo_BlockTypeModel $blockType
	 * @param Neo_BlockModel|null $block
	 */
	public function __construct(Neo_BlockTypeModel $blockType, Neo_BlockModel $block = null)
	{
		$this->_blockTypeId = $blockType->id;
		$this->_blockTypeDate = $blockType->dateUpdated->getTimestamp();

		if($block)
		{
			$this->_blockId = $block->id;
			$this->_blockDate = $block->dateUpdated->getTimestamp();
		}

		$this->_fieldDates = $this->_getFieldDates($blockType);
	}

	/**
	 * Evaluates the dependency by generating and saving the data related with dependency.
	 * This method doesn't need to do anything in this instance as all the data needed is provided in the constructor.
	 * It still must be implemented though as the `ICacheDependency` interface requires it.
	 */
	public function evaluateDependency() {}

	/**
	 * Returns whether the cached block/block type has become invalid.
	 *
	 * @return bool
	 */
	public function getHasChanged()
	{
		$blockType = craft()->neo->getBlockTypeById($this->_blockTypeId);

		if($this->_blockTypeDate != $blockType->dateUpdated->getTimestamp())
		{
			return true;
		}

		if($this->_blockId)
		{
			$block = craft()->neo->getBlockById($this->_blockId);

			if($this->_blockDate != $block->dateUpdated->getTimestamp())
			{
				return true;
			}
		}

		$fieldDates = $this->_getFieldDates($blockType);

		foreach($fieldDates as $fieldId => $fieldDate)
		{
			$currentFieldDate = $this->_fieldDates[$fieldId];

			if($currentFieldDate != $fieldDate)
			{
				return true;
			}
		}

		return false;
	}


	// Private methods

	/**
	 * Returns all fields' last modified timestamps, indexed by the field's ID.
	 *
	 * @return array
	 */
	private static function _getAllFieldDates()
	{
		if(!self::$_memoizedFieldDates)
		{
			$fieldDates = [];
			$results = craft()->db->createCommand()
				->select('f.id, f.dateUpdated')
				->from('fields f')
				->queryAll();

			foreach($results as $result)
			{
				$id = $result['id'];
				$date = DateTime::createFromString($result['dateUpdated']);

				$fieldDates[$id] = $date->getTimestamp();
			}

			self::$_memoizedFieldDates = $fieldDates;
		}

		return self::$_memoizedFieldDates;
	}

	/**
	 * Returns an array of last modified timestamps for each field on a block type, indexed by the field's ID.
	 *
	 * @param Neo_BlockTypeModel $blockType
	 * @return array
	 */
	private function _getFieldDates(Neo_BlockTypeModel $blockType)
	{
		$fields = $blockType->getFieldLayout()->getFields();
		$allFieldDates = self::_getAllFieldDates();
		$fieldDates = [];

		foreach($fields as $field)
		{
			$id = $field->fieldId;
			$fieldDates[$id] = $allFieldDates[$id];
		}

		return $fieldDates;
	}
}