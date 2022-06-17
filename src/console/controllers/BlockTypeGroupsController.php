<?php

namespace benf\neo\console\controllers;

use benf\neo\enums\BlockTypeGroupDropdown;
use benf\neo\Plugin as Neo;
use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\helpers\Console;
use craft\helpers\Db;
use yii\console\ExitCode;

/**
 * Actions for managing Neo block type groups.
 *
 * @package benf\neo\console\controllers
 * @author Spicy Web <plugins@spicyweb.com.au>
 * @since 3.1.0
 */
class BlockTypeGroupsController extends Controller
{
    /**
     * @var int|null A block type group ID.
     */
    public ?int $groupId = null;

    /**
     * @var bool Whether to delete block types belonging to the block type group.
     */
    public bool $deleteBlockTypes = false;

    /**
     * @var ?int A new name to set for the block type group.
     */
    public ?string $setName = null;

    /**
     * @var bool Whether to set a blank name for the block type group.
     */
    public bool $blankName = false;

    /**
     * @var string What behaviour should be used for showing the block type group's dropdown (either 'show', 'hide', or 'global').
     */
    public ?string $dropdown = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'delete') {
            $options[] = 'groupId';
            $options[] = 'deleteBlockTypes';
        } elseif ($actionID === 'edit') {
            $options[] = 'groupId';
            $options[] = 'setName';
            $options[] = 'blankName';
            $options[] = 'dropdown';
        }

        return $options;
    }

    /**
     * Deletes a Neo block type group.
     *
     * @return int
     */
    public function actionDelete(): int
    {
        if (!$this->groupId) {
            $this->stderr('The --group-id option must be specified.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $group = Neo::$plugin->blockTypes->getGroupById($this->groupId);

        if ($group === null) {
            $this->stderr('The block type group ID specified does not exist.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $blockTypeUids = (new Query())
            ->select(['uid'])
            ->from('{{%neoblocktypes}}')
            ->where(['groupId' => $this->groupId])
            ->column();

        if ($this->deleteBlockTypes) {
            $this->stdout('Deleting the group\'s block types...' . PHP_EOL);
            foreach ($blockTypeUids as $blockTypeUid) {
                $projectConfig->remove('neoBlockTypes.' . $blockTypeUid);
            }
        } elseif (!empty($blockTypeUids)) {
            $this->stdout('Reassigning the group\'s block types to the field\'s previous group...' . PHP_EOL);
            $prevGroupUid = (new Query())
                ->select(['uid'])
                ->from(['{{%neoblocktypegroups}}'])
                ->where(['fieldId' => $group->fieldId])
                ->andWhere(['<', 'sortOrder', $group->sortOrder])
                ->orderBy(['sortOrder' => SORT_DESC])
                ->scalar();

            foreach ($blockTypeUids as $blockTypeUid) {
                $projectConfig->set('neoBlockTypes.' . $blockTypeUid . '.group', $prevGroupUid);
            }
        }

        // Now we can delete the group
        $this->stdout('Deleting the group...' . PHP_EOL);
        $projectConfig->remove('neoBlockTypeGroups.' . $group->uid);
        $this->stdout('Done.' . PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Edits a Neo block type group.
     *
     * @return int
     */
    public function actionEdit(): int
    {
        if (!$this->groupId) {
            $this->stderr('The --group-id option must be specified.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        if ($this->blankName && $this->setName) {
            $this->stderr('Only one of --blank-name or --set-name may be specified.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $dropdownOptions = [
            BlockTypeGroupDropdown::Show,
            BlockTypeGroupDropdown::Hide,
            BlockTypeGroupDropdown::Global,
        ];

        if ($this->dropdown && !in_array($this->dropdown, $dropdownOptions)) {
            $this->stderr('The --dropdown value must be one of ' . implode(', ', $dropdownOptions) . '.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $groupUid = Db::uidById('{{%neoblocktypegroups}}', $this->groupId);

        if ($groupUid === null) {
            $this->stderr('The block type group ID specified does not exist.' . PHP_EOL, Console::FG_RED);
            return ExitCode::USAGE;
        }

        $projectConfig = Craft::$app->getProjectConfig();
        $groupPath = 'neoBlockTypeGroups.' . $groupUid;
        $groupConfig = $projectConfig->get($groupPath);

        if ($this->setName || $this->blankName) {
            $groupConfig['name'] = $this->setName ?? '';
        }

        if ($this->dropdown) {
            $groupConfig['alwaysShowDropdown'] = match ($this->dropdown) {
                BlockTypeGroupDropdown::Show => true,
                BlockTypeGroupDropdown::Hide => false,
                BlockTypeGroupDropdown::Global => null,
            };
        }

        $projectConfig->set($groupPath, $groupConfig);
        $this->stdout('Done.' . PHP_EOL);

        return ExitCode::OK;
    }
}
