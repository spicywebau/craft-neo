<?php

namespace benf\neo;

use benf\neo\controllers\Configurator as ConfiguratorController;
use benf\neo\controllers\Conversion as ConversionController;
use benf\neo\controllers\Input as InputController;
use benf\neo\elements\Block;
use benf\neo\fieldlayoutelements\ChildBlocksUiElement;
use benf\neo\gql\interfaces\elements\Block as NeoGqlInterface;
use benf\neo\models\Settings;
use benf\neo\services\Blocks as BlocksService;
use benf\neo\services\BlockTypes as BlockTypesService;
use benf\neo\services\Conversion as ConversionService;
use benf\neo\services\Fields as FieldsService;
use Craft;
use craft\base\Plugin as BasePlugin;
use craft\db\Table;
use craft\events\DefineFieldLayoutElementsEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;

use craft\events\RegisterGqlTypesEvent;
use craft\gatsbyhelper\events\RegisterIgnoredTypesEvent;
use craft\gatsbyhelper\services\Deltas;
use craft\models\FieldLayout;
use craft\services\Fields;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
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
    public string $schemaVersion = '2.13.0';

    /**
     * @inheritdoc
     */
    public $controllerMap = [
        'configurator' => ConfiguratorController::class,
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
        $this->_registerGarbageCollection();
        $this->_registerChildBlocksUiElement();
        $this->_registerGatsbyHelper();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?\craft\base\Model
    {
        return new Settings();
    }

    /**
     * Registers the Neo field type.
     */
    private function _registerFieldType()
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
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
        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $blockTypeData = [];
            $blockTypeGroupData = [];

            foreach ($this->blockTypes->getAllBlockTypes() as $blockType) {
                $blockTypeData[$blockType['uid']] = $blockType->getConfig();
            }

            foreach ($this->blockTypes->getAllBlockTypeGroups() as $blockTypeGroup) {
                $blockTypeGroupData[$blockTypeGroup['uid']] = $blockTypeGroup->getConfig();
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

    private function _registerGarbageCollection()
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function() {
            $gc = Craft::$app->getGc();
            $gc->deletePartialElements(Block::class, '{{%neoblocks}}', 'id');
            $gc->deletePartialElements(Block::class, Table::CONTENT, 'elementId');
        });
    }

    private function _registerChildBlocksUiElement()
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_UI_ELEMENTS, function(DefineFieldLayoutElementsEvent $event) {
            if ($event->sender->type === Block::class) {
                $event->elements[] = ChildBlocksUiElement::class;
            }
        });
    }

    private function _registerGatsbyHelper()
    {
        if (class_exists(Deltas::class)) {
            Event::on(Deltas::class, Deltas::EVENT_REGISTER_IGNORED_TYPES, function(RegisterIgnoredTypesEvent $event) {
                $event->types[] = Block::class;
            });
        }
    }
}
