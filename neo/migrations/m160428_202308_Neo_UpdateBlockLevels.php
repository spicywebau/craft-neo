<?php
namespace Craft;

class m160428_202308_Neo_UpdateBlockLevels extends BaseMigration
{
	public function safeUp()
	{
		$tableName = (new Neo_BlockRecord())->getTableName();
		craft()->db->createCommand()->update($tableName, ['level' => new \CDbExpression('level + 1')]);

		return true;
	}
}
