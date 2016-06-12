<?php
namespace Craft;

/**
 * Class Neo_ReasonsService
 * Implements support for the Reasons plugin.
 *
 * @see https://github.com/mmikkel/Reasons-Craft
 * @package Craft
 */
class Neo_ReasonsService extends BaseApplicationComponent
{
	/**
	 * Separate initialisation function to be called inside the NeoPlugin init method.
	 */
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

	/**
	 * Returns the conditionals just for Neo block types.
	 *
	 * @return array
	 */
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

	/**
	 * Saves the conditional rules for a field.
	 * Serves as a wrapper for `ReasonsService::saveConditionals`, which requires Reasons to exist first.
	 *
	 * @param Reasons_ConditionalsModel $conditionals
	 */
	public function saveConditionals(/*Reasons_ConditionalsModel*/ $conditionals)
	{
		craft()->neo->requirePlugin('reasons');
		craft()->reasons->saveConditionals($conditionals);
	}

	/**
	 * Saves Neo block type conditionals from it's post data.
	 *
	 * @param Event $e
	 */
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
