<?php
namespace Craft;

class NeoVariable
{
	public function children(Neo_BlockModel $block)
	{
		return craft()->neo->getChildBlocks($block);
	}

	public function hasChildren(Neo_BlockModel $block)
	{
		return !empty($this->children($block));
	}
}
