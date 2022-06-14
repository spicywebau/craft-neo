<?php

namespace benf\neo\console\controllers;

use benf\neo\enums\BlockTypeGroupDropdown;
use Craft;
use craft\console\Controller;
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

        if ($actionID === 'edit') {
            $options[] = 'groupId';
            $options[] = 'setName';
            $options[] = 'blankName';
            $options[] = 'dropdown';
        }

        return $options;
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

        $projectConfig = Craft::$app->getProjectConfig();
        $groupUid = Db::uidById('{{%neoblocktypegroups}}', $this->groupId);
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
