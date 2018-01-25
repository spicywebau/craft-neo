<?php
namespace benf\neo;

use Craft;

class Field extends \craft\base\Field
{
	public static function displayName(): string
	{
		return Craft::t('neo', "Neo");
	}
}
