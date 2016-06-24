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
		return '1.3.0';
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
		return '1.2.0';
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

	public function isCraftRequiredVersion()
	{
		return version_compare(craft()->getVersion(), $this->getCraftMinimumVersion(), '>=');
	}

	public function isPHPRequiredVersion()
	{
		return version_compare(PHP_VERSION, $this->getPHPMinimumVersion(), '>=');
	}

	/**
	 * Initialises the plugin, as well as it's support for other plugins.
	 */
	public function init()
	{
		parent::init();

		craft()->neo_reasons->pluginInit();
		craft()->neo_relabel->pluginInit();
	}

	/**
	 * Adds custom Neo Twig extensions.
	 * It adds a way to test if some value is an instance of Neo_BlockModel in your templates.
	 * This is useful when using Twig variables in field settings.
	 *
	 * @see https://github.com/benjamminf/craft-neo/wiki/6.-FAQ#why-do-asset-fields-with-slug-as-an-upload-location-break-on-neo-blocks
	 * @return NeoTwigExtension
	 * @throws \Exception
	 */
	public function addTwigExtension()
	{
		Craft::import('plugins.neo.twigextensions.NeoTwigExtension');

		return new NeoTwigExtension();
	}

	/**
	 * Checks for environment compatibility when installing.
	 *
	 * @return bool
	 */
	public function onBeforeInstall()
	{
		$craftCompatible = $this->isCraftRequiredVersion();
		$phpCompatible = $this->isPHPRequiredVersion();

		if(!$craftCompatible)
		{
			self::log(Craft::t("Neo is not compatible with Craft {version} - requires Craft {required} or greater", [
				'version' => craft()->getVersion(),
				'required' => $this->getCraftMinimumVersion(),
			]), LogLevel::Error, true);
		}

		if(!$phpCompatible)
		{
			self::log(Craft::t("Neo is not compatible with PHP {version} - requires PHP {required} or greater", [
				'version' => PHP_VERSION,
				'required' => $this->getPHPMinimumVersion(),
			]), LogLevel::Error, true);
		}

		return $craftCompatible && $phpCompatible;
	}
}
