<?php
namespace Craft;

/**
 * Class NeoFieldType
 * @package Craft
 */
class NeoFieldType extends BaseFieldType
{
	public function getName()
	{
		return Craft::t('Neo');
	}

	public function defineContentAttribute()
	{
		return false;
	}

	public function getInputHtml($name, $value)
	{
		return craft()->templates->render('neo/_fieldtype/input', array(
			'name'  => $name,
			'value' => $value
		));
	}

	public function getSettingsHtml()
	{
		return craft()->templates->render('neo/_fieldtype/settings', array());
	}
}
