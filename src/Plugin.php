<?php
namespace benf\neo;

use yii\base\Event;
use benf\neo\fields\Neo;

class Plugin extends \craft\base\Plugin
{
	public function init()
	{
		parent::init();

		Event::on(
			Fields::class,
			Fields::EVENT_REGISTER_FIELD_TYPES,
			function(RegisterComponentTypesEvent $event)
			{
	            $event->types[] = Neo::class;
	        }
	    );
	}
}
