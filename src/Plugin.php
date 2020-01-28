<?php
namespace benf\neo;

use yii\base\Event;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\db\Query;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\services\ProjectConfig;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterGqlTypesEvent;
use craft\services\Gql;

use benf\neo\controllers\Conversion as ConversionController;
use benf\neo\controllers\Input as InputController;
use benf\neo\integrations\fieldlabels\FieldLabels;
use benf\neo\models\Settings;
use benf\neo\services\Blocks as BlocksService;
use benf\neo\services\BlockTypes as BlockTypesService;
use benf\neo\services\Conversion as ConversionService;
use benf\neo\services\Fields as FieldsService;
use benf\neo\gql\interfaces\elements\Block as NeoGqlInterface;

/**
 * Class Plugin
 *
 * @package benf\neo
 * @author Spicy Web <craft@spicyweb.com.au>
 * @author Benjamin Fleming
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
	public $schemaVersion = '2.2.0';

	/**
	 * @inheritdoc
	 */
	public $controllerMap = [
		'conversion' => ConversionController::class,
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
		
		Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
		    // Add my GraphQL types
		    $event->types[] = NeoGqlInterface::class;
		});

		// Setup project config functionality
		$this->_setupProjectConfig();

		$pluginsService = Craft::$app->getPlugins();

		if ($pluginsService->isPluginInstalled('fieldlabels'))
		{
			(new FieldLabels)->init();
		}

		if (class_exists('\NerdsAndCompany\Schematic\Schematic')) {
			Event::on(
				\NerdsAndCompany\Schematic\Schematic::class, 
				\NerdsAndCompany\Schematic\Schematic::EVENT_RESOLVE_CONVERTER, 
				function(\NerdsAndCompany\Schematic\Events\ConverterEvent $event) {
					$modelClass = $event->modelClass;
					if (strpos($modelClass, __NAMESPACE__) !== false) {
						$converterClass = __NAMESPACE__.'\\converters\\'.str_replace(__NAMESPACE__.'\\', '', $modelClass);
						$event->converterClass = $converterClass;
					}
				}
			);
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function createSettingsModel(): Settings
	{
		return new Settings();
	}

	private function _setupProjectConfig()
	{
		// Listen for Neo updates in the project config to apply them to the database
		Craft::$app->getProjectConfig()
			->onAdd('neoBlockTypes.{uid}', [$this->blockTypes, 'handleChangedBlockType'])
			->onUpdate('neoBlockTypes.{uid}', [$this->blockTypes, 'handleChangedBlockType'])
			->onRemove('neoBlockTypes.{uid}', [$this->blockTypes, 'handleDeletedBlockType'])
			->onAdd('neoBlockTypeGroups.{uid}', [$this->blockTypes, 'handleChangedBlockTypeGroup'])
			->onUpdate('neoBlockTypeGroups.{uid}', [$this->blockTypes, 'handleChangedBlockTypeGroup'])
			->onRemove('neoBlockTypeGroups.{uid}', [$this->blockTypes, 'handleDeletedBlockTypeGroup']);

		// Listen for a project config rebuild, and provide the Neo data from the database
		Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event)
		{
			$fieldsService = Craft::$app->getFields();
			$blockTypeData = [];
			$blockTypeGroupData = [];

			$blockTypeQuery = (new Query)
				->select([
					// We require querying for the layout ID, rather than performing an inner join and getting the
					// layout UID that way, because Neo allows block types not to have field layouts
					'types.fieldLayoutId',
					'types.name',
					'types.handle',
					'types.maxBlocks',
					'types.maxChildBlocks',
					'types.childBlocks',
					'types.topLevel',
					'types.sortOrder',
					'types.uid',
					'fields.uid AS field',
				])
				->from(['{{%neoblocktypes}} types'])
				->innerJoin('{{%fields}} fields', '[[types.fieldId]] = [[fields.id]]');

			foreach ($blockTypeQuery->all() as $blockType)
			{
				$childBlocks = $blockType['childBlocks'];

				if (!empty($childBlocks))
				{
					$childBlocks = json_decode($childBlocks);
				}

				$blockTypeData[$blockType['uid']] = [
					'field' => $blockType['field'],
					'name' => $blockType['name'],
					'handle' => $blockType['handle'],
					'sortOrder' => (int)$blockType['sortOrder'],
					'maxBlocks' => (int)$blockType['maxBlocks'],
					'maxChildBlocks' => (int)$blockType['maxChildBlocks'],
					'childBlocks' => $childBlocks,
					'topLevel' => (bool)$blockType['topLevel'],
				];

				if ($blockType['fieldLayoutId'] !== null)
				{
					$fieldLayout = $fieldsService->getLayoutById($blockType['fieldLayoutId']);
					$fieldLayoutConfig = $fieldLayout->getConfig();
					if ($fieldLayoutConfig) {
						$blockTypeData[$blockType['uid']]['fieldLayouts'] = [
							$fieldLayout->uid => $fieldLayoutConfig,
						];
					}
				}

				unset($blockType['fieldLayoutId']);
			}

			$blockTypeGroupQuery = (new Query())
				->select([
					'groups.name',
					'groups.sortOrder',
					'groups.uid',
					'fields.uid AS field',
				])
				->from(['{{%neoblocktypegroups}} groups'])
				->innerJoin('{{%fields}} fields', '[[groups.fieldId]] = [[fields.id]]');

			foreach ($blockTypeGroupQuery->all() as $blockTypeGroup)
			{
				$blockTypeGroupData[$blockTypeGroup['uid']] = [
					'field' => $blockTypeGroup['field'],
					'name' => $blockTypeGroup['name'],
					'sortOrder' => (int)$blockTypeGroup['sortOrder'],
				];
			}

			$event->config['neoBlockTypes'] = $blockTypeData;
			$event->config['neoBlockTypeGroups'] = $blockTypeGroupData;
		});
	}
}
