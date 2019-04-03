<?php
namespace benf\neo\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\helpers\Json;

use benf\neo\Plugin as Neo;
use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;
use benf\neo\elements\Block;

/**
 * Class FieldAsset
 *
 * @package benf\neo\assets
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class FieldAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		$this->sourcePath = '@benf/neo/resources';

		$this->depends = [
			CpAsset::class,
		];

		$this->js = [
			'polyfill.js',
			'main.js',
		];

		if ($this->_matchUriSegments(['settings', 'fields', 'edit', '*']))
		{
			array_push($this->js, 'converter.js');
		}

		parent::init();
	}

	/**
	 * Sets up the field layout designer for a given Neo field.
	 *
	 * @param Field $field The Neo field.
	 * @return string
	 */
	public static function createSettingsJs(Field $field): string
	{
		$viewService = Craft::$app->getView();

		$blockTypes = $field->getBlockTypes();
		$blockTypeGroups = $field->getGroups();

		// Render the field layout designer HTML, but disregard any Javascript it outputs, as that'll be handled by Neo.
		$viewService->startJsBuffer();
		$fieldLayoutHtml = $viewService->renderTemplate('_includes/fieldlayoutdesigner', [
			'fieldLayout' => false,
			'instructions' => '',
		]);
		$viewService->clearJsBuffer();

		$jsSettings = [
			'namespace' => $viewService->getNamespace(),
			'blockTypes' => self::_getBlockTypesJsSettings($blockTypes),
			'groups' => self::_getBlockTypeGroupsJsSettings($blockTypeGroups),
			'fieldLayoutHtml' => $fieldLayoutHtml,
		];

		$encodedJsSettings = Json::encode($jsSettings);

		return "Neo.createConfigurator($encodedJsSettings)";
	}

	/**
	 * Sets up the field block inputs for a given Neo field.
	 *
	 * @param Field $field The Neo field.
	 * @param array $value The Neo blocks, associated with this field, to generate inputs for.
	 * @param bool $static Whether to generate static HTML for the blocks, e.g. for displaying entry revisions.
	 * @param int|null $siteId
	 * @return string
	 */
	public static function createInputJs(Field $field, $value, bool $static = false, int $siteId = null): string
	{
		$viewService = Craft::$app->getView();

		$name = $field->handle;
		$id = $viewService->formatInputId($name);
		$blockTypes = $field->getBlockTypes();
		$blockTypeGroups = $field->getGroups();

		$jsSettings = [
			'name' => $name,
			'namespace' => $viewService->namespaceInputName($name),
			'blockTypes' => self::_getBlockTypesJsSettings($blockTypes, true, $static, $siteId),
			'groups' => self::_getBlockTypeGroupsJsSettings($blockTypeGroups),
			'inputId' => $viewService->namespaceInputId($id),
			'minBlocks' => $field->minBlocks,
			'maxBlocks' => $field->maxBlocks,
			'maxTopBlocks' => $field->maxTopBlocks,
			'blocks' => self::_getBlocksJsSettings($value, $static),
			'static' => $static,
		];

		$encodedJsSettings = Json::encode($jsSettings, JSON_UNESCAPED_UNICODE);

		return "Neo.createInput($encodedJsSettings)";
	}

	/**
	 * Returns the raw data from the given blocks.
	 *
	 * This converts Blocks into the format used by the input generator Javascript.
	 *
	 * @param array $blocks The Neo blocks.
	 * @param bool $static Whether to generate static HTML for the blocks, e.g. for displaying entry revisions.
	 * @return array
	 */
	private static function _getBlocksJsSettings(array $blocks, bool $static = false): array
	{
		$collapseAllBlocks = Neo::$plugin->getSettings()->collapseAllBlocks;
		$jsBlocks = [];
		$sortOrder = 0;

		foreach ($blocks as $block)
		{
			if ($block instanceof Block)
			{
				$blockType = $block->getType();

				$jsBlocks[] = [
					'id' => $block->id,
					'blockType' => $blockType->handle,
					'modified' => false,
					'sortOrder' => $sortOrder++,
					'collapsed' => !$collapseAllBlocks ? $block->getCollapsed() : true,
					'enabled' => (bool)$block->enabled,
					'level' => max(0, intval($block->level) - 1),
					'tabs' => Neo::$plugin->blocks->renderTabs($block, $static),
				];
			}
			elseif (is_array($block))
			{
				$jsBlocks[] = $block;
			}
		}

		return $jsBlocks;
	}

	/**
	 * Returns the raw data from the given block types.
	 *
	 * This converts block types into the format used by the input generator Javascript.
	 *
	 * @param array $blockTypes The Neo block types.
	 * @param bool $renderTabs Whether to render the block types' tabs.
	 * @param bool $static Whether to generate static HTML for the block types, e.g. for displaying entry revisions.
	 * @param int|null $siteId
	 * @return array
	 */
	private static function _getBlockTypesJsSettings(array $blockTypes, bool $renderTabs = false, bool $static = false, int $siteId = null): array
	{
		$jsBlockTypes = [];

		foreach ($blockTypes as $blockType)
		{
			if ($blockType instanceof BlockType)
			{
				$fieldLayout = $blockType->getFieldLayout();
				$fieldLayoutTabs = $fieldLayout->getTabs();
				$jsFieldLayout = [];
				$fieldTypes = [];

				foreach ($fieldLayoutTabs as $tab)
				{
					$tabFields = $tab->getFields();
					$jsTabFields = [];

					foreach ($tabFields as $field)
					{
						$jsTabFields[] = [
							'id' => $field->id,
							'required' => $field->required,
						];

						$fieldTypes[$field->handle] = $field->className();
					}

					$jsFieldLayout[] = [
						'name' => $tab->name,
						'fields' => $jsTabFields,
					];
				}

				$jsBlockType = [
					'id' => $blockType->id,
					'sortOrder' => $blockType->sortOrder,
					'name' => Craft::t('neo', $blockType->name),
					'handle' => $blockType->handle,
					'maxBlocks' => $blockType->maxBlocks,
					'maxChildBlocks' => $blockType->maxChildBlocks,
					'childBlocks' => is_string($blockType->childBlocks) ? Json::decodeIfJson($blockType->childBlocks) : $blockType->childBlocks,
					'topLevel' => (bool)$blockType->topLevel,
					'errors' => $blockType->getErrors(),
					'fieldLayout' => $jsFieldLayout,
					'fieldLayoutId' => $fieldLayout->id,
					'fieldTypes' => $fieldTypes,
				];

				if ($renderTabs)
				{
					$tabsHtml = Neo::$plugin->blockTypes->renderTabs($blockType, $static, null, $siteId);
					$jsBlockType['tabs'] = $tabsHtml;
				}

				$jsBlockTypes[] = $jsBlockType;
			}
			elseif (is_array($blockType))
			{
				$jsBlockTypes[] = $blockType;
			}
		}

		return $jsBlockTypes;
	}

	/**
	 * Returns the raw data from the given block type groups.
	 *
	 * This converts block type groups into the format used by the input generator Javascript.
	 *
	 * @param array $blockTypeGroups The Neo block type groups.
	 * @return array
	 */
	private static function _getBlockTypeGroupsJsSettings(array $blockTypeGroups): array
	{
		$jsBlockTypeGroups = [];	

		foreach ($blockTypeGroups as $blockTypeGroup)
		{
			if ($blockTypeGroup instanceof BlockTypeGroup)
			{
				$jsBlockTypeGroups[] = [
					'id' => $blockTypeGroup->id,
					'sortOrder' => $blockTypeGroup->sortOrder,
					'name' => $blockTypeGroup->name,
				];
			}
			elseif (is_array($blockTypeGroup))
			{
				$jsBlockTypeGroups[] = $blockTypeGroup;
			}
		}

		return $jsBlockTypeGroups;
	}

	/**
	 * Helper function for matching against the URI.
	 * Useful for including resources on specific pages.
	 *
	 * @param $matchSegments
	 * @return bool
	 */
	private function _matchUriSegments($matchSegments): bool
	{
		$segments = Craft::$app->getRequest()->getSegments();

		if (count($segments) !== count($matchSegments))
		{
			return false;
		}

		foreach ($segments as $i => $segment)
		{
			$matchSegment = $matchSegments[$i];

			if ($matchSegment !== '*' && $segment !== $matchSegment)
			{
				return false;
			}
		}

		return true;
	}
}
