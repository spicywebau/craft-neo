<?php
namespace benf\neo\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\helpers\Json;

use benf\neo\Field;
use benf\neo\models\BlockType;
use benf\neo\models\BlockTypeGroup;

class FieldAsset extends AssetBundle
{
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

		parent::init();
	}

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

	public static function createInputJs(Field $field, bool $static = false): string
	{
		$viewService = Craft::$app->getView();

		$name = $field->handle;
		$id = $viewService->formatInputId($name);
		$blockTypes = $field->getBlockTypes();
		$blockTypeGroups = $field->getGroups();
		$blocks = [];

		$jsSettings = [
			'name' => $name,
			'namespace' => $viewService->namespaceInputName($name),
			'blockTypes' => self::_getBlockTypesJsSettings($blockTypes),
			'groups' => self::_getBlockTypeGroupsJsSettings($blockTypeGroups),
			'inputId' => $viewService->namespaceInputId($id),
			'minBlocks' => $field->minBlocks,
			'maxBlocks' => $field->maxBlocks,
			'blocks' => self::_getBlocksJsSettings($blocks),
			'static' => $static,
		];

		$encodedJsSettings = Json::encode($jsSettings);

		return "Neo.createInput($encodedJsSettings)";
	}

	private static function _getBlocksJsSettings(array $blocks): array
	{
		$jsBlocks = [];

		return $jsBlocks;
	}

	private static function _getBlockTypesJsSettings(array $blockTypes): array
	{
		$jsBlockTypes = [];

		foreach ($blockTypes as $blockType)
		{
			if ($blockType instanceof BlockType)
			{
				$fieldLayout = $blockType->getFieldLayout();
				$fieldLayoutTabs = $fieldLayout->getTabs();
				$jsFieldLayout = [];

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
					}

					$jsFieldLayout[] = [
						'name' => $tab->name,
						'fields' => $jsTabFields,
					];
				}

				$jsBlockTypes[] = [
					'id' => $blockType->id,
					'sortOrder' => $blockType->sortOrder,
					'name' => $blockType->name,
					'handle' => $blockType->handle,
					'maxBlocks' => $blockType->maxBlocks,
					'maxChildBlocks' => $blockType->maxChildBlocks,
					'childBlocks' => Json::decodeIfJson($blockType->childBlocks),
					'topLevel' => (bool)$blockType->topLevel,
					'errors' => $blockType->getErrors(),
					'fieldLayout' => $jsFieldLayout,
					'fieldLayoutId' => $fieldLayout->id,
				];
			}
			elseif (is_array($blockType))
			{
				$jsBlockTypes[] = $blockType;
			}
		}

		return $jsBlockTypes;
	}

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
}
