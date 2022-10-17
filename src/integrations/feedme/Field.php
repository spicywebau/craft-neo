<?php

/*
This class is based on the Matrix field class from Feed Me version 5.0.4, by Pixel & Tonic, Inc.
https://github.com/craftcms/feed-me/blob/5.0.4/src/fields/Matrix.php
Feed Me is released under the terms of the Craft License, a copy of which is included below.
https://github.com/craftcms/feed-me/blob/5.0.4/LICENSE.md

Copyright © Pixel & Tonic

Permission is hereby granted to any person obtaining a copy of this software
(the “Software”) to use, copy, modify, merge, publish and/or distribute copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

1. **Don’t plagiarize.** The above copyright notice and this license shall be
   included in all copies or substantial portions of the Software.

2. **Don’t use the same license on more than one project.** Each licensed copy
   of the Software shall be actively installed in no more than one production
   environment at a time.

3. **Don’t mess with the licensing features.** Software features related to
   licensing shall not be altered or circumvented in any way, including (but
   not limited to) license validation, payment prompts, feature restrictions,
   and update eligibility.

4. **Pay up.** Payment shall be made immediately upon receipt of any notice,
   prompt, reminder, or other message indicating that a payment is owed.

5. **Follow the law.** All use of the Software shall not violate any applicable
   law or regulation, nor infringe the rights of any other person or entity.

Failure to comply with the foregoing conditions will automatically and
immediately result in termination of the permission granted hereby. This
license does not include any right to receive updates to the Software or
technical support. Licensees bear all risk related to the quality and
performance of the Software and any modifications made or obtained to it,
including liability for actual and consequential harm, such as loss or
corruption of data, and any necessary service, repair, or correction.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES, OR OTHER
LIABILITY, INCLUDING SPECIAL, INCIDENTAL AND CONSEQUENTIAL DAMAGES, WHETHER IN
AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace benf\neo\integrations\feedme;

use benf\neo\Field as NeoField;
use Cake\Utility\Hash;
use craft\feedme\base\Field as BaseFeedMeField;
use craft\feedme\base\FieldInterface;
use craft\feedme\helpers\DataHelper;
use craft\feedme\Plugin;

/**
 * Neo field class for Feed Me.
 *
 * @package benf\neo\integrations\feedme
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.5.0
 */
