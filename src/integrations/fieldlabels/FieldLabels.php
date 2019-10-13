<?php

namespace benf\neo\integrations\fieldlabels;

use yii\base\Event;

use Craft;
use craft\services\Fields;

use benf\neo\Plugin as Neo;
use benf\neo\services\BlockTypes;
use spicyweb\fieldlabels\Plugin as FieldLabelsPlugin;

/**
 * Class FieldLabels
 * Implements support for the Field Labels plugin.
 *
 * @see https://github.com/spicywebau/craft-fieldlabels
 * @package benf\neo\integrations\fieldlabels
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
 * @since 2.3.0
 */
class FieldLabels
{
	private $_uidIdMap = [];
	
	/**
	 * Field Labels initialisation function to be called inside the Neo Plugin init method.
	 */
	public function init()
	{
		$request = Craft::$app->getRequest();
		
		if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
			// Record the pre-save IDs so we can still save labels for new block types, since the `newX` block type IDs
			// that the Field Labels POST data has will be lost
			Event::on(BlockTypes::class, BlockTypes::EVENT_BEFORE_SAVE_BLOCK_TYPE, function (Event $event) {
				$this->_uidIdMap[$event->blockType->uid] = $event->blockType->id;
			});
			
			Event::on(BlockTypes::class, BlockTypes::EVENT_AFTER_SAVE_BLOCK_TYPE, function (Event $event) {
				$postData = Craft::$app->getRequest()->getBodyParam('neo');
				$blockType = $event->blockType;
				$layoutFieldIds = Craft::$app->getFields()->getFieldIdsByLayoutId($blockType->fieldLayoutId);
				$fieldLabelsPost = null;
				$doesTheBlockTypeIdKeyExist = null;
				
				// Make sure we get the pre-save IDs so we can save labels for new block types
				$blockTypeId = $this->_uidIdMap[$blockType->uid];
				
				if ($blockType && $blockType->fieldLayoutId && $postData && isset($postData['fieldlabels'])) {
					$fieldLabelsPost = $postData['fieldlabels'];
					
					$doesTheBlockTypeIdKeyExist = array_key_exists($blockTypeId, $fieldLabelsPost);
					
					if ($fieldLabelsPost && $doesTheBlockTypeIdKeyExist) {
						// FieldLabelsPlugin::$plugin->methods->saveLabels($fieldlabelsPost[$blockTypeId][0], $blockType->fieldLayoutId);
						FieldLabelsPlugin::$plugin->methods->saveLabels($fieldLabelsPost[$blockTypeId],
							$blockType->fieldLayoutId);
					}
				}
				
				// get all labeled
				if ($fieldLabelsPost === null) {
					$labelledFieldIds = [];
				} else {
					$labelledFieldIds = $doesTheBlockTypeIdKeyExist ? array_keys($fieldLabelsPost[$blockTypeId]) : [];
				}
				
				$unlabelledFieldIds = array_filter($layoutFieldIds,
					function ($fieldId) use ($labelledFieldIds) {
						return !in_array($fieldId, $labelledFieldIds);
					});
				
				foreach ($unlabelledFieldIds as $fieldId) {
					if (
						($label = FieldLabelsPlugin::$plugin->methods->getLabel($blockType->fieldLayoutId,
							$fieldId)) !== null
					) {
						FieldLabelsPlugin::$plugin->methods->deleteLabel($label);
					}
				}
			});
		}
	}
}
