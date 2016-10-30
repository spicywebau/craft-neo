<?php
namespace Craft;

class m161029_230849_Neo_AddMaxChildBlocksSetting extends BaseMigration
{
	public function safeUp()
	{
		$this->addColumnAfter('neoblocktypes', 'maxChildBlocks', 'integer', 'maxBlocks');

		return true;
	}
}
