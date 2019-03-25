<?php
namespace benf\neo\integrations\fieldlabels;

use yii\base\Event;

use Craft;
use craft\services\Fields;

use benf\neo\Plugin as Neo;
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
	/**
	 * Field Labels initialisation function to be called inside the Neo Plugin init method.
	 */
	public static function init()
	{
		$request = Craft::$app->getRequest();

		if ($request->getIsCpRequest() && !$request->getIsConsoleRequest())
		{
			Event::on(Fields::class, Fields::EVENT_AFTER_SAVE_FIELD_LAYOUT, function(Event $event) {
				$fieldLayout = $event->layout;
				$postData = Craft::$app->getRequest()->getBodyParam('neo');
				$blockType = Neo::$plugin->blockTypes->currentSavingBlockType;

				if ($blockType && $postData && isset($postData['fieldlabels']))
				{
					$fieldlabelsPost = $postData['fieldlabels'];

					if ($fieldlabelsPost && array_key_exists($blockType->id, $fieldlabelsPost))
					{
						FieldLabelsPlugin::$plugin->methods->saveLabels($fieldlabelsPost[$blockType->id], $fieldLayout->id);
					}
				}
			});
		}
	}
}
