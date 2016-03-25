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
		return Craft::t("A Matrix-like field type that groups existing fields");
	}

	function getVersion()
	{
		return '0.0.1';
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
		return 'http://benf.co';
	}

	public function getDocumentationUrl()
	{
		return 'https://github.com/benjamminf/craft-neo';
	}

	public function init()
	{
		parent::init();

		if($this->isCraftRequiredVersion())
		{

		}
	}

	public function isCraftRequiredVersion()
	{
		return version_compare(craft()->getVersion(), '2.5', '>=');
	}
}
