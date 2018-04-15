<?php
namespace benf\neo\services;

use yii\base\Component;

use Craft;
use craft\helpers\StringHelper;

use benf\neo\elements\Block;
use benf\neo\models\BlockType;

class Blocks extends Component
{
	public function getSearchKeywords(Block $block, ElementInterface $element = null): string
	{
		$fieldsService = Craft::$app->getFields();

		if ($element === null)
		{
			$element = $block;
		}

		$keywords = [];

		$fieldLayout = $block->getFieldLayout();
		$fieldIds = $fieldLayout->getFieldIds();

		foreach ($fieldsService->getAllFields() as $field)
		{
			if (in_array($field->id, $fieldIds))
			{
				$fieldValue = $block->getFieldValue($field->handle);
				$keywords[] = $field->getSearchKeywords($fieldValue, $element);
			}
		}

		return StringHelper::toString($keywords, ' ');
	}

	public function renderTabs(Block $block, bool $static = false, $namespace = null): array
	{
		$viewService = Craft::$app->getView();

		$blockType = $block->getType();

		$namespace = $namespace ?? $viewService->namespaceInputName($blockType->handle);
		$oldNamespace = $viewService->getNamespace();
		$newNamespace = $viewService->namespaceInputName($namespace . '[__NEOBLOCK__][fields]', $oldNamespace);
		$viewService->setNamespace($newNamespace);

		$tabsHtml = [];

		$fieldLayout = $blockType->getFieldLayout();
		$tabs = $fieldLayout->getTabs();

		foreach ($tabs as $tab)
		{
			$viewService->startJsBuffer();

			$tabHtml = [
				'name' => Craft::t('neo', $tab->name),
				'headHtml' => '',
				'bodyHtml' => '',
				'footHtml' => '',
				'errors' => [],
			];

			$fields = $tab->getFields();

			if ($block)
			{
				foreach ($fields as $field)
				{
					$fieldErrors = $block->getErrors($field->handle);

					if (!empty($fieldErrors))
					{
						$tabHtml['errors'] = array_merge($tabHtml['errors'], $fieldErrors);
					}
				}
			}

			$fieldsHtml = $viewService->renderTemplate('_includes/fields', [
				'namespace' => null,
				'element' => $block,
				'fields' => $fields,
				'static' => $static,
			]);

			$fieldsJs = $viewService->clearJsBuffer();

			$tabHtml['bodyHtml'] = $viewService->namespaceInputs($fieldsHtml);
			$tabHtml['footHtml'] = $fieldsJs;

			$tabsHtml[] = $tabHtml;
		}

		$viewService->setNamespace($oldNamespace);

		return $tabsHtml;
	}
}
