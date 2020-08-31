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
 * @author Spicy Web <plugins@spicyweb.com.au>
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
    public $schemaVersion = '2.8.0';

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

        $this->_registerFieldType();
        $this->_registerTwigVariable();
        $this->_registerGqlType();
        $this->_registerProjectConfigApply();
        $this->_registerProjectConfigRebuild();
        $this->_setupBlocksHasSortOrder();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    /**
     * Registers the Neo field type.
     */
    private function _registerFieldType()
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event){
            $event->types[] = Field::class;
        });
    }

    /**
     * Registers the `craft.neo` Twig variable.
     */
    private function _registerTwigVariable()
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('neo', Variable::class);
        });
    }

    /**
     * Registers Neo's GraphQL type.
     */
    private function _registerGqlType()
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = NeoGqlInterface::class;
        });
    }

    /**
     * Listens for Neo updates in the project config to apply them to the database.
     */
    private function _registerProjectConfigApply()
    {
        Craft::$app->getProjectConfig()
            ->onAdd('neoBlockTypes.{uid}', [$this->blockTypes, 'handleChangedBlockType'])
            ->onUpdate('neoBlockTypes.{uid}', [$this->blockTypes, 'handleChangedBlockType'])
            ->onRemove('neoBlockTypes.{uid}', [$this->blockTypes, 'handleDeletedBlockType'])
            ->onAdd('neoBlockTypeGroups.{uid}', [$this->blockTypes, 'handleChangedBlockTypeGroup'])
            ->onUpdate('neoBlockTypeGroups.{uid}', [$this->blockTypes, 'handleChangedBlockTypeGroup'])
            ->onRemove('neoBlockTypeGroups.{uid}', [$this->blockTypes, 'handleDeletedBlockTypeGroup']);
    }

    /**
     * Registers an event listener for a project config rebuild, and provides the Neo data from the database.
     */
    private function _registerProjectConfigRebuild()
    {
        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event)
        {
            $fieldsService = Craft::$app->getFields();
            $blockTypeData = [];
            $blockTypeGroupData = [];
            $layoutIds = [];
            $selectColumns = [
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
            ];

            // We need to check for `maxSiblingBlocks` because if Field Labels 1.3's migrations (which execute a project
            // config rebuild) run before Neo 2.8's, then `maxSiblingBlocks` won't exist yet
            // TODO: remove this in Neo 2.9
            $maxSiblingBlocks = Craft::$app->getDb()
                ->getSchema()
                ->getTableSchema('{{%neoblocktypes}}')
                ->getColumn('maxSiblingBlocks');

            if ($maxSiblingBlocks !== null) {
                $selectColumns[] = 'types.maxSiblingBlocks';
            }

            $blockTypeQuery = (new Query())
                ->select($selectColumns)
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

                if ($maxSiblingBlocks !== null) {
                    $blockTypeData[$blockType['uid']]['maxSiblingBlocks'] = (int)$blockType['maxSiblingBlocks'];
                }

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

                // We need to check if `$layoutsData[$layoutId]` exists: `$this->_getFieldLayoutsData($layoutIds)` won't
                // contain field layouts that only contained blank tab(s), which might be the case if a Craft install
                // was upgraded from Craft 3.4 / Neo 2.7 or earlier (when Neo was able to support blank tabs) or if the
                // field(s) that belonged to the blank tab(s) were deleted.
                if ($layoutId !== null && isset($layoutsData[$layoutId])) {
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
