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
			$block->setCollapsed(isset($rawBlock['collapsed']));
			$block->ownerSiteId = $locale;

			if (!empty($rawBlock['content']))
			{
				$block->setFieldValues($rawBlock['content']);
			}

			$renderedBlocks[] = [
				'type' => $type->id,
				'level' => $block->level,
				'enabled' => $block->enabled,
				'collapsed' => $block->getCollapsed(),
				'tabs' => Neo::$plugin->blocks->renderTabs($block, false, $namespace),
			];
		}

		return $this->asJson([
			'success' => true,
			'blocks' => $renderedBlocks,
		]);
	}

	public function actionSaveExpansion(): Response
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$requestService = Craft::$app->getRequest();
		$elementsService = Craft::$app->getElements();

		$expanded = $requestService->getRequiredParam('expanded');
		$blockId = $requestService->getRequiredParam('blockId');
		$locale = $requestService->getRequiredParam('locale');

		$return = $this->asJson([
			'success' => false,
			'blockId' => $blockId,
			'locale' => $locale,
		]);

		$block = $blockId ? $elementsService->getElementById($blockId, Block::class, $locale) : null;

		if ($block)
		{
			$block->setCollapsed(!$expanded);
			$block->cacheCollapsed();

			$return = $this->asJson([
				'success' => true,
				'blockId' => $blockId,
				'locale' => $locale,
				'expanded' => !$block->getCollapsed(),
			]);
		}

		return $return;
	}
}
