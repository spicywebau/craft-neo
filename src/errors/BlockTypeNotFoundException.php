<?php
namespace benf\neo\errors;

use yii\base\Exception;

class BlockTypeNotFoundException extends Exception
{
	public function getName()
	{
		return "Neo block type not found";
	}
}
