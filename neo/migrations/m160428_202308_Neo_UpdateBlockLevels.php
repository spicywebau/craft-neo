<?php
namespace Craft;

class m160428_202308_Neo_UpdateBlockLevels extends BaseMigration
{
	public function safeUp()
	{
		craft()->db->createCommand()->update('neoblocks', ['level' => new \CDbExpression('level + 1')]);

		return true;
	}
}
