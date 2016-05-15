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
	public function getName()
	{
		return "Neo";
	}

	public function getDescription()
	{
		return Craft::t("A Matrix-like field type that uses existing fields");
	}

	public function getVersion()
	{
		return '1.1.0';
	}

	public function getCraftMinimumVersion()
	{
		return '2.6';
	}

	public function getPHPMinimumVersion()
	{
		return '5.4';
	}

	public function getSchemaVersion()
	{
		return '1.1.0';
	}

	public function getDeveloper()
	{
		return 'Benjamin Fleming';
	}

	public function getDeveloperUrl()
	{
		return 'http://benjamminf.github.io';
	}

	public function getDocumentationUrl()
	{
		return 'https://github.com/benjamminf/craft-neo/wiki';
	}

	public function getReleaseFeedUrl()
	{
		return 'https://raw.githubusercontent.com/benjamminf/craft-neo/master/releases.json';
	}

	public function isCompatible()
	{
		return $this->isCraftRequiredVersion() && $this->isPHPRequiredVersion();
	}

	public function isCraftRequiredVersion()
	{
		return version_compare(craft()->getVersion(), $this->getCraftMinimumVersion(), '>=');
	}

	public function isPHPRequiredVersion()
	{
		return version_compare(PHP_VERSION, $this->getPHPMinimumVersion(), '>=');
	}

	public function init()
	{
		parent::init();

		craft()->neo_reasons->pluginInit();
		craft()->neo_relabel->pluginInit();
	}

	public function onBeforeInstall()
	{
		return $this->isCompatible();
	}
}
