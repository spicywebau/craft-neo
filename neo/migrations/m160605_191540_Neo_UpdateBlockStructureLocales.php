<?php
namespace Craft;

class m160605_191540_Neo_UpdateBlockStructureLocales extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumn('neoblockstructures', 'ownerLocale', 'locale');
		$this->addForeignKey('neoblockstructures', 'ownerLocale', 'locales', 'locale', 'CASCADE', 'CASCADE');

		return true;
	}
}
