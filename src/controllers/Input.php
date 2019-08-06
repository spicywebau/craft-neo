<?php
namespace benf\neo\controllers;

use yii\web\Response;

use Craft;
use craft\web\Controller;

use benf\neo\Plugin as Neo;
use benf\neo\elements\Block;

/**
 * Class Input
 *
 * @package benf\neo\controllers
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Input extends Controller
{
	/**
	 * Renders pasted or cloned input blocks.
	 *
	 * @return Response
	 */
	public function actionRenderBlocks(): Response
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$requestService = Craft::$app->getRequest();

		$blocks = $requestService->getRequiredBodyParam('blocks');
		$namespace = $requestService->getParam('namespace');
		$siteId = $requestService->getParam('locale');
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
			$block->siteId = $siteId ?? Craft::$app->getSites()->getPrimarySite()->id;

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

	/**
	 * Saves the expanded/collapsed state of a block.
	 *
	 * @return Response
	 */
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
		$siteId = $requestService->getParam('locale', $sitesService->getPrimarySite()->id);

		$return = $this->asJson([
			'success' => false,
			'blockId' => $blockId,
			'locale' => $siteId,
		]);

		$block = $blockId ? Neo::$plugin->blocks->getBlockById($blockId, $siteId) : null;

		// Only set the collapsed state if `collapseAllBlocks` is disabled; if `collapseAllBlocks` is enabled, a block's
		// original collapsed state will be preserved in case the setting is disabled in the future
		if ($block && !Neo::$plugin->getSettings()->collapseAllBlocks)
		{
			$block->setCollapsed(!$expanded);
			$block->cacheCollapsed();

			$return = $this->asJson([
				'success' => true,
				'blockId' => $blockId,
				'locale' => $siteId,
				'expanded' => !$block->getCollapsed(),
			]);
		}

		return $return;
	}
}
