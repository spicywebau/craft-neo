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
		$sitesService = Craft::$app->getSites();

		$expanded = $requestService->getRequiredParam('expanded');
		$blockId = $requestService->getRequiredParam('blockId');

		// If the `locale` parameter wasn't passed, then this Craft installation has only one site, thus we can just
		// grab the primary site ID.
		$locale = $requestService->getParam('locale', $sitesService->getPrimarySite()->id);

		$return = $this->asJson([
			'success' => false,
			'blockId' => $blockId,
			'locale' => $locale,
		]);

		$block = $blockId ? Neo::$plugin->blocks->getBlockById($blockId, $locale) : null;

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
