<?php
namespace Craft;

/**
 * Class Neo_RelabelService
 * Implements support for the Relabel plugin.
 *
 * @see https://github.com/benjamminf/craft-relabel
 * @package Craft
 */
class Neo_RelabelService extends BaseApplicationComponent
{
	/**
	 * Separate initialisation function to be called inside the NeoPlugin init method.
	 */
	public function pluginInit()
	{
		if(craft()->plugins->getPlugin('relabel') && craft()->request->isCpRequest() && !craft()->isConsole())
		{
			craft()->on('fields.saveFieldLayout', [$this, 'onSaveFieldLayout']);
		}
	}

	/**
	 * Saves the relabels for a field.
	 * Serves as a wrapper for `RelabelService::saveLabel`, which requires Relabel to exist first.
	 *
	 * @param RelabelModel $label
	 */
	public function saveLabel(/*RelabelModel*/ $label)
	{
		craft()->neo->requirePlugin('relabel');
		craft()->relabel->saveLabel($label);
	}

	/**
	 * Saves Neo block type relabels from it's post data.
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
			if(isset($postData['relabel']))
			{
				craft()->neo->requirePlugin('relabel');

				$relabelPost = $postData['relabel'];

				if($relabelPost && array_key_exists($blockType->id, $relabelPost))
				{
					$relabelPost = $relabelPost[$blockType->id];

					foreach($relabelPost as $fieldId => $labelInfo)
					{
						$labelModel = new RelabelModel();
						$labelModel->fieldId = $fieldId;
						$labelModel->fieldLayoutId = $fieldLayout->id;

						if(array_key_exists('name', $labelInfo))
						{
							$labelModel->name = $labelInfo['name'];
						}

						if(array_key_exists('instructions', $labelInfo))
						{
							$labelModel->instructions = $labelInfo['instructions'];
						}

						$this->saveLabel($labelModel);
					}
				}
			}
		}
	}
}
