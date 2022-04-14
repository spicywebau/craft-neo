<?php

namespace benf\neo\console\controllers;

use benf\neo\elements\Block;
use benf\neo\Field;
use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\Queue;
use craft\i18n\Translation;
use craft\queue\jobs\ApplyNewPropagationMethod;
use yii\console\ExitCode;

/**
 * Actions for managing Neo fields.
 *
 * @package benf\neo\console\controllers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 2.13.0
 */
class FieldsController extends Controller
{
    /**
     * @var bool Whether to reapply propagation methods on a per-block-structure basis
     */
    public bool $byBlockStructure = false;

    /**
     * @var string|null
     */
    public ?string $fieldId = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'reapply-propagation-method') {
            $options[] = 'byBlockStructure';
            $options[] = 'fieldId';
        }

        return $options;
    }

    /**
     * Reapplies a Neo field's propagation method to its blocks if the Craft install is multi-site.
     *
     * @return int
     */
    public function actionReapplyPropagationMethod(): int
    {
        // Not multi-site? Nothing to do here.
        if (!Craft::$app->getIsMultiSite()) {
            $this->stdout('The Craft install is not multi-site, so the propagation method was not applied.' . PHP_EOL);
            return ExitCode::OK;
        }

        $fieldsService = Craft::$app->getFields();

        // If not reapplying by block structure, just do one for every field
        if (!$this->byBlockStructure) {
            $setIds = [];

            if ($this->fieldId !== null) {
                foreach (explode(',', $this->fieldId) as $fieldId) {
                    $setIds[$fieldId] = true;
                }
            }

            $neoFields = array_filter($fieldsService->getAllFields(), function($field) use ($setIds) {
                return $field instanceof Field &&
                    $field->propagationMethod !== Field::PROPAGATION_METHOD_ALL &&
                    (empty($setIds) || isset($setIds[$field->id]));
            });

            foreach ($neoFields as $field) {
                Queue::push(new ApplyNewPropagationMethod([
                    'description' => Translation::prep('neo', 'Applying new propagation method to Neo blocks'),
                    'elementType' => Block::class,
                    'criteria' => [
                        'fieldId' => $field->id,
                    ],
                ]));
            }

            $this->stdout($this->_jobMessage(count($neoFields)) . PHP_EOL);

            return ExitCode::OK;
        }

        // If we're still here, we're reapplying on a per-block-structure basis, which is necessary for Craft installs
        // affected by issue #421
        $counter = 0;
        $fieldPropagationMethod = [];
        $blockStructuresQuery = (new Query())
            ->select(['nbs.id', 'nbs.structureId', 'nbs.ownerId', 'nbs.fieldId', 'nbs.siteId'])
            ->from('{{%neoblockstructures}} nbs')
            // Don't reapply to revision blocks
            ->innerJoin(['elements' => Table::ELEMENTS], '[[elements.id]] = [[nbs.ownerId]]')
            ->where(['elements.revisionId' => null]);

        // If the ID was set, only look for those fields, otherwise just look for them all
        if ($this->fieldId !== null) {
            $blockStructuresQuery->andWhere(['nbs.fieldId' => explode(',', $this->fieldId)]);
        }

        foreach ($blockStructuresQuery->all() as $blockStructure) {
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
                    'description' => Translation::prep('neo', 'Applying new propagation method to Neo blocks'),
                    'elementType' => Block::class,
                    'criteria' => [
                        'ownerId' => $blockStructure['ownerId'],
                        'siteId' => $blockStructure['siteId'],
                        'fieldId' => $fieldId,
                        'structureId' => $blockStructure['structureId'],
                    ],
                ]));
                $counter++;
            }
        }

        $this->stdout($this->_jobMessage($counter) . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * @param int $counter
     * @return string
     */
    private function _jobMessage(int $counter = 0): string
    {
        if ($counter === 1) {
            return '1 new job was added to the queue.';
        }

        return $counter . ' new jobs were added to the queue.';
    }
}
