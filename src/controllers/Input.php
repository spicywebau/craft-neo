<?php

/*
The `Input::actionUpdateVisibleElements()` method is based on a large section of the
`\craft\controllers\ElementsController::actionSaveDraft()` method, from Craft CMS 4.3.6.1,
by Pixel & Tonic, Inc.
https://github.com/craftcms/cms/blob/4.3.6.1/src/controllers/ElementsController.php#L1113
Craft CMS is released under the terms of the Craft License, a copy of which is included below.
https://github.com/craftcms/cms/blob/4.3.6.1/LICENSE.md

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

namespace benf\neo\controllers;

use benf\neo\elements\Block;
use benf\neo\Plugin as Neo;
use Craft;
use craft\helpers\ArrayHelper;
use craft\web\Controller;
use craft\web\View;
use yii\web\Response;

/**
 * Class Input
 *
 * @package benf\neo\controllers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.0.0
 */
class Input extends Controller
{
    /**
     * Renders pasted or cloned input blocks.
     * @throws
     * @return Response
     */
    public function actionRenderBlocks(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $requestService = Craft::$app->getRequest();

        $blocks = $requestService->getRequiredBodyParam('blocks');
        $namespace = $requestService->getParam('namespace');

        // remove the ending section of the namespace since we're adding it back in renderBlocks. having it in will make it double up.
        $ex = explode('][', $namespace);
        $namespace = $ex[0] . ']';

        $siteId = $requestService->getParam('siteId');
        $renderedBlocks = [];

        foreach ($blocks as $rawBlock) {
            $type = Neo::$plugin->blockTypes->getById((int)$rawBlock['type']);
            $block = new Block();
            if (isset($rawBlock['ownerId']) && $rawBlock['ownerId']) {
                $block->ownerId = $rawBlock['ownerId'];
            }
            $block->typeId = $rawBlock['type'];
            $block->level = $rawBlock['level'];
            $block->enabled = isset($rawBlock['enabled']) && (bool)$rawBlock['enabled'];
            $block->setCollapsed(isset($rawBlock['collapsed']) && (bool)$rawBlock['collapsed']);
            $block->siteId = $siteId ?? Craft::$app->getSites()->getPrimarySite()->id;

            if (!empty($rawBlock['content'])) {
                $block->setFieldValues($rawBlock['content']);
            }

            $renderedBlocks[] = [
                'type' => (int)$type->id,
                'level' => $block->level,
                'enabled' => $block->enabled,
                'collapsed' => $block->getCollapsed(),
                'tabs' => Neo::$plugin->blocks->renderTabs($block, $namespace),
            ];
        }

        return $this->asJson([
            'success' => true,
            'blocks' => $renderedBlocks,
        ]);
    }

    /**
     * Saves the expanded/collapsed state of a block.
     *
     * @return Response
     */
    public function actionSaveExpansion(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $requestService = Craft::$app->getRequest();
        $sitesService = Craft::$app->getSites();

        $expanded = $requestService->getRequiredParam('expanded');
        $blockId = $requestService->getRequiredParam('blockId');

        // If the `siteId` parameter wasn't passed, then this Craft installation has only one site, thus we can just
        // grab the primary site ID.
        $siteId = $requestService->getParam('siteId', $sitesService->getPrimarySite()->id);

        $return = $this->asJson([
            'success' => false,
            'blockId' => $blockId,
            'siteId' => $siteId,
        ]);

        $block = $blockId ? Neo::$plugin->blocks->getBlockById($blockId, $siteId) : null;

        // Only set the collapsed state if `collapseAllBlocks` is disabled; if `collapseAllBlocks` is enabled, a block's
        // original collapsed state will be preserved in case the setting is disabled in the future
        if ($block && !Neo::$plugin->getSettings()->collapseAllBlocks) {
            $block->setCollapsed(!$expanded);
            $block->cacheCollapsed();

            // Also set the canonical block to the new state if this is a derivative block owned by a provisional draft
            if ($block->getOwner()->isProvisionalDraft && !$block->getIsCanonical()) {
                $canonicalBlock = $block->getCanonical();
                $canonicalBlock->setCollapsed(!$expanded);
                $canonicalBlock->cacheCollapsed();
            }

            $return = $this->asJson([
                'success' => true,
                'blockId' => $blockId,
                'siteId' => $siteId,
                'expanded' => !$block->getCollapsed(),
            ]);
        }

        return $return;
    }

