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
	 *
	 * @throws HttpException
	 */
	public function actionSaveExpansion()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$expanded = craft()->request->getPost('expanded');
		$blockId = craft()->request->getPost('blockId');
		$locale = craft()->request->getPost('locale');

		$block = craft()->neo->getBlockById($blockId, $locale);
		$block->collapsed = ($expanded === 'false' ? true : !$expanded);

		$success = craft()->neo->saveBlockCollapse($block);

		$this->returnJson([
			'success' => $success,
		]);
	}

	/**
	 * Renders the HTML, CSS and JS for a Neo block.
	 * This is used when duplicating a block.
	 *
	 * @throws HttpException
	 */
	public function actionRenderBlocks()
	{
		$this->requireAjaxRequest();
		$this->requirePostRequest();

		$blocks = craft()->request->getPost('blocks');
		$namespace = craft()->request->getPost('namespace');
		$locale = craft()->request->getPost('locale');

		$renderedBlocks = [];

		foreach($blocks as $rawBlock)
		{
			$type = craft()->neo->getBlockTypeById($rawBlock['type']);

			$block = new Neo_BlockModel();
			$block->modified = true;
			$block->typeId = $rawBlock['type'];
			$block->level = $rawBlock['level'];
			$block->enabled = isset($rawBlock['enabled']);
			$block->collapsed = isset($rawBlock['collapsed']);
			$block->locale = $locale;

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

	/**
	 * Converts a Neo field
	 * @throws HttpException
	 */
	public function actionConvertToMatrix()
	{
		$this->requireAdmin();
		$this->requireAjaxRequest();

		$fieldId = craft()->request->getParam('fieldId');
		$neoField = craft()->fields->getFieldById($fieldId);

		$return = [];

		try
		{
			$return['success'] = craft()->neo->convertFieldToMatrix($neoField);
		}
		catch(\Exception $e)
		{
			$return['success'] = false;
			$return['errors'] = [$e->getMessage()];
		}

		$this->returnJson($return);
	}
}
