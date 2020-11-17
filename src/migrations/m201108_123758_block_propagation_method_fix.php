<?php

namespace benf\neo\migrations;

use benf\neo\Field;
use benf\neo\elements\Block;
use Craft;
use craft\db\Migration;
use craft\helpers\Queue;
use craft\queue\jobs\ApplyNewPropagationMethod;

/**
 * Fixes Neo blocks that had not had their field's new propagation method applied.
 *
 * @package benf\neo\migrations
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.8.14
 */
class m201108_123758_block_propagation_method_fix extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Not multi-site?  Nothing to do here.
        if (!Craft::$app->getIsMultiSite()) {
            return true;
        }

        $neoFields = array_filter(Craft::$app->getFields()->getAllFields(), function($field) {
            return $field instanceof Field && $field->propagationMethod !== Field::PROPAGATION_METHOD_ALL;
        });

        foreach ($neoFields as $neoField) {
            Queue::push(new ApplyNewPropagationMethod([
                'description' => Craft::t('neo', 'Applying new propagation method to Neo blocks'),
                'elementType' => Block::class,
                'criteria' => [
                    'fieldId' => $neoField->id,
                ],
            ]));
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m201108_123758_block_propagation_method_fix cannot be reverted.\n";
        return false;
    }
}
