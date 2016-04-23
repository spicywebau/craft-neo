<?php
namespace Craft;

class NeoController extends BaseController
{
	public function actionSaveExpansion()
	{
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
