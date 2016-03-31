<?php
namespace Craft;

/**
 * Class NeoPlugin
 *
 * Thank you for using Craft Neo!
 * @see https://github.com/benjamminf/craft-neo
 * @package Craft
 */
class NeoPlugin extends BasePlugin
{
	function getName()
	{
		return "Neo";
	}

	function getDescription()
	{
		return Craft::t("A Matrix-like field type that uses existing fields");
	}

	function getVersion()
	{
		return '0.1.0';
	}

	public function getCraftMinimumVersion()
	{
		return '2.6';
	}

	public function getSchemaVersion()
	{
		return '0.0.1';
	}

	function getDeveloper()
	{
		return 'Benjamin Fleming';
	}

	function getDeveloperUrl()
	{
		return 'http://benjamminf.github.io';
	}

	public function getDocumentationUrl()
	{
		return 'https://github.com/benjamminf/craft-neo';
	}

	public function getReleaseFeedUrl()
	{
		return 'https://raw.githubusercontent.com/benjamminf/craft-neo/master/releases.json';
	}

	public function isCraftRequiredVersion()
	{
		return version_compare(craft()->getVersion(), $this->getCraftMinimumVersion(), '>=');
	}

	public function onBeforeInstall()
	{
		return $this->isCraftRequiredVersion();
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

					craft()->neo_reasons->saveConditionals($conditionalsModel);
				}
			}
		}
	}
}
