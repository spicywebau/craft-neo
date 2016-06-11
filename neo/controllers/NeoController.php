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
}