class Field extends BaseFeedMeField implements FieldInterface
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public static string $name = 'Neo';

    /**
     * @var string
     */
    public static string $class = NeoField::class;

    // Templates
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function getMappingTemplate(): string
    {
        return 'neo/feed-me';
    }

    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public function parseField(): mixed
    {
        $preppedData = [];
        $fieldData = [];
        $complexFields = [];

        $blocks = Hash::get($this->fieldInfo, 'blocks');

        // Before we do anything, we need to extract the data from our feed and normalise it. This is especially
        // complex due to sub-fields, which each can be a variety of fields and formats, compounded by multiple or
        // Neo blocks - we don't know! We also need to be careful of the order data is in the feed to be
        // reflected in the field - phew!
        //
        // So, in order to keep data in the order provided in our feed, we start there (as opposed to looping through blocks)

        foreach ($this->feedData as $nodePath => $value) {
            // Get the field mapping info for this node in the feed
            $fieldInfo = $this->_getFieldMappingInfoForNodePath($nodePath, $blocks);

            // If this is data concerning our Neo field and blocks
            if ($fieldInfo) {
                $blockHandle = $fieldInfo['blockHandle'];
                $subFieldHandle = $fieldInfo['subFieldHandle'];
                $subFieldInfo = $fieldInfo['subFieldInfo'];
                $isComplexField = $fieldInfo['isComplexField'];

                $nodePathSegments = explode('/', $nodePath);
                $blockIndex = Hash::get($nodePathSegments, 1);

                if (!is_numeric($blockIndex)) {
                    // Try to check if it's only one-level deep (only importing one block type)
                    // which is particularly common for JSON.
                    $blockIndex = Hash::get($nodePathSegments, 2);

                    if (!is_numeric($blockIndex)) {
                        $blockIndex = 0;
                    }
                }

                $key = $blockIndex . '.' . $blockHandle . '.' . $subFieldHandle;

                // Check for complex fields (think Table, Super Table, etc), essentially anything that has
                // sub-fields, and doesn't have data directly mapped to the field itself. It needs to be
                // accumulated here (so it's in the right order), but grouped based on the field and block
                // it's in. A bit annoying, but no better ideas...
                if ($isComplexField) {
                    $complexFields[$key]['info'] = $subFieldInfo;
                    $complexFields[$key]['data'][$nodePath] = $value;
                    continue;
                }

                // Swap out the node-path stored in the field-mapping info, because
                // it'll be generic NeoBlock/Images not NeoBlock/0/Images/0 like we need
                $subFieldInfo['node'] = $nodePath;

                // Parse each field via their own fieldtype service
                $parsedValue = $this->_parseSubField($this->feedData, $subFieldHandle, $subFieldInfo);

                // Finish up with the content, also sort out cases where there's array content
                if (isset($fieldData[$key]) && is_array($fieldData[$key])) {
                    $fieldData[$key] = array_merge_recursive($fieldData[$key], $parsedValue);
                } else {
                    $fieldData[$key] = $parsedValue;
                }
            }
        }

        // Handle some complex fields that don't directly have nodes, but instead have nested properties mapped.
        // They have their mapping setup on sub-fields, and need to be processed all together, which we've already prepared.
        // Additionally, we only want to supply each field with a sub-set of data related to that specific block and field
        // otherwise, we get the field class processing all blocks in one go - not what we want.
        foreach ($complexFields as $key => $complexInfo) {
            $parts = explode('.', $key);
            $subFieldHandle = $parts[2];

            $subFieldInfo = Hash::get($complexInfo, 'info');
            $nodePaths = Hash::get($complexInfo, 'data');

            $parsedValue = $this->_parseSubField($nodePaths, $subFieldHandle, $subFieldInfo);

            if (isset($fieldData[$key])) {
                $fieldData[$key] = array_merge_recursive($fieldData[$key], $parsedValue);
            } else {
                $fieldData[$key] = $parsedValue;
            }
        }

        ksort($fieldData, SORT_NUMERIC);

        // New, we've got a collection of prepared data, but it's formatted a little rough, due to catering for
        // sub-field data that could be arrays or single values. Let's build our Neo-ready data
        $levelNodePath = Hash::get($this->fieldInfo, 'level.node');
        $levelNodePathArray = explode('/', $levelNodePath);
        array_splice($levelNodePathArray, 2, 0, [null]);
        foreach ($fieldData as $blockSubFieldHandle => $value) {
            $handles = explode('.', $blockSubFieldHandle);
            $blockIndex = 'new' . ($handles[0] + 1);
            $blockHandle = $handles[1];
            $subFieldHandle = $handles[2];
            array_splice($levelNodePathArray, 2, 1, $handles[0]);

            $disabled = Hash::get($this->fieldInfo, 'blocks.' . $blockHandle . '.disabled', false);
            $collapsed = Hash::get($this->fieldInfo, 'blocks.' . $blockHandle . '.collapsed', false);
            $level = DataHelper::fetchValue($this->feedData, ['node' => implode('/', $levelNodePathArray)]);

            // Prepare an array that's ready for Neo to import it
            $preppedData[$blockIndex . '.type'] = $blockHandle;
            $preppedData[$blockIndex . '.enabled'] = !$disabled;
            $preppedData[$blockIndex . '.collapsed'] = $collapsed;
            $preppedData[$blockIndex . '.level'] = $level ?? Hash::get($this->fieldInfo, 'default', 1);
            $preppedData[$blockIndex . '.fields.' . $subFieldHandle] = $value;
        }

        return Hash::expand($preppedData);
    }


    // Private Methods
    // =========================================================================

    /**
     * @param $nodePath
     * @param $blocks
     * @return array|null
     */
    private function _getFieldMappingInfoForNodePath($nodePath, $blocks): ?array
    {
        foreach ($blocks as $blockHandle => $blockInfo) {
            $fields = Hash::get($blockInfo, 'fields');

            $feedPath = preg_replace('/(\/\d+\/)/', '/', $nodePath);
            $feedPath = preg_replace('/^(\d+\/)|(\/\d+)/', '', $feedPath);

            foreach ($fields as $subFieldHandle => $subFieldInfo) {
                $node = Hash::get($subFieldInfo, 'node');

                $nestedFieldNodes = Hash::extract($subFieldInfo, 'fields.{*}.node');

                if ($nestedFieldNodes) {
                    foreach ($nestedFieldNodes as $nestedFieldNode) {
                        if ($feedPath == $nestedFieldNode) {
                            return [
                                'blockHandle' => $blockHandle,
                                'subFieldHandle' => $subFieldHandle,
                                'subFieldInfo' => $subFieldInfo,
                                'nodePath' => $nodePath,
                                'isComplexField' => true,
                            ];
                        }
                    }
                }

                if ($feedPath == $node || $node === 'usedefault') {
                    return [
                        'blockHandle' => $blockHandle,
                        'subFieldHandle' => $subFieldHandle,
                        'subFieldInfo' => $subFieldInfo,
                        'nodePath' => $nodePath,
                        'isComplexField' => false,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param $feedData
     * @param $subFieldHandle
     * @param $subFieldInfo
     * @return mixed
     */
    private function _parseSubField($feedData, $subFieldHandle, $subFieldInfo): mixed
    {
        $subFieldClassHandle = Hash::get($subFieldInfo, 'field');

        $subField = Hash::extract($this->field->getBlockTypeFields(), '{n}[handle=' . $subFieldHandle . ']')[0];

        $class = Plugin::$plugin->fields->getRegisteredField($subFieldClassHandle);
        $class->feedData = $feedData;
        $class->fieldHandle = $subFieldHandle;
        $class->fieldInfo = $subFieldInfo;
        $class->field = $subField;
        $class->element = $this->element;
        $class->feed = $this->feed;

        // Get our content, parsed by this field's service function
        return $class->parseField();
    }
}
