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

	private $_blockTypeId;
	private $_blockTypeDate;
	private $_blockId;
	private $_blockDate;


	// Public methods

	public function __construct(Neo_BlockTypeModel $blockType, Neo_BlockModel $block = null)
	{
		$this->_blockTypeId = $blockType->id;
		$this->_blockTypeDate = $blockType->dateUpdated->getTimestamp();

		if($block)
		{
			$this->_blockId = $block->id;
			$this->_blockDate = $block->dateUpdated->getTimestamp();
		}
	}

	/**
	 * Evaluates the dependency by generating and saving the data related with dependency.
	 * This method is invoked by cache before writing data into it.
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

		// TODO Need to check for changes in any fields associated with the block type
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

		return false;
	}
}