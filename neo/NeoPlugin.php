<?php
namespace Craft;

/**
 * Class NeoPlugin
 *
 * Thank you for using Craft Neo!
 * @see https://github.com/spicywebau/craft-neo
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
		return '1.5.2';
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
		return '1.5.0';
	}

	public function getDeveloper()
	{
		// Created by Benjamin Fleming https://github.com/benjamminf
		return 'Spicy Web';
	}

	public function getDeveloperUrl()
	{
		return 'https://spicyweb.com.au';
	}

	public function getDocumentationUrl()
	{
		return 'https://github.com/spicywebau/craft-neo/wiki';
	}

	public function getReleaseFeedUrl()
	{
		return 'https://raw.githubusercontent.com/spicywebau/craft-neo/craft-2/releases.json';
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

		craft()->on('elements.onBeforeDeleteElements', [$this, 'onBeforeDeleteElements']);
		craft()->on('i18n.onBeforeDeleteLocale', [$this, 'onBeforeDeleteLocale']);

		if(craft()->request->isCpRequest() && !craft()->request->isAjaxRequest())
		{
			$this->includeResources();
		}
	}

	/**
	 * Adds custom Neo Twig extensions.
	 * It adds a way to test if some value is an instance of Neo_BlockModel in your templates.
	 * This is useful when using Twig variables in field settings.
	 *
	 * @see https://github.com/spicywebau/craft-neo/wiki/6.-FAQ#why-do-asset-fields-with-slug-as-an-upload-location-break-on-neo-blocks
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

	/**
	 * Converts all Neo fields to Matrix on uninstall
	 */
	public function onBeforeUninstall()
	{
		$fields = craft()->fields->getAllFields();

		foreach($fields as $field)
		{
			if($field->getFieldType() instanceof NeoFieldType)
			{
				craft()->neo->convertFieldToMatrix($field);
			}
		}
	}

	/**
	 * Cleans up Neo blocks when their parents get deleted.
	 *
	 * This typically happens automatically, but as a consequence of the database constraints. Explicitly calling to
	 * delete the blocks invokes their lifecycle events, which other fields and plugins can hook into.
	 *
	 * This solves an issue with nested Matrix and SuperTable fields becoming orphaned if Neo's parent element is
	 * deleted.
	 *
	 * (Stolen from SuperTable)
	 * @see https://github.com/engram-design/SuperTable/commit/6bfb059d2cffe42753b4569444d00681b59a3e1d
	 *
	 * @param Event $e
	 */
	protected function onBeforeDeleteElements(Event $e)
	{
		$elementIds = $e->params['elementIds'];

		if(count($elementIds) == 1)
		{
			$blockCondition = ['ownerId' => $elementIds[0]];
		}
		else
		{
			$blockCondition = ['in', 'ownerId', $elementIds];
		}

		$blockIds = craft()->db->createCommand()
			->select('id')
			->from('neoblocks')
			->where($blockCondition)
			->queryColumn();

		if($blockIds)
		{
			craft()->neo->deleteBlockById($blockIds);
		}
	}

	/**
	 * Transfers all Neo content from a deleted locale if needed.
	 * TODO
	 *
	 * @param Event $e
	 */
	protected function onBeforeDeleteLocale(Event $e)
	{
		$oldLocale = $e->params['localeId'];
		$newLocale = $e->params['transferContentTo'];

		if($e->performAction && $newLocale)
		{
			// TODO don't understand how LocalizationService::deleteSiteLocale() works at all, need to investigate first
		}
	}

	/**
	 * Includes additional CSS and JS resources in the control panel
	 */
	protected function includeResources()
	{
		if($this->_matchUriSegments(['settings', 'fields', 'edit', '*']))
		{
			craft()->templates->includeJsResource('neo/converter.js');
		}
	}

	/**
	 * Helper function for matching against the URI.
	 * Useful for including resources on specific pages.
	 *
	 * @param $matchSegments
	 * @return bool
	 */
	private function _matchUriSegments($matchSegments)
	{
		$segments = craft()->request->getSegments();

		if(count($segments) != count($matchSegments))
		{
			return false;
		}

		foreach($segments as $i => $segment)
		{
			$matchSegment = $matchSegments[$i];

			if($matchSegment != '*' && $segment != $matchSegment)
			{
				return false;
			}
		}

		return true;
	}
}
