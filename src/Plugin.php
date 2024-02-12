<?php

namespace benf\neo;

use benf\neo\controllers\Configurator as ConfiguratorController;
use benf\neo\controllers\Conversion as ConversionController;
use benf\neo\controllers\Input as InputController;
use benf\neo\elements\Block;
use benf\neo\fieldlayoutelements\ChildBlocksUiElement;
use benf\neo\gql\interfaces\elements\Block as NeoGqlInterface;
use benf\neo\integrations\feedme\Field as FeedMeField;
use benf\neo\models\Settings;
use benf\neo\services\Blocks as BlocksService;
use benf\neo\services\BlockTypes as BlockTypesService;
use benf\neo\services\Conversion as ConversionService;
use benf\neo\services\Fields as FieldsService;
use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\console\Application as ConsoleApplication;
use craft\console\Controller;
use craft\console\controllers\ResaveController;
use craft\db\Query;
use craft\db\Table;
use craft\elements\conditions\SlugConditionRule;
use craft\elements\GlobalSet;
use craft\events\DefineConsoleActionsEvent;
use craft\events\DefineFieldLayoutElementsEvent;
use craft\events\RebuildConfigEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterGqlTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\feedme\events\RegisterFeedMeFieldsEvent;
use craft\feedme\services\Fields as FeedMeFields;
use craft\fields\Assets;
use craft\gatsbyhelper\events\RegisterIgnoredTypesEvent;
use craft\gatsbyhelper\services\Deltas;
use craft\helpers\Console;
use craft\helpers\Db;
use craft\models\FieldLayout;
use craft\services\Fields;
use craft\services\Gc;
use craft\services\Gql;
use craft\services\ProjectConfig;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

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
     * @var Plugin|null
     */
    public static ?Plugin $plugin = null;

    /**
     * @inheritdoc
     */
    public string $schemaVersion = '4.0.0';

    /**
     * @inheritdoc
     */
    public string $minVersionRequired = '2.13.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @inheritdoc
     */
    public $controllerMap = [
        'configurator' => ConfiguratorController::class,
        'conversion' => ConversionController::class,
        'input' => InputController::class,
    ];

    /**
     * @var bool
     */
    public static bool $isGeneratingConditionHtml = false;

    /**
     * @inheritdoc
     */
    public function init(): void
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
        $this->_registerGarbageCollection();
        $this->_registerChildBlocksUiElement();
        $this->_registerResaveBlocksCommand();
        $this->_registerPermissions();
        $this->_registerGatsbyHelper();
        $this->_registerFeedMeSupport();
        $this->_registerConditionFieldRuleRemoval();
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('neo/plugin-settings', [
            'settings' => $this->getSettings(),
            'blockTypeIconSourceOptions' => Craft::$app->getFields()
                ->createField(Assets::class)
                ->getSourceOptions(),
        ]);
    }

    /**
     * Registers the Neo field type.
     */
    private function _registerFieldType(): void
    {
        Event::on(Fields::class, Fields::EVENT_REGISTER_FIELD_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = Field::class;
        });
    }

    /**
     * Registers the `craft.neo` Twig variable.
     */
    private function _registerTwigVariable(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            $event->sender->set('neo', Variable::class);
        });
    }

    /**
     * Registers Neo's GraphQL type.
     */
    private function _registerGqlType(): void
    {
        Event::on(Gql::class, Gql::EVENT_REGISTER_GQL_TYPES, function(RegisterGqlTypesEvent $event) {
            $event->types[] = NeoGqlInterface::class;
        });
    }

    /**
     * Listens for Neo updates in the project config to apply them to the database.
     */
    private function _registerProjectConfigApply(): void
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
    private function _registerProjectConfigRebuild(): void
    {
        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $event) {
            $blockTypeData = [];
            $blockTypeGroupData = [];
            $sortOrderData = [];

            foreach ($this->blockTypes->getAllBlockTypes() as $blockType) {
                $config = $blockType->getConfig();
                $sortOrderData[$config['field']][$config['sortOrder'] - 1] = "blockType:$blockType->uid";
                unset($config['sortOrder']);
                $blockTypeData[$blockType['uid']] = $config;
            }

            foreach ($this->blockTypes->getAllBlockTypeGroups() as $blockTypeGroup) {
                $config = $blockTypeGroup->getConfig();
                $sortOrderData[$config['field']][$config['sortOrder'] - 1] = "blockTypeGroup:$blockTypeGroup->uid";
                unset($config['sortOrder']);
                $blockTypeGroupData[$blockTypeGroup['uid']] = $config;
            }

            // Reset the sort order array keys, in case anything's been deleted recently
            foreach ($sortOrderData as $fieldUid => $order) {
                $sortOrderData[$fieldUid] = array_values($order);
            }

            $event->config['neo'] = [
                'orders' => $sortOrderData,
            ];
            $event->config['neoBlockTypes'] = $blockTypeData;
            $event->config['neoBlockTypeGroups'] = $blockTypeGroupData;
        });
    }

    private function _registerGarbageCollection(): void
    {
        Event::on(Gc::class, Gc::EVENT_RUN, function() {
            $stdout = function(string $string, ...$format) {
                if (Craft::$app instanceof ConsoleApplication) {
                    Console::stdout($string, ...$format);
                }
            };
            $gc = Craft::$app->getGc();
            $gc->deletePartialElements(Block::class, '{{%neoblocks}}', 'id');

            // Delete anything in the structures table that's a Neo block structure, but doesn't exist in the
            // neoblockstructures table
            $stdout('    > deleting orphaned Neo block structure data ... ');
            $neoStructureIds = (new Query())
                ->select(['structureId'])
                ->from(['se' => Table::STRUCTUREELEMENTS])
                ->innerJoin(['nb' => '{{%neoblocks}}'], '[[se.elementId]] = [[nb.id]]')
                ->column();
            $neoStructureIdsNotToDelete = (new Query())
                ->select(['structureId'])
                ->from('{{%neoblockstructures}}')
                ->column();
            $neoStructureIdsToDelete = array_diff($neoStructureIds, $neoStructureIdsNotToDelete);

            foreach (array_chunk($neoStructureIdsToDelete, 1000) as $neoStructureIdChunk) {
                Db::delete(Table::STRUCTURES, [
                    'id' => $neoStructureIdChunk,
                ]);
            }

            $stdout("done\n", Console::FG_GREEN);
        });
    }

    private function _registerChildBlocksUiElement(): void
    {
        Event::on(FieldLayout::class, FieldLayout::EVENT_DEFINE_UI_ELEMENTS, function(DefineFieldLayoutElementsEvent $event) {
            if ($event->sender->type === Block::class) {
                $event->elements[] = ChildBlocksUiElement::class;
            }
        });
    }

    private function _registerResaveBlocksCommand(): void
    {
        Event::on(ResaveController::class, Controller::EVENT_DEFINE_ACTIONS, function(DefineConsoleActionsEvent $event) {
            $event->actions['neo-blocks'] = [
                'helpSummary' => 'Re-saves Neo blocks.',
                'options' => ['field', 'type'],
                'optionsHelp' => [
                    'field' => 'The field handle to save Neo blocks for.',
                    'type' => 'The block type handle(s) of the Neo blocks to resave.',
                ],
                'action' => function(): int {
                    $controller = Craft::$app->controller;
                    $criteria = [];
                    if ($controller->field !== null) {
                        $criteria['field'] = explode(',', $controller->field);
                    }
                    if ($controller->type !== null) {
                        $criteria['type'] = explode(',', $controller->type);
                    }
                    return $controller->resaveElements(Block::class, $criteria);
                },
            ];
        });
    }

    private function _registerPermissions(): void
    {
        // Only if the settings allow it
        if (!$this->getSettings()->enableBlockTypeUserPermissions) {
            return;
        }

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                foreach ($this->fields->getNeoFields() as $field) {
                    $blockTypePermissions = [];

                    foreach ($field->getBlockTypes() as $blockType) {
                        $blockTypePermissions["neo-editBlocks:{$blockType->uid}"] = [
                            'label' => Craft::t('neo', 'Edit {blockType} blocks', [
                                'blockType' => $blockType->name,
                            ]),
                            'nested' => [
                                "neo-createBlocks:{$blockType->uid}" => [
                                    'label' => Craft::t('neo', 'Create blocks'),
                                ],
                                "neo-deleteBlocks:{$blockType->uid}" => [
                                    'label' => Craft::t('neo', 'Delete blocks'),
                                ],
                            ],
                        ];
                    }

                    $event->permissions[] = [
                        'heading' => Craft::t('neo', 'Neo - {field}', [
                            'field' => $field->name,
                        ]),
                        'permissions' => $blockTypePermissions,
                    ];
                }
            }
        );
    }

    private function _registerGatsbyHelper()
    {
        if (class_exists(Deltas::class)) {
            Event::on(Deltas::class, Deltas::EVENT_REGISTER_IGNORED_TYPES, function(RegisterIgnoredTypesEvent $event) {
                $event->types[] = Block::class;
            });
        }
    }

    private function _registerFeedMeSupport(): void
    {
        if (class_exists(FeedMeFields::class)) {
            Event::on(
                FeedMeFields::class,
                FeedMeFields::EVENT_REGISTER_FEED_ME_FIELDS,
                function(RegisterFeedMeFieldsEvent $e) {
                    $e->fields[] = FeedMeField::class;
                }
            );
        }
    }

    /**
     * Removes any condition rules related to fields when generating condition builders for the block type settings.
     */
    private function _registerConditionFieldRuleRemoval()
    {
        Event::on(
            BaseCondition::class,
            BaseCondition::EVENT_REGISTER_CONDITION_RULES,
            function(RegisterConditionRulesEvent $event) {
                if (self::$isGeneratingConditionHtml) {
                    $event->conditionRules = array_filter(
                        $event->conditionRules,
                        function($type) use ($event) {
                            // No field value conditions allowed as it may make existing blocks invalid
                            if (isset($type['fieldUid'])) {
                                return false;
                            }

                            // Global sets don't have slugs
                            if ($event->sender->elementType === GlobalSet::class && $type === SlugConditionRule::class) {
                                return false;
                            }

                            // Everything else is okay
                            return true;
                        }
                    );
                }
            }
        );
    }
}
