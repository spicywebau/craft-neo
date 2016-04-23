<?php
namespace Craft;

class Neo_ReasonsService extends BaseApplicationComponent
{
	public function pluginInit()
	{
		if(craft()->plugins->getPlugin('reasons') && craft()->request->isCpRequest() && !craft()->isConsole())
		{
			if(craft()->request->isAjaxRequest())
			{
				// TODO
			}
			else
			{
				$data = [
					'conditionals' => $this->getConditionals(),
				];

				craft()->templates->includeJs('if(window.Craft && Craft.ReasonsPlugin) Craft.ReasonsPlugin.Neo = ' . JsonHelper::encode($data));
			}

			craft()->on('fields.saveFieldLayout', [$this, 'onSaveFieldLayout']);
		}
	}

	public function saveConditionals(/*Reasons_ConditionalsModel*/ $conditionals)
	{
		craft()->neo->requirePlugin('reasons');
		craft()->reasons->saveConditionals($conditionals);
	}

	public function getConditionals()
	{
		craft()->neo->requirePlugin('reasons');

		// TODO Reduce database impact

		$blockTypeConditionals = [];
		$sources = [];

		$neoBlockTypeRecords = Neo_BlockTypeRecord::model()->findAll();
		if($neoBlockTypeRecords)
		{
			foreach($neoBlockTypeRecords as $neoBlockTypeRecord)
			{
				$neoBlockType = Neo_BlockTypeModel::populateModel($neoBlockTypeRecord);
				$sources[$neoBlockType->id] = $neoBlockType->fieldLayoutId;
			}
		}

		$conditionals = [];
		$conditionalsRecords = Reasons_ConditionalsRecord::model()->findAll();
		if($conditionalsRecords)
		{
			foreach($conditionalsRecords as $conditionalsRecord)
			{
				$conditionalsModel = Reasons_ConditionalsModel::populateModel($conditionalsRecord);
				if($conditionalsModel->conditionals && $conditionalsModel->conditionals != '')
				{
					$conditionals[$conditionalsModel->fieldLayoutId] = $conditionalsModel->conditionals;
				}
			}
		}

		foreach($sources as $blockTypeId => $fieldLayoutId)
		{
			if(isset($conditionals[$fieldLayoutId]))
			{
				$blockTypeConditionals[$blockTypeId] = $conditionals[$fieldLayoutId];
			}
		}

		return $blockTypeConditionals;
	}

	public function onSaveFieldLayout(Event $e)
	{
		$fieldLayout = $e->params['layout'];
		$postData = craft()->request->getPost('neo');
		$blockType = craft()->neo->currentSavingBlockType;

		if($postData && $blockType)
		{
			if(isset($postData['reasons']))
			{
				craft()->neo->requirePlugin('reasons');

				$reasonsPost = $postData['reasons'][$blockType->id];

				if($reasonsPost)
				{
					$conditionalsModel = new Reasons_ConditionalsModel();
					$conditionalsModel->fieldLayoutId = $fieldLayout->id;
					$conditionalsModel->conditionals = $reasonsPost;

					$this->saveConditionals($conditionalsModel);
				}
			}
		}
	}
}
