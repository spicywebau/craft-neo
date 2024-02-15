<?php

/*
The `ApplyNeoPropagationMethod::processItem()` method is based on a large section of the
`\craft\queue\jobs\ApplyNewPropagationMethod::processItem()` method, from Craft CMS 4.7.2.1, by
Pixel & Tonic, Inc.
https://github.com/craftcms/cms/blob/4.7.2.1/src/queue/jobs/ApplyNewPropagationMethod.php#L73
Craft CMS is released under the terms of the Craft License, a copy of which is included below.
https://github.com/craftcms/cms/blob/4.7.2.1/LICENSE.md

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

namespace benf\neo\jobs;

use benf\neo\elements\Block;
use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Table;
use craft\errors\UnsupportedSiteException;
use craft\helpers\Db;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use craft\i18n\Translation;
use craft\queue\jobs\ApplyNewPropagationMethod;
use craft\services\Structures;
use Throwable;

/**
 * Class ApplyNeoPropagationMethod
 *
 * @package benf\neo\jobs
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 4.0.5
 */
class ApplyNeoPropagationMethod extends ApplyNewPropagationMethod
{
    /**
     * @inheritdoc
     */
    public string $elementType = Block::class;

    /**
     * @var array
     */
    public array $structureData;

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        parent::execute($queue);

        // Ensure block structures are regenerated after duplicating the blocks
        if ($this->itemOffset === $this->totalItems()) {
            foreach ($this->structureData as $fieldId => $fieldStructureData) {
                Queue::push(new ResaveFieldBlockStructures([
                    'fieldId' => $fieldId,
                    'structureOverrides' => $fieldStructureData,
                ]));
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function processItem(mixed $item): void
    {
        // Skip revisions
        try {
            if (ElementHelper::isRevision($item)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        $elementsService = Craft::$app->getElements();
        $structuresService = Craft::$app->getStructures();
        $allSiteIds = Craft::$app->getSites()->getAllSiteIds();

        $this->_setStructureData(
            $item->fieldId,
            $item->siteId,
            $item->ownerId,
            $item->id,
            $item->level,
            $item->lft,
            $item->rgt,
        );

        // See what sites the element should exist in going forward
        /** @var ElementInterface $item */
        $newSiteIds = array_map(
            fn(array $siteInfo) => $siteInfo['siteId'],
            ElementHelper::supportedSitesForElement($item),
        );

        // What other sites are there?
        $otherSiteIds = array_diff($allSiteIds, $newSiteIds);

        if (empty($otherSiteIds)) {
            return;
        }

        // Load the element in any sites that it's about to be deleted for
        $otherSiteBlocks = $item::find()
            ->id($item->id)
            ->siteId($otherSiteIds)
            ->structureId($item->structureId)
            ->status(null)
            ->drafts(null)
            ->provisionalDrafts(null)
            ->orderBy([])
            ->indexBy('siteId')
            ->all();

        if (empty($otherSiteBlocks)) {
            return;
        }

        // Remove their URIs so the duplicated elements can retain them w/out needing to increment them
        Db::update(Table::ELEMENTS_SITES, [
            'uri' => null,
        ], [
            'id' => array_map(fn(ElementInterface $element) => $element->siteSettingsId, $otherSiteBlocks),
        ], [], false);

        // Duplicate those elements so their content can live on
        while (!empty($otherSiteBlocks)) {
            /** @var ElementInterface $otherSiteElement */
            $otherSiteBlock = array_pop($otherSiteBlocks);
            try {
                $newBlock = $elementsService->duplicateElement($otherSiteBlock, [], false);
            } catch (UnsupportedSiteException $e) {
                // Just log it and move along
                Craft::warning(sprintf(
                    "Unable to duplicate “%s” to site %d: %s",
                    get_class($otherSiteBlock),
                    $otherSiteBlock->siteId,
                    $e->getMessage()
                ));
                Craft::$app->getErrorHandler()->logException($e);
                continue;
            }

            $this->_setStructureData(
                $newBlock->fieldId,
                $newBlock->siteId,
                $newBlock->ownerId,
                $newBlock->id,
                $item->level,
                $item->lft,
                $item->rgt,
            );

            // This may support more than just the site it was saved in
            $newBlockSiteIds = array_map(
                fn(array $siteInfo) => $siteInfo['siteId'],
                ElementHelper::supportedSitesForElement($newBlock),
            );
            foreach ($newBlockSiteIds as $newBlockSiteId) {
                unset($otherSiteBlocks[$newBlockSiteId]);
                $this->duplicatedElementIds[$item->id][$newBlockSiteId] = $newBlock->id;
            }
        }

        // Now resave the original element
        $item->setScenario(Element::SCENARIO_ESSENTIALS);
        $item->resaving = true;

        try {
            $elementsService->saveElement($item, updateSearchIndex: false);
        } catch (Throwable $e) {
            Craft::$app->getErrorHandler()->logException($e);
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Translation::prep('neo', 'Applying new propagation method to Neo blocks');
    }

    private function _setStructureData(
        int $fieldId,
        int $siteId,
        int $ownerId,
        int $blockId,
        int $level,
        int $lft,
        int $rgt,
    ) {
        $this->structureData[$fieldId][$siteId][$ownerId][$blockId] = [
            'level' => $level,
            'lft' => $lft,
            'rgt' => $rgt,
        ];
    }
}
