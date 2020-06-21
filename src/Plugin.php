<?php
namespace benf\neo;

use yii\base\Event;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\db\Query;
use craft\db\Table;
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
use yii\base\NotSupportedException;

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
    public $schemaVersion = '2.3.0';

    /**
     * @inheritdoc
     */
    public $controllerMap = [
        'conversion' => ConversionController::class,
        'input' => InputController::class,
    ];
    
    public $blockHasSortOrder = true;

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

        if ($pluginsService->isPluginInstalled('fieldlabels')) {
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

        $this->_setupBlocksHasSortOrder();
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
            $layoutIds = [];

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
                ->innerJoin('{{%fields}} fields', '[[types.fieldId]] = [[fields.id]]')
                ->all();

            foreach ($blockTypeQuery as $blockType) {
                $childBlocks = $blockType['childBlocks'];

                if (!empty($childBlocks)) {
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

                if ($blockType['fieldLayoutId'] !== null) {
                    $layoutIds[] = $blockType['fieldLayoutId'];
                }
            }

            $layoutIdUidMap = (new Query())
                    ->select(['id', 'uid'])
                    ->from(Table::FIELDLAYOUTS)
                    ->where(['id' => $layoutIds])
                    ->pairs();

            $layoutsData = $this->_getFieldLayoutsData($layoutIds);

            foreach ($blockTypeQuery as $blockType) {
                $layoutId = $blockType['fieldLayoutId'];

                if ($layoutId !== null) {
                    $blockTypeData[$blockType['uid']]['fieldLayouts'] = [
                        $layoutIdUidMap[$layoutId] => $layoutsData[$layoutId],
                    ];

                    unset($blockTypeData[$blockType['uid']]['fieldLayoutId']);
                }
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

            foreach ($blockTypeGroupQuery->all() as $blockTypeGroup) {
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

    private function _setupBlocksHasSortOrder()
    {
        $dbService = Craft::$app->getDb();
        
        try {
            $this->blockHasSortOrder = $dbService->columnExists('{{%neoblocks}}', 'sortOrder');
        } catch (NotSupportedException $e) {
            $this->blockHasSortOrder = true;
        }
    }

    private function _getFieldLayoutsData(array $layoutIds)
    {
        $layoutData = [];
        $layoutFields = (new Query())
            ->select([
                'lf.required',
                'lf.sortOrder AS fieldSortOrder',
                'f.uid AS fieldUid',
                't.uid AS tabUid',
                't.name',
                't.sortOrder AS tabSortOrder',
                'l.id AS layoutId',
            ])
            ->from(['lf' => Table::FIELDLAYOUTFIELDS])
            ->innerJoin(['f' => Table::FIELDS], '[[f.id]] = [[lf.fieldId]]')
            ->innerJoin(['t' => Table::FIELDLAYOUTTABS], '[[lf.tabId]] = [[t.id]]')
            ->innerJoin(['l' => Table::FIELDLAYOUTS], '[[l.id]] = [[t.layoutId]]')
            ->where([
                'l.id' => $layoutIds,
                'l.dateDeleted' => null,
            ])
            ->orderBy([
                'tabSortOrder' => SORT_ASC,
                'fieldSortOrder' => SORT_ASC,
            ])
            ->all();

        foreach ($layoutFields as $layoutField) {
            $layoutId = $layoutField['layoutId'];
            $tabUid = $layoutField['tabUid'];

            $layoutData[$layoutId]['tabs'][$tabUid]['name'] = $layoutField['name'];
            $layoutData[$layoutId]['tabs'][$tabUid]['sortOrder'] = (int)$layoutField['tabSortOrder'];
            $layoutData[$layoutId]['tabs'][$tabUid]['fields'][$layoutField['fieldUid']] = [
                'required' => (bool)$layoutField['required'],
                'sortOrder' => (int)$layoutField['fieldSortOrder'],
            ];
        }

        foreach ($layoutData as &$layout) {
            $layout['tabs'] = array_values($layout['tabs']);
        }

        return $layoutData;
    }
}
