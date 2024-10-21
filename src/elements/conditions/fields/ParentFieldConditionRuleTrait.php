<?php

namespace benf\neo\elements\conditions\fields;

use benf\neo\helpers\Memoize;
use benf\neo\Plugin as Neo;
use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\db\Table;
use craft\helpers\Db;
use yii\base\InvalidConfigException;

/**
 * Trait for field condition rules for parent Neo blocks.
 *
 * @package benf\neo\elements\conditions\fields
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.7.0
 */
trait ParentFieldConditionRuleTrait
{
    /**
     * @var FieldInterface[] The custom field instances associated with this rule
     */
    private array $_fieldInstances;

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        $parentBlock = $element->getParent();

        // If no parent block, then disregard this rule
        return $parentBlock ? parent::matchElement($parentBlock) : true;
    }

    /**
     * @inheritdoc
     */
    public function getGroupLabel(): ?string
    {
        return Craft::t('neo', 'Parent block fields');
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return $this->field()->name . ' (parent block)';
    }

    /**
     * Based on `craft\fields\conditions\FieldConditionRuleTrait::fieldInstances()`, but using field layouts associated
     * with all of the Neo field's block types.
     */
    protected function fieldInstances(): array
    {
        if (!isset($this->_fieldInstances)) {
            $config = $this->getConfig();

            if (!isset($config['fieldUid'])) {
                throw new InvalidConfigException('No field UUID set on the field condition rule yet.');
            }

            if (
                isset($config['layoutElementUid']) &&
                isset(Memoize::$parentFieldInstancesByLayoutElementUuid[$config['layoutElementUid']])
            ) {
                $this->_fieldInstances = Memoize::$parentFieldInstancesByLayoutElementUuid[$config['layoutElementUid']];
                return $this->_fieldInstances;
            }

            // Loop through all the layout's fields, and look for the selected field instance
            // and any other instances with the same label and handle
            $this->_fieldInstances = [];
            /** @var FieldInterface[] $potentialInstances */
            $potentialInstances = [];
            $selectedInstance = null;
            $selectedInstanceLabel = null;

            // Get all of the block type field layouts associated with the Neo field(s)
            // (ensuring we retain the unsaved layouts set on the condition)
            $conditionLayouts = $this->getCondition()->getFieldLayouts();
            $fieldLayouts = array_values(array_filter(
                $conditionLayouts,
                fn($layout) => $layout->id === null,
            ));
            $layoutIds = array_values(Db::idsByUids(
                Table::FIELDLAYOUTS,
                array_map(fn($layout) => $layout->uid, $conditionLayouts),
            ));
            $layoutBlockTypes = Neo::$plugin->blockTypes->getByCriteria([
                'fieldLayoutId' => $layoutIds,
            ]);
            $fieldBlockTypes = Neo::$plugin->blockTypes->getByCriteria([
                'fieldId' => array_values(array_unique(array_map(fn($blockType) => $blockType->fieldId, $layoutBlockTypes))),
            ]);
            $fieldLayouts = array_merge(
                $fieldLayouts,
                array_map(fn($blockType) => $blockType->getFieldLayout(), $fieldBlockTypes),
            );

            foreach ($fieldLayouts as $fieldLayout) {
                foreach ($fieldLayout->getCustomFields() as $field) {
                    if ($field->uid === $config['fieldUid']) {
                        // skip if it doesn't have a label
                        $label = $field->layoutElement->label();
                        if ($label === null) {
                            continue;
                        }

                        // is this the selected field instance?
                        // (if we aren't looking for a specific instance, include it if the handle isn't overridden)
                        if (
                            (isset($config['layoutElementUid']) && $field->layoutElement->uid === $config['layoutElementUid']) ||
                            (!isset($config['layoutElementUid']) && !isset($field->layoutElement->handle))
                        ) {
                            $this->_fieldInstances[] = $field;

                            if (isset($config['layoutElementUid'])) {
                                $selectedInstance = $field;
                                $selectedInstanceLabel = $label;
                            }
                        } elseif (isset($config['layoutElementUid'])) {
                            $potentialInstances[] = $field;
                        }
                    }
                }
            }

            if (empty($this->_fieldInstances)) {
                if (!isset($config['layoutElementUid'])) {
                    throw new InvalidConfigException("Field {$config['fieldUid']} is not included in the available field layouts.");
                }

                if (!empty($potentialInstances)) {
                    // Just go with the first one
                    $this->_fieldInstances[] = $first = array_shift($potentialInstances);
                    $selectedInstance = $first;
                    $selectedInstanceLabel = $first->layoutElement->label();
                } else {
                    throw new InvalidConfigException("Invalid field layout element UUID: {$config['layoutElementUid']}");
                }
            }

            // Add any potential fields to the mix if they have a matching label and handle
            foreach ($potentialInstances as $field) {
                if (
                    $field->handle === $selectedInstance->handle &&
                    $field->layoutElement->label() === $selectedInstanceLabel
                ) {
                    $this->_fieldInstances[] = $field;
                }
            }

            if (isset($config['layoutElementUid'])) {
                Memoize::$parentFieldInstancesByLayoutElementUuid[$config['layoutElementUid']] = $this->_fieldInstances;
            }
        }

        return $this->_fieldInstances;
    }
}