    /**
     * Gets data for which fields on a block should be visible.
     *
     * @return Response
     * @since 3.7.0
     */
    public function actionUpdateVisibleElements(): Response
    {
        $this->requireAcceptsJson();
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $elementsService = Craft::$app->getElements();
        $fieldsService = Craft::$app->getFields();
        $blocksData = $request->getRequiredBodyParam('blocks');
        $fieldId = $request->getRequiredBodyParam('fieldId');
        $ownerCanonicalId = $request->getRequiredBodyParam('ownerCanonicalId');
        $ownerDraftId = $request->getRequiredBodyParam('ownerDraftId');
        $isProvisionalDraft = $request->getRequiredBodyParam('isProvisionalDraft');
        $siteId = $request->getRequiredBodyParam('siteId');
        $sortOrder = $request->getRequiredBodyParam('sortOrder');

        $field = $fieldsService->getFieldById($fieldId);
        $canonicalOwner = $elementsService->getElementById($ownerCanonicalId, null, $siteId);
        $draftsQueryMethod = $isProvisionalDraft ? 'provisionalDrafts' : 'drafts';

        // Get the blocks belonging to the current draft
        $draft = $canonicalOwner::find()
            ->{$draftsQueryMethod}()
            ->draftId($ownerDraftId)
            ->siteId($siteId)
            ->status(null)
            ->one();
        $blocks = $draft
            ->getFieldValue($field->handle)
            ->status(null)
            ->all();

        foreach ($blocks as $block) {
            $block->useMemoized($blocks);
        }

        $blockIds = array_map(fn($block) => $block->id, $blocks);
        $returnedBlocksData = [];

        foreach ($sortOrder as $i => $blockId) {
            $blockData = $blocksData[$blockId];

            // For block data with ID `newX`, make sure we get the saved version of the block
            $block = ArrayHelper::firstWhere(
                $blocks,
                'id',
                (int)(is_numeric($blockId) ? $blockId : $blockIds[$i])
            );

            if ($block === null) {
                continue;
            }

            $view = Craft::$app->getView();
            $namespace = "fields[{$field->handle}][blocks][{$block->id}]";
            $fieldLayout = $block->getFieldLayout();
            $form = $fieldLayout->createForm($block, false, [
                'namespace' => $namespace,
                'registerDeltas' => false,
                'visibleElements' => $blockData['visibleLayoutElements'],
            ]);
            $missingElements = [];
            foreach ($form->tabs as $tab) {
                if (!$tab->getUid()) {
                    continue;
                }

                $elementInfo = [];

                foreach ($tab->elements as [$layoutElement, $isConditional, $elementHtml]) {
                    /** @var FieldLayoutComponent $layoutElement */
                    /** @var bool $isConditional */
                    /** @var string|bool $elementHtml */
                    if ($isConditional) {
                        $elementInfo[] = [
                            'uid' => $layoutElement->uid,
                            'html' => $elementHtml,
                        ];
                    }
                }

                $missingElements[] = [
                    'uid' => $tab->getUid(),
                    'id' => $tab->getId(),
                    'elements' => $elementInfo,
                ];
            }

            $tabs = $form->tabs;
            if (count($tabs) > 1) {
                $selectedTab = isset($blockData['selectedTab']) && isset($tabs[$blockData['selectedTab']])
                    ? $blockData['selectedTab']
                    : null;
                $tabHtml = $view->namespaceInputs(fn() => $view->renderTemplate('neo/_tabs.twig', [
                    'tabs' => $tabs,
                    'selectedTab' => $selectedTab,
                    'block' => $block,
                ], View::TEMPLATE_MODE_CP), $namespace);
            } else {
                $tabHtml = null;
            }

            $returnedBlocksData[$blockId] = [
                'tabs' => $tabHtml,
                'missingElements' => $missingElements,
                'initialDeltaValues' => $view->getInitialDeltaValues(),
                'headHtml' => $view->getHeadHtml(),
                'bodyHtml' => $view->getBodyHtml(),
            ];
        }

        return $this->asSuccess(data: [
            'blocks' => $returnedBlocksData,
        ]);
    }
}
