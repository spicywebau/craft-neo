<?php
namespace Craft;

class Neo_ReasonsService extends BaseApplicationComponent
{
	public function saveConditionals(/*Reasons_ConditionalsModel*/ $conditionals)
	{
		craft()->neo->requirePlugin('reasons');
		craft()->reasons->saveConditionals($conditionals);
	}
}
