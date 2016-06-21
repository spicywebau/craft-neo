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
	public function actionRenderBlock()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$blockTypeId = craft()->request->getPost('blockType');
		$content = craft()->request->getPost('content');
		$namespace = craft()->request->getPost('namespace');

		$blockType = craft()->neo->getBlockTypeById($blockTypeId);

		$block = new Neo_BlockModel();
		$block->typeId = $blockTypeId;
		$block->setContentFromPost($content);

		$tabsHtml = craft()->neo->renderBlockTabs($blockType, $block, $namespace);

		$this->returnJson([
			'success' => true,
			'tabs' => $tabsHtml,
		]);
	}
}
