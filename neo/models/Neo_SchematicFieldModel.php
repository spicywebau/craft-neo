<?php

namespace Craft;

use NerdsAndCompany\Schematic\Models\MatrixField;

/**
 * Schematic Neo Field Model.
 *
 * A schematic field model for mapping neo data.
 */
class Neo_SchematicFieldModel extends MatrixField
{
    /**
     * @return NeoService
     */
    private function getNeoService()
    {
        return Craft::app()->neo;
    }

    //==============================================================================================================
    //================================================  EXPORT  ====================================================
    //==============================================================================================================

    /**
     * Get block type definitions.
     *
     * @param FieldModel $field
     *
     * @return array
     */
    protected function getBlockTypeDefinitions(FieldModel $field)
    {
        $fieldFactory = $this->getFieldFactory();
        $blockTypeDefinitions = [];

        /** @var Neo_BlockTypeModel[] $blockTypes */
        $blockTypes = $this->getNeoService()->getBlockTypesByFieldId($field->id);
        foreach ($blockTypes as $blockType) {
            $blockTypeDefinitions[] = [
                'fieldId' => $blockType->fieldId,
                'name' => $blockType->name,
                'handle' => $blockType->handle,
                'maxBlocks' => $blockType->maxBlocks,
                'maxChildBlocks' => $blockType->maxChildBlocks,
                'sortOrder' => $blockType->sortOrder,
                'childBlocks' => $blockType->childBlocks,
                'topLevel' => $blockType->topLevel,
                'fieldLayout' => Craft::app()->schematic_fields->getFieldLayoutDefinition($blockType->getFieldLayout()),
            ];
        }

        return $blockTypeDefinitions;
    }

    //==============================================================================================================
    //================================================  IMPORT  ====================================================
    //==============================================================================================================

    /**
     * @param array      $fieldDefinition
     * @param FieldModel $field
     * @param bool       $force
     *
     * @return mixed
     */
    protected function getBlockTypes(array $fieldDefinition, FieldModel $field, $force = false)
    {
        $blockTypes = $this->getNeoService()->getBlockTypesByFieldId($field->id);
        
        //delete old blocktypes if they are missing from the definition.
        if ($force) {
            foreach ($blockTypes as $key => $value) {
                if (!array_key_exists($key, $fieldDefinition['blockTypes'])) {
                    $this->getNeoService()->deleteBlockType($blockTypes[$key]);
                    unset($blockTypes[$key]);
                }
            }
        }

        foreach ($fieldDefinition['blockTypes'] as $blockTypeHandle => $blockTypeDef) {
            var_dump('NeoField', $blockTypeHandle);
            $blockType = array_key_exists($blockTypeHandle, $blockTypes)
                ? $blockTypes[$blockTypeHandle]
                : new Neo_BlockTypeModel();

            $blockType->name = $blockTypeDef['name'];
            $blockType->handle = $blockTypeDef['handle'];
            $blockType->sortOrder = $blockTypeDef['sortOrder'];
            $blockType->maxBlocks = $blockTypeDef['maxBlocks'];
            $blockType->maxChildBlocks = $blockTypeDef['maxChildBlocks'];
            $blockType->childBlocks = $blockTypeDef['childBlocks'];
            $blockType->topLevel = $blockTypeDef['topLevel'];
            
            $fieldLayout = Craft::app()->schematic_fields->getFieldLayout($blockTypeDef['fieldLayout']);
            $blockType->setFieldLayout($fieldLayout);
            
            $this->getNeoService()->saveBlockType($blockType, false);

            $blockTypes[$blockTypeHandle] = $blockType;
        }

        return $blockTypes;
    }
}
