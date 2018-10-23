<?php
namespace benf\neo;

use yii\base\Event;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\services\Fields;
use craft\events\RegisterComponentTypesEvent;
use craft\web\twig\variables\CraftVariable;

use benf\neo\services\Fields as FieldsService;
use benf\neo\services\BlockTypes as BlockTypesService;
use benf\neo\services\Blocks as BlocksService;
use benf\neo\services\Conversion as ConversionService;
use benf\neo\controllers\Input as InputController;

/**
 * Class Plugin
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
 * @since 2.0.0
 */
class Plugin extends BasePlugin
{
	/**
	 * @var Plugin
	 */
	public static $plugin;

	/**
	 * @inheritdoc
	 */
	public $schemaVersion = '2.0.0';

	/**
	 * @inheritdoc
	 */
	public $controllerMap = [
		'input' => InputController::class,
	];

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();

		self::$plugin = $this;

		$this->setComponents([
			'fields' => FieldsService::class,
			'blockTypes' => BlockTypesService::class,
			'blocks' => BlocksService::class,
			'conversion' => ConversionService::class,
		]);

		Craft::$app->view->registerTwigExtension(new TwigExtension());

		Event::on(
			Fields::class,
			Fields::EVENT_REGISTER_FIELD_TYPES,
			function(RegisterComponentTypesEvent $event)
			{
				$event->types[] = Field::class;
			}
		);

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function(Event $event)
			{
				$event->sender->set('neo', Variable::class);
			}
		);
	}
}
