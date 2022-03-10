<?php

namespace benf\neo\migrations;

use benf\neo\elements\Block;
use benf\neo\Field;
use Craft;
use craft\db\Migration;
use craft\db\Query;
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
        // Nothing to do here if the Craft install isn't multi-site
        if (!Craft::$app->getIsMultiSite()) {
            return true;
        }

        $fieldsService = Craft::$app->getFields();
        $fieldPropagationMethod = [];
        $blockStructures = (new Query())
            ->from('{{%neoblockstructures}}')
            ->all();

        foreach ($blockStructures as $blockStructure) {
            $fieldId = $blockStructure['fieldId'];

            if (!isset($fieldPropagationMethod[$fieldId])) {
                $field = $fieldsService->getFieldById($fieldId);

                if (!($field instanceof Field)) {
                    continue;
                }

                $fieldPropagationMethod[$fieldId] = $field->propagationMethod;
            }

            if ($fieldPropagationMethod[$fieldId] !== Field::PROPAGATION_METHOD_ALL) {
                Queue::push(new ApplyNewPropagationMethod([
                    'description' => Craft::t('neo', 'Applying new propagation method to Neo blocks'),
                    'elementType' => Block::class,
                    'criteria' => [
                        'ownerId' => $blockStructure['ownerId'],
                        'ownerSiteId' => $blockStructure['ownerSiteId'],
                        'fieldId' => $fieldId,
                        'structureId' => $blockStructure['structureId'],
                    ],
                ]));
            }
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
