<?php
namespace benf\neo\controllers;

use yii\web\Response;

use Craft;
use craft\web\Controller;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;

class Input extends Controller
{
	public function actionRenderBlocks(): Response
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$requestService = Craft::$app->getRequest();

		$blocks = $requestService->getRequiredBodyParam('blocks');
		$namespace = $requestService->getParam('namespace');
		$locale = $requestService->getParam('locale');
		$renderedBlocks = [];

		foreach ($blocks as $rawBlock)
		{
			$type = Neo::$plugin->blockTypes->getById((int)$rawBlock['type']);
			$block = new Block();
			//$block->modified = true;
			$block->typeId = $rawBlock['type'];
			$block->level = $rawBlock['level'];
			$block->enabled = isset($rawBlock['enabled']);
			//$block->collapsed = isset($rawBlock['collapsed']);
			$block->ownerSiteId = $locale;

			if (!empty($rawBlock['content']))
			{
				$block->setFieldValues($rawBlock['content']);
			}

			$renderedBlocks[] = [
				'type' => $type->id,
				'level' => $block->level,
				'enabled' => $block->enabled,
				//'collapsed' => $block->collapsed,
				'tabs' => Neo::$plugin->blocks->renderTabs($block, false, $namespace),
			];
		}

		return $this->asJson([
			'success' => true,
			'blocks' => $renderedBlocks,
		]);
	}
}
