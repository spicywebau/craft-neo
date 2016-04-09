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
		return '0.4.0';
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

	public function init()
	{
		parent::init();

		craft()->neo_reasons->pluginInit();
		craft()->neo_relabel->pluginInit();
	}

	public function onBeforeInstall()
	{
		return $this->isCraftRequiredVersion();
	}
}
