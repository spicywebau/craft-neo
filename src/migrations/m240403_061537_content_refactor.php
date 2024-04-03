<?php

namespace benf\neo\migrations;

use benf\neo\Plugin as Neo;
use craft\db\Query;
use craft\migrations\BaseContentRefactorMigration;

/**
 * m240403_061537_content_refactor migration.
 */
class m240403_061537_content_refactor extends BaseContentRefactorMigration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        foreach (Neo::$plugin->blockTypes->getAllBlockTypes() as $blockType) {
            $this->updateElements(
                (new Query())->from('{{%neoblocks}}')->where(['typeId' => $blockType->id]),
                $blockType->getFieldLayout(),
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240403_061537_content_refactor cannot be reverted.\n";
        return false;
    }
}
