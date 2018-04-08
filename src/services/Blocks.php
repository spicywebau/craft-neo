<?php
namespace benf\neo\services;

use yii\base\Component;

use Craft;
use benf\neo\elements\Block;
use benf\neo\models\BlockType;

class Blocks extends Component
{
	public function getSearchKeywords(Block $block): string
	{
		return '';	
	}

	public function renderTabs(Block $block, bool $static = false, $namespace = null): string
	{
		$viewService = Craft::$app->getView();

		$blockType = $block->getType();

		$namespace = $namespace ?? $viewService->namespaceInputName($blockType->handle);
		$oldNamespace = $viewService->getNamespace();
		$newNamespace = $viewService->namespaceInputName($namespace . '[__NEOBLOCK__][fields]', $oldNamespace);
		$viewService->setNamespace($newNamespace);

		$tabsHtml = [];

		$fieldLayout = $blockType->getFieldLayout();
		$fieldLayoutTabs = $fieldLayout->getTabs();

		foreach ($fieldLayoutTabs as $fieldLayoutTab)
		{
			$viewService->startJsBuffer();

			$tabHtml = [
				'name' => Craft::t('neo', $fieldLayoutTab->name),
				'headHtml' => '',
				'bodyHtml' => '',
				'footHtml' => '',
				'errors' => [],
			];

			$fieldLayoutFields = $fieldLayoutTab->getFields();

			foreach ($fieldLayoutFields as $fieldLayoutField)
			{
				$field = $fieldLayoutField->getField();
				$fieldType = $field->getFieldType();

				if ($fieldType)
				{
					$fieldType->element = $block;
					
					if ($block)
					{
						$fieldErrors = $block->getErrors($field->handle);

						if (!empty($fieldErrors))
						{
							$tabHtml['errors'] = array_merge($tabHtml['errors'], $fieldErrors);
						}
					}
				}
			}

			$tabHtml['bodyHtml'] = $viewService->namespaceInputs(craft()->templates->render('_includes/fields', [
				'namespace' => null,
				'element' => $block,
				'fields' => $fieldLayoutFields,
				'static' => $static,
			]));

			$tabHtml['footHtml'] = $viewService->clearJsBuffer();

			$tabsHtml[] = $tabHtml;
		}

		$viewService->setNamespace($oldNamespace);

		return $tabsHtml;
	}
}
