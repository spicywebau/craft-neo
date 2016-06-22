<?php
namespace Craft;

/**
 * Class NeoController
 *
 * @package Craft
 */
class NeoController extends BaseController
{
	/**
	 * Saves the block's collapsed or expanded state from an AJAX request.
	 * This is used when toggling a block on the front-end.
	 */
	public function actionSaveExpansion()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$expanded = craft()->request->getPost('expanded');
		$blockId = craft()->request->getPost('blockId');

		$block = craft()->neo->getBlockById($blockId);
		$block->collapsed = ($expanded === 'false' ? true : !$expanded);

		$success = craft()->neo->saveBlockCollapse($block);

		$this->returnJson([
			'success' => $success,
		]);
	}

	/**
	 *
	 */
	public function actionRenderBlocks()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$blocks = craft()->request->getPost('blocks');
		$namespace = craft()->request->getPost('namespace');

		$renderedBlocks = [];

		foreach($blocks as $rawBlock)
		{
			$type = craft()->neo->getBlockTypeById($rawBlock['type']);

			$block = new Neo_BlockModel();
			$block->typeId = $rawBlock['type'];
			$block->level = $rawBlock['level'];
			$block->enabled = isset($rawBlock['enabled']);
			$block->collapsed = isset($rawBlock['collapsed']);

			if(!empty($rawBlock['content']))
			{
				$block->setContentFromPost($rawBlock['content']);
			}

			$renderedBlocks[] = [
				'type' => $type->id,
				'level' => $block->level,
				'enabled' => $block->enabled,
				'collapsed' => $block->collapsed,
				'tabs' => craft()->neo->renderBlockTabs($type, $block, $namespace),
			];
		}

		$this->returnJson([
			'success' => true,
			'blocks' => $renderedBlocks,
		]);
	}
}
