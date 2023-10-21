# Changelog

## Unreleased

### Fixed
- Fixed a bug with the spinner placement when lazy loading new blocks, when a new block was created with the add block above action
- Fixed a bug where conditionally hidden fields on new blocks would not display the first time conditions were met for it to display

## 3.9.6 - 2023-10-20

### Fixed
- Fixed a bug when the `enableLazyLoadingNewBlocks` plugin setting was disabled, where JavaScript was being initialised twice for each existing block
- Fixed a bug where published Neo blocks could be merged into the wrong place in a draft block structure in some cases
- Fixed an error that could occur when saving an entry containing a Neo field

## 3.9.5 - 2023-10-06

### Fixed
- Fixed a bug that occurred when using the child block UI element, where creating the parent block and then creating the child block before the draft had finished saving would cause blocks to be lost
- Fixed a bug when creating at least two block types at once, where parent-child relations set between the new block types would not be saved
- Fixed a bug when lazy loading new blocks, where relation element fields in new blocks would show elements from the default site, rather than the active site
- Fixed an error that could occur when lazy loading new blocks

## 3.9.4 - 2023-09-27

### Fixed
- Fixed a bug where nested fields that depend on external CSS or JavaScript files weren't loading correctly when lazy loading new blocks
- Fixed an error that could occur when upgrading from Craft 3 to Craft 4 (thanks @oliver-amann)

## 3.9.3 - 2023-09-27

### Added
- Added the `enableLazyLoadingNewBlocks` plugin setting (defaults to `true`; setting to `false` will revert to pre-3.9.0 behaviour)

### Fixed
- Fixed a bug where empty field layout tabs on Neo block types would be duplicated when saving the Neo field
- Fixed an error that occurred when creating a new block that contains a Vizy field

## 3.9.2 - 2023-09-23

### Fixed
- Fixed a bug where content of newly created Neo blocks would be lost on owner element save

## 3.9.1 - 2023-09-22

### Fixed
- Fixed a bug that occurred when pasting Neo input blocks that use the child block UI element and have descendants, where the descendants would disappear after updating visible field layout elements for the Neo field

## 3.9.0 - 2023-09-19

### Added
- Added Dutch translations (thanks @lavandongen)

### Changed
- Neo now requires Craft 4.5.0 or later
- Field layout tabs for new Neo input blocks of a certain block type are now lazy loaded when a block of that type is first created

### Fixed
- Fixed a bug where Neo block structure data would be lost when soft-deleting an entry
- Fixed an error that could occur when applying Neo block type project config changes
- Fixed a bug with the placement of block type handles on input blocks on Craft 4.5, for users who have their preferences set to show field handles in edit forms
- Fixed a bug when trying to save an entry before draft autosaving had completed following the creation of a Neo block, where any further changes to the Neo field would cause that block's contents to disappear
- Fixed an error that occurred when saving an entry that uses a Neo field, if one of that field's block types had been deleted, and any block type that used the deleted block type as a child block type had not been edited

## 3.8.6 - 2023-08-10

### Fixed
- Fixed an error that could occur during Craft garbage collection if the `neoblockstructures` table contained more than 65535 rows

## 3.8.5 - 2023-08-08

### Fixed
- Fixed a bug that caused content loss of new Neo blocks' subfields, if the new block was created between a provisional draft saving and dynamic Neo subfield conditions being applied

## 3.8.4 - 2023-08-03

### Fixed
- Fixed a bug where empty block type descriptions could be inconsistently set to either `''` or `null` (should now be consistently set to `''`)

## 3.8.3 - 2023-07-25

### Added
- Added `benf\neo\services\Blocks::getStructures()`

### Fixed
- Fixed timeouts caused by Neo that could occur when saving an entry, if Craft is running with a clustered/load-balanced DB service

## 3.8.2 - 2023-07-13

### Fixed
- Fixed a server error that occurred when using a slideout editor to edit an element with a Neo field
- Fixed a bug with applying dynamic Neo subfield conditions after a draft autosave, if the draft was resaved while Neo was checking which fields should be visible

## 3.8.1 - 2023-07-07

### Fixed
- Fixed visual bugs and loss of newly created block type and block type group data if validation errors occurred when saving a Neo field, and there had been more than one block type or block type group created

## 3.8.0 - 2023-06-24

### Added
- Added `benf\neo\Field::getItems()`
- Added `benf\neo\Field::setItems()`

### Changed
- When editing a Neo field's settings, block type and block type group settings are now lazy loaded

### Deprecated
- Deprecated `benf\neo\controllers\Configurator::actionRenderFieldLayout()`

### Fixed
- Fixed performance issues when loading a Neo field settings page, if the Neo field has a large number of block types
- Fixed an SQL error that could occur when upgrading to Craft 4, if any `neoblocks` table rows referenced nonexistent element IDs
- Fixed a bug where the copy and clone actions were still available on blocks for users who didn't have permission to create blocks of its type
- Fixed a bug where the add block above action was still available on blocks for users who didn't have permission to create any of the sibling block types

## 3.7.12 - 2023-06-16

### Fixed
- Fixed a compatibility issue with the Content Templates plugin and Neo 3.7.11

## 3.7.11 - 2023-06-16

### Fixed
- Fixed a bug when creating drafts on multi-site Craft projects with a Neo field's propagation method set to an option other than "Only save blocks in the site they were created in", where some sites could end up without a Neo block structure for the draft

## 3.7.10 - 2023-06-14

### Fixed
- Fixed a bug when validation errors occurred when editing an element that has more than one Neo field, where some blocks could have multiple add block buttons added
- Fixed a bug when merging live entry changes into a draft, where newly-created blocks could end up in the wrong places in the draft's block structure
- Fixed a bug where Neo fields would fail validation if they only contained automatically-added blocks (per the field's Min Blocks setting) and no changes were made to them
- Fixed a bug where a block type's min and max child blocks settings weren't being cleared if the block type was set to no longer have any valid child block types

## 3.7.9 - 2023-05-18

### Changed
- Neo will no longer create revision block structures using queue jobs if the Craft project's `runQueueAutomatically` general setting is disabled

### Fixed
- Fixed a bug where Neo blocks could get detached from entries when sections were enabled for a new site

## 3.7.8 - 2023-05-09

### Fixed
- Fixed a bug where pasting or cloning blocks whose types' 'Group Child Block Types' setting was disabled would cause the setting not to be respected on the pasted/cloned block

## 3.7.7 - 2023-05-01

### Fixed
- Fixed a bug where setting conditions on a child blocks UI element would cause the UI element not to display

## 3.7.6 - 2023-04-06

### Fixed
- Fixed a JavaScript error that occurred when entry saving failed, if draft autosaving was disabled and new Neo blocks were created containing Redactor fields
- Fixed a bug where Plain Text and Table fields were converting posted shortcode-looking strings to emoji, for Craft 4.5 and later (thanks @brandonkelly)

## 3.7.5 - 2023-03-28

### Fixed
- Fixed a bug when updating Neo blocks' visible field layout elements, where blocks' top bars could display the incorrect selected tabs

## 3.7.4 - 2023-03-25

### Added
- Added the `enableBlockTypeUserPermissions` plugin setting (defaults to `true`)

### Fixed
- Fixed an error that occurred when updating Neo blocks' visible field layout elements if the owner element was disabled

## 3.7.3 - 2023-03-18

### Fixed
- Fixed a bug when updating visible field layout elements after creating a new block, where disabled blocks in the Neo field weren't being accounted for, causing another block's content to appear in the new block

## 3.7.2 - 2023-03-17

### Fixed
- Fixed a bug where, when automatically creating child blocks for a newly created block with Min Child Blocks set, the child blocks would be created at the same level as the parent

## 3.7.1 - 2023-03-15

### Fixed
- Fixed a bug on multi-site Craft installs, where Neo could check for the wrong site's block data when updating visible field layout elements

## 3.7.0 - 2023-02-21

### Added
- Added the ability to set conditions on which global sets a Neo block type can be used for
- Added the Level option to the Neo block layout element condition rules
- Added options to the Neo block layout element condition rules for parent blocks' field values
- Added `benf\neo\conditions\fields\ParentFieldConditionRuleTrait`
- Added `benf\neo\conditions\fields\ParentDateFieldConditionRule`
- Added `benf\neo\conditions\fields\ParentLightswitchFieldConditionRule`
- Added `benf\neo\conditions\fields\ParentNumberFieldConditionRule`
- Added `benf\neo\conditions\fields\ParentOptionsFieldConditionRule`
- Added `benf\neo\conditions\fields\ParentRelationalFieldConditionRule`
- Added `benf\neo\conditions\fields\ParentTextFieldConditionRule`
- Added `benf\neo\controllers\Input::actionUpdateVisibleElements()`

### Changed
- When using an element editor page, Neo block subfields will now be shown or hidden based on their condition rules after the owner draft is saved
- When opening an element editor page, if a Neo field's Min Blocks setting hasn't been met, and it has only one block type available at the top level, the required blocks will now automatically be created
- When editing an element and creating a new Neo block, if the block type's Min Child Blocks setting is set, and it has only one child block type available, the required child blocks will now automatically be created (however, the child blocks themselves won't have any required child blocks automatically created, to prevent any potential infinite recursion)

### Fixed
- Fixed a bug where the default Neo block type icon wasn't displaying when editing elements that don't have drafts, e.g. global sets, Craft Commerce products
- Fixed a bug with the Add Block Above action, when using the new block menu grid style

## 3.6.4 - 2023-02-11

### Changed
- When Neo block queries have a single `ownerId`, `siteId` and either a single `fieldId` or `id` set, the appropriate `structureId` will now also be set on the query, if it exists and wasn't already set on the query

### Fixed
- Fixed an error that occurred when viewing revisions, when using new block menu styles that show icons

## 3.6.3 - 2023-02-08

### Changed
- When running Craft garbage collection, Neo will now delete any orphaned Neo block structure data

### Fixed
- Fixed an error that occurred when updating to Neo 3.6.2 if no Neo fields existed
- Fixed a bug where Neo block structures belonging to provisional drafts weren't being deleted when saving the entry

## 3.6.2 - 2023-02-02

### Fixed
- Fixed a bug where all block types in new block menus on unsaved blocks were showing the default icons, when using new block menu styles that show icons
- Fixed a bug with the format of block type icon data stored in the project config

## 3.6.1 - 2023-01-31

### Fixed
- Fixed an error that occurred when applying a project config from a Craft install running a Neo version prior to 3.6.0

## 3.6.0 - 2023-01-27

### Added
- Added the `newBlockMenuStyle` plugin setting
- Added the `blockTypeIconSources` plugin setting
- Added a plugin settings page
- Added French translations (thanks @scandella)
- Added `benf\neo\controllers\Configurator::actionRenderBlockType()`
- Added `benf\neo\models\BlockType::$iconId`
- Added `benf\neo\services\BlockTypes::EVENT_SET_CONDITION_ELEMENT_TYPES`
- Added `benf\neo\services\BlockTypes::renderBlockTypeSettings()`

### Deprecated
- Deprecated `benf\neo\assets\SettingsAsset::EVENT_SET_CONDITION_ELEMENT_TYPES`; use `benf\neo\services\BlockTypes::EVENT_SET_CONDITION_ELEMENT_TYPES` instead

### Fixed
- Fixed a bug where, when pasting or cloning a block type, the block type conditions weren't being copied

## 3.5.16 - 2023-01-04

### Fixed
- Fixed a bug where users who were disallowed from deleting Neo blocks of a certain block type still didn't have the delete option on existing blocks if the block type's Ignore Permissions setting was enabled

## 3.5.15 - 2022-12-28

### Fixed
- Fixed a bug where new Neo blocks' collapsed states were being lost when saving
- Fixed a console error that could occur after copying a Neo block if any of the Neo field's block types were being filtered out

## 3.5.14 - 2022-12-21

### Fixed
- Fixed a bug where revision Neo blocks could be saved at the wrong level

## 3.5.13 - 2022-12-14

### Fixed
- Fixed a bug where a Neo block type group without a name could have its name set to `null` in the project config, instead of an empty string
- Fixed an error that occurred when applying a project config if a Neo block type group without a name had its name set to `null` in the project config
- Fixed an error that occurred when using Field Manager to import Neo field data that was exported by the Craft 3 version of Field Manager

## 3.5.12 - 2022-12-09

### Fixed
- Fixed block structure issues

## 3.5.11 - 2022-12-09

### Fixed
- Fixed an error that occurred when merging canonical Neo block changes into their derivative blocks
- Fixed a bug where disabled nested Neo blocks, and potentially blocks following them in the block structure, could incorrectly be saved at the top level
- Fixed a bug when using eager loading (including using GraphQL), where querying a Neo block's children would always return no results
- Fixed a bug where a Neo block type's `conditions` property was not being set in the project config if there were no conditions on the block type
- Fixed style issues with Neo block type handles on Neo input blocks when using Safari

## 3.5.10 - 2022-12-06

### Fixed
- Fixed a bug in Neo 3.5.9 where Neo block structures weren't being saved for entry revisions

## 3.5.9 - 2022-12-05

### Fixed
- Fixed an error that could occur when saving entries using Neo 3.5.8

## 3.5.8 - 2022-12-04

### Fixed
- Fixed a bug where queries for Neo blocks with a specific owner entry/category could return duplicate blocks, if the entry/category had any drafts and the duplicated blocks were owned by both the entry/category and the drafts
- Fixed the position of field status indicators within Neo fields in Live Preview

## 3.5.7 - 2022-12-02

### Fixed
- Fixed a bug where Neo blocks could be scrambled or the "Can not move a node when the target node is same" error could occur when creating a draft

## 3.5.6 - 2022-11-30

### Fixed
- Fixed a bug where rebuilding the project config would result in Neo block type config YAML files being removed

## 3.5.5 - 2022-11-29

### Fixed
- Fixed a console error that occurred in Neo 3.5.4 if there was no Neo copied block data in the browser's local storage

## 3.5.4 - 2022-11-28

### Fixed
- Fixed a bug where Neo child blocks could be cloned to exceed the parent block's max child blocks setting

## 3.5.3 - 2022-11-27

### Fixed
- Fixed an error that occurred when executing a GraphQL query, if a field previously of the Neo type had been changed to a different type

## 3.5.2 - 2022-11-11

> {note} The migration that runs in this update saves the value of `ignorePermissions` for each block type based on which block types already have any user permissions set - if any have been set, then it will be saved as `false`, otherwise it will be saved as `true`.

### Added
- Added `benf\neo\models\BlockType::$ignorePermissions` - Adds the ability to set a block type to ignore any user permissions set for it (in the Advanced section of the block type settings)

## 3.5.1 - 2022-11-10

### Fixed
- Fixed an error that occurred if Feed Me wasn't installed

## 3.5.0 - 2022-11-10

### Added
- Added support for the Feed Me plugin
- Added the ability to set user permissions for the creation, deletion and editing of blocks of each block type
- Added the ability to set whether the child block types of a block type will be shown in their groups (if any)
- Added `benf\neo\console\controllers\BlockTypesController::$setGroupChildBlockTypes`
- Added `benf\neo\console\controllers\BlockTypesController::$unsetGroupChildBlockTypes`
- Added `benf\neo\Field::getBlockTypeFields()`
- Added `benf\neo\integrations\feedme\Field`
- Added `benf\neo\models\BlockType::$groupChildBlockTypes`
- Added `benf\neo\services\Fields::getNeoFields()`

### Changed
- Updated JavaScript dependencies

### Fixed
- Fixed a bug where opening a Neo block's actions menu would not close any already open actions menu belonging to another block in the same Neo field
- Fixed a bug that occurred when using the `collapseAllBlocks` plugin setting, where existing blocks could not be expanded

## 3.4.1 - 2022-11-08

### Fixed
- Fixed a bug where cloning or copying Neo blocks using the child blocks UI element could cause the parent blocks to be pasted/cloned with some of the child block's content

## 3.4.0 - 2022-09-29

### Added
- Neo block subfields can now have condition rules applied based on the owner element's section, entry type, asset volume, category group, user group or tag group
- Added `benf\neo\elements\conditions\BlockCondition`
- Added `benf\neo\elements\conditions\OwnerCategoryGroupConditionRule`
- Added `benf\neo\elements\conditions\OwnerConditionRuleTrait`
- Added `benf\neo\elements\conditions\OwnerEntryTypeConditionRule`
- Added `benf\neo\elements\conditions\OwnerSectionConditionRule`
- Added `benf\neo\elements\conditions\OwnerTagGroupConditionRule`
- Added `benf\neo\elements\conditions\OwnerUserGroupConditionRule`
- Added `benf\neo\elements\conditions\OwnerVolumeConditionRule`

### Changed
- Copied Neo input block data is now stored on a per-field basis

### Fixed
- Fixed a bug where Neo block tabs weren't showing indicators of validation errors in descendant blocks, if the descendant blocks were hidden in child blocks UI element(s)
- Fixed a bug where the block type names on existing Neo input blocks weren't being translated
- Fixed a bug where the tab names on all Neo input blocks weren't being translated
- Fixed a bug where eager loaded Neo fields with disabled parent blocks would have their enabled child blocks counted as children of the parent's previous sibling block

## 3.3.9 - 2022-09-16

### Added
- Collapsed block previews can now display TinyMCE Field content

### Fixed
- Fixed an error that could occur when upgrading to Craft 4, if any Neo blocks contained null `sortOrder` values
- Fixed an 'Attempt to read property "typeId" on bool' error that could occur during Neo field validation when saving an entry
- Fixed a bug with Min Child Blocks validation when saving an element that doesn't autosave drafts
- Fixed a bug where Neo block tabs weren't showing indicators of validation errors in subfields

## 3.3.8 - 2022-09-14

### Fixed
- Fixed a gateway timeout that could occur when validation errors occurred with unsaved Neo parent and child blocks
- Fixed an error that occurred when upgrading from Craft 3 to Craft 4 if any Neo fields had been converted to Matrix fields
- Fixed a JavaScript error that occurred if any existing Neo blocks with filtered-out block types had child blocks with allowed block types

## 3.3.7 - 2022-09-08

### Fixed
- Fixed an 'Undefined array key' error that occurred when saving an element, if any Neo blocks were in formerly valid places in the field for them to exist
- Fixed a bug where Neo fields could be created without any block types at the top level
- Fixed a bug when editing a Neo field's settings, where setting a block type to be allowed or disallowed at the top level was not applying the new state to the block type sidebar

## 3.3.6 - 2022-09-01

### Fixed
- Fixed a bug with Min Sibling Blocks of This Type validation
- Fixed a bug with GraphQL, where transform directives weren't working on asset field on child blocks

## 3.3.5 - 2022-08-23

### Fixed
- Fixed a bug where pasted or cloned blocks would appear to be disabled
- Fixed a bug where newly created disabled blocks would not have their disabled state saved

## 3.3.4 - 2022-08-22

### Changed
- Renamed the `Input._blockSelect` Neo JavaScript property to `Input.blockSelect` (denoting it as public)

### Fixed
- Fixed a JavaScript error that occurred when trying to enable or disable multiple selected blocks at once
- Fixed a bug where validation errors when creating a new entry could cause Neo blocks' subfield content to be lost

## 3.3.3 - 2022-08-18

### Changed
- Improved the appearance of titles of Neo input blocks with validation errors

### Fixed
- Fixed a bug where trying to delete all Neo blocks from a field would cause them not to be deleted
- Fixed a JavaScript error that could occur with a Neo -> Matrix -> Redactor setup if there were validation errors

## 3.3.2 - 2022-08-17

### Fixed
- Fixed an 'Undefined array key' error that occurred in some cases when saving an element

## 3.3.1 - 2022-08-16

### Fixed
- Fixed an error that occurred when applying a project config from a Craft install running a Neo version prior to 3.3.0

## 3.3.0 - 2022-08-16

### Added
- Added the Min Levels field setting (added `benf\neo\Field::$minLevels`)
- Added the Min Top-Level Blocks field setting (added `benf\neo\Field::$minTopBlocks`)
- Added the Enabled block type setting (added `benf\neo\models\BlockType::$enabled` and added the `enabled` column to the `neoblocktypes` table)
- Added the Min Blocks block type setting (added `benf\neo\models\BlockType::$minBlocks` and added the `minBlocks` column to the `neoblocktypes` table)
- Added the Min Sibling Blocks of This Type block type setting (added `benf\neo\models\BlockType::$minSiblingBlocks` and added the `minSiblingBlocks` column to the `neoblocktypes` table)
- Added the Min Child Blocks block type setting (added `benf\neo\models\BlockType::$minChildBlocks` and added the `minChildBlocks` column to the `neoblocktypes` table)
- Added `benf\neo\console\controllers\BlockTypesController::$setEnabled` (added the `--set-enabled` option to the `php craft neo/block-types/edit` console command)
- Added `benf\neo\console\controllers\BlockTypesController::$unsetEnabled` (added the `--unset-enabled` option to the `php craft neo/block-types/edit` console command)
- Added `benf\neo\console\controllers\BlockTypesController::$setMinBlocks` (added the `--set-min-blocks` option to the `php craft neo/block-types/edit` console command)
- Added `benf\neo\console\controllers\BlockTypesController::$setMinSiblingBlocks` (added the `--set-min-sibling-blocks` option to the `php craft neo/block-types/edit` console command)
- Added `benf\neo\console\controllers\BlockTypesController::$setMinChildBlocks` (added the `--set-min-child-blocks` option to the `php craft neo/block-types/edit` console command)

### Changed
- Restored the ability to show Linkit field content in collapsed block previews

### Fixed
- Fixed a bug when saving an element, where Neo fields weren't being validated on block types' Max Blocks settings, unless at least one of the Neo field's Max Top-Level Blocks and Max Levels settings were set
- Fixed a bug where copying and pasting a block type would not copy the old block type's description to the new block type
- Fixed a bug where collapsed block previews were still displaying after expanding a block
- Fixed a bug where collapsed block previews were not displaying the content of multi-line plain text fields
- Fixed a bug on multi-site Craft installs, where a provisional draft being created with an unedited Neo field could cause the Neo field's block structure not to be created for the provisional draft, if the Neo field's propagation method was set to "Only save blocks to the site they were created in"
- Fixed a bug where disabled Neo input blocks could appear to be enabled, and selecting the disable action on these blocks would have no effect
- Fixed a bug where disabled Neo input blocks could sometimes not appear, if there were validation errors when saving an element

## 3.2.5 - 2022-08-03

### Fixed
- Fixed a bug where calling `ids()` on a memoized Neo block query would always return an empty array
- Fixed a bug that occurred when saving an element that doesn't autosave drafts, where validation errors would cause new Neo blocks that had any child blocks to incorrectly detect themselves as their own child block
- Fixed a bug that occurred when saving an element that doesn't autosave drafts, where changes to a Neo block's enabled/disabled state wouldn't be saved

## 3.2.4 - 2022-08-01

### Added
- Added `benf\neo\console\controllers\FieldsController::$withPropagationMethod` (added the `--with-propagation-method` to the `php craft neo/fields/reapply-propagation-method` console command)

### Fixed
- Fixed potential performance issues when executing Neo block queries with memoized blocks if the `status` property was set on the query
- Fixed a bug when reverting to an entry revision, where Neo revision blocks that had deleted canonical blocks weren't being reverted
- Fixed a bug where Neo fields were assuming their values hadn't been eager-loaded on element save

## 3.2.3 - 2022-07-29

### Added
- Element conditions can now include condition rules for Neo fields on Craft 4.2.0 or later

### Changed
- Updated JavaScript dependencies

### Fixed
- Fixed a bug where saving an element with a Neo field that only contained disabled blocks would cause the blocks to be deleted
- Fixed a bug where `benf\neo\elements\db\BlockQuery::exists()` could incorrectly return `false` if the query wasn't memoized and had no cached result
- Fixed a bug where cloned Neo blocks using the child blocks UI element wouldn't have child block buttons
- Fixed a bug on multi-site Craft installs, where Neo revision blocks weren't being saved for entry revisions if the canonical blocks weren't saved for the site that was active when the entry was saved
- Fixed a bug where setting a Neo block as disabled on a provisional draft would cause the disabled state to be lost
- Fixed a bug where reordering a Neo field's blocks could sometimes cause them to move out of order after saving

## 3.2.2 - 2022-07-26

### Fixed
- Fixed an 'Attempting to duplicate an element in an unsupported site' error that occurred when disabling a section for a site, if any Neo blocks belonging to any of the section's entries were only saved for the disabled site

## 3.2.1 - 2022-07-25

### Fixed
- Fixed an error that could occur when upgrading to Neo 3.2.0 if using MySQL

## 3.2.0 - 2022-07-24

### Added
- Added the `php craft neo/block-types/delete` console command
- Added the `php craft neo/block-types/edit` console command
- Added the ability to set conditions on the owner elements a Neo block type can be used for, through condition fields for Craft CMS and Craft Commerce element types on the Neo block type settings
- Added `benf\neo\assets\SettingsAsset::EVENT_SET_CONDITION_ELEMENT_TYPES` (allowing other plugins to register element types for the Neo block type condition fields)
- Added `benf\neo\console\controllers\BlockTypesController`
- Added `benf\neo\events\SetConditionElementTypesEvent`
- Added `benf\neo\models\BlockType::$conditions` and added the `conditions` column to the `neoblocktypes` table

### Fixed
- Fixed a JavaScript error that occurred when pasting a block type
- Fixed an incorrect German translation of 'Min Blocks' (thanks @alumpe)
- Fixed a bug where the child blocks UI element wasn't appearing on the field layout designer sidebar on existing block types
- Fixed a JavaScript error that occurred when saving an element that doesn't autosave drafts, if a validation error occurred and a new block contained a Redactor field

## 3.1.8 - 2022-07-15

### Fixed
- Fixed a bug where block type field layout changes would be lost if the Neo field failed to save due to a validation error
- Fixed a bug where blocks that had reached their block type's max child blocks setting were having their add block above, paste, and clone actions disabled, regardless of whether or not they should have been disabled

## 3.1.7 - 2022-07-08

### Fixed
- Fixed a bug when editing an element via the slideout editor, where new Neo blocks weren't being saved when saving the element

## 3.1.6 - 2022-07-06

### Fixed
- Fixed a bug when editing a new entry with a Neo field with the propagation method 'Only save blocks to the site they were created in', where new Neo blocks weren't getting duplicated for the other sites
- Fixed a bug where pasting a Neo block would paste it as a child block of the block where the paste action was selected, instead of after that block at the same level, if that block was allowed to have child blocks
- Fixed a bug when saving a provisional draft on a multi-site Craft install, where Neo blocks could be saved at the wrong level, as a result of Neo finding the wrong structure ID for the original entry

## 3.1.5 - 2022-06-28

### Fixed
- Fixed a bug where unedited Neo blocks were being duplicated in preview mode, when editing a provisional draft, if the blocks were eager loaded

## 3.1.4 - 2022-06-27

### Fixed
- Fixed an error that could occur when saving a Neo field value if PostgreSQL is being used

## 3.1.3 - 2022-06-27

### Fixed
- Fixed a bug in Neo 3.1.2 where saving an entry would fail if the database table names used a table prefix

## 3.1.2 - 2022-06-24

### Fixed
- Fixed a JavaScript error that occurred when filtering out a Neo block type in a case where the block type was already in use; now, Neo blocks with filtered-out types won't be rendered, and will be deleted if structural changes are made to the Neo field
- Fixed a JavaScript error that occurred as a result of cloning a block that uses the child blocks UI element
- Fixed a bug where disabled blocks weren't displaying on entry revisions
- Fixed a bug where block structures weren't being resaved when saving a provisional draft

## 3.1.1 - 2022-06-19

### Fixed
- Fixed a bug where collapsed Neo blocks had the 'Collapse' action display in the actions menu instead of 'Expand' after saving them
- Fixed an error that occurred when updating from a Neo version prior to 3.0.4, if a Neo-to-Matrix conversion had been performed in the past
- Fixed a bug where Neo fields with enough block types / groups that the buttons wouldn't fit within an element editor page container, on non-active tabs when the page loaded, would still show the buttons (overflowing the editor container) instead of a dropdown
- Fixed an error that occurred when using the `php craft neo/block-type-groups/delete` console command if the block type group ID specified didn't exist
- Fixed a bug where changes to existing Neo blocks weren't saving for element types that supported drafts but not change tracking
- Fixed a bug where unedited Neo blocks weren't being added to their field's block structure for provisional drafts
- Fixed a bug where `primaryOwnerId` was being set in the eager loading map criteria instead of `ownerId`, causing unedited Neo blocks not to appear in preview mode if the Neo field was eager loaded

## 3.1.0 - 2022-06-14

### Added
- Added the `php craft neo/block-type-groups/delete` console command
- Added the `php craft neo/block-type-groups/edit` console command
- Added `benf\neo\console\controllers\BlockTypeGroupsController`
- Added `benf\neo\enums\BlockTypeGroupDropdown`
- Added `benf\neo\services\BlockTypes::renderFieldLayoutDesigner()`

### Changed
- Neo block type settings are now rendered in PHP/Twig instead of JavaScript
- When editing a Neo field's settings, the existing block types' field layout designers are now lazy loaded
- Neo input blocks now show the block type's handle next to the name on desktops/tablets, for users who have their 'Show field handles in edit forms' user setting enabled
- `benf\neo\elements\Block::useMemoized()` and `benf\neo\elements\db\BlockQuery::useMemoized()` now support being given an `Illuminate\Support\Collection` object

### Fixed
- Fixed a bug where block type groups with blank names would appear in the block type settings sidebar with the name `NULL` when first loading a Neo field settings page

## 3.0.7 - 2022-06-13

### Fixed
- Fixed an error that occurred when passing an array of handles to a Neo block query's `type()` method

## 3.0.6 - 2022-06-02

### Fixed
- Fixed a bug where block type groups that only contained non-top-level block types were displaying as a disabled dropdown at the top level on Neo input fields

## 3.0.5 - 2022-05-24

### Added
- Added `benf\neo\models\BlockType::$description` (thanks @leevigraham)

### Changed
- Neo input blocks now have a `data-neo-b-name` attribute (thanks @davidhellmann)
- Block type descriptions can appear on hover over new block buttons

### Fixed
- Fixed a bug where copied top level Neo blocks could be pasted into places in the Neo field where that block type shouldn't have been allowed
- Fixed a bug where creating a Quick Post widget with a visible Neo field would cause an error

## 3.0.4 - 2022-05-13

### Fixed
- Fixed a bug where Neo blocks at the top level that were disabled weren't having their disabled state saved
- Fixed a bug where Neo blocks not at the top level that were disabled weren't being displayed on element editor pages
- Fixed a bug where, when converting a Neo field to Matrix, the old Neo block types and block type groups weren't getting deleted from the database and project config
- Fixed a bug where it was possible to exceed a Neo block type's Max Blocks and Max Sibling Blocks of This Type settings, by pasting blocks that were copied from a different browser tab
- Fixed a bug where running a migration that creates a new entry with Neo content would cause an error

## 3.0.3 - 2022-05-10

### Fixed
- Fixed an error that could occur on Craft installs upgraded from Craft 3 / Neo 2 when applying project config changes

## 3.0.2 - 2022-05-08

### Changed
- The Neo block type settings sidebar now displays block type handles under the block type names

### Fixed
- Fixed a bug where top-level Neo blocks' Paste, Clone and Add Block Above actions weren't being disabled when the field had reached its Max Top-Level Blocks setting
- Fixed a bug where, when selecting a block type tab that has a space in its name, the tab's contents wouldn't display
- Fixed a bug where selecting block type tabs on mobile devices wasn't working
- Fixed a bug where errors that occurred when rendering Neo input HTML would cause Neo input fields to display the "Unable to nest Neo fields" error, instead of displaying the actual error that occurred

## 3.0.1 - 2022-05-05

### Fixed
- Fixed an error that occurred when querying Neo block contents using GraphQL

## 3.0.0 - 2022-05-04

### Added
- Added Craft 4 compatibility
- Added the ability to show or hide block type group dropdowns where the group only has one available block type
- Added the `defaultAlwaysShowGroupDropdowns` plugin setting (defaults to `true`)
- Added the `alwaysShowDropdown` column to the `neoblocktypegroups` table
- Added `benf\neo\models\BlockTypeGroup::$alwaysShowDropdown`
- Added `benf\neo\assets\InputAsset`
- Added `benf\neo\assets\SettingsAsset`
- Added `benf\neo\controllers\Configurator`
- Added `benf\neo\jobs\SaveBlockStructures`

### Changed
- Existing Neo input field content is no longer rendered using JavaScript (other than new block buttons)
- Whether a Neo block type's max blocks setting has been exceeded is now validated server-side when saving a Neo field's contents, rather than relying on it to be enforced by client-side JavaScript
- The `neoblockstructures` table's `ownerSiteId` column has been renamed to `siteId`, and the `benf\neo\models\BlockStructure` class's `$ownerSiteId` property has been renamed to `$siteId`
- The amount of animations and transitions Neo uses is now reduced if the user has requested reduced motion
- When editing a Neo field's block types, creating a new block type will show the settings tab, if the field layout tab was previously showing
- Visual improvements to the Neo block type settings
- Updated JavaScript dependencies

### Deprecated
- Deprecated `benf\neo\assets\FieldAsset`; users of `EVENT_FILTER_BLOCK_TYPES` should use `InputAsset` instead
- Deprecated `benf\neo\tasks\DuplicateNeoStructureTask`; use `benf\neo\jobs\SaveBlockStructures` instead

### Removed
- Removed Craft 3 compatibility
- Removed the unused `saveModifiedBlocksOnly` setting
- Removed the unused `ownerSiteId` column from the `neoblocks` table
- Removed `benf\neo\Field::$localizeBlocks`; use `$propagationMethod` instead
- Removed `benf\neo\Field::$wasModified`
- Removed `benf\neo\Plugin::$blockHasSortOrder`
- Removed `benf\neo\integrations\fieldlabels\FieldLabels`
- Removed `benf\neo\converters\Field` (Neo Field Converter for Schematic)
- Removed `benf\neo\converters\models\BlockType` (Neo BlockType Converter for Schematic)
- Removed `benf\neo\converters\models\BlockTypeGroup` (Neo BlockTypeGroup Converter for Schematic)
- Removed `benf\neo\elements\Block::getModified()`; use `$dirty` instead
- Removed `benf\neo\elements\Block::setModified()`; use `$dirty` instead
- Removed `benf\neo\elements\Block::$ownerSiteId`; use `$siteId` instead
- Removed `benf\neo\elements\db\BlockQuery::ownerLocale()`; use `site()` or `siteId()` instead
- Removed `benf\neo\elements\db\BlockQuery::ownerSite()`; use `site()` or `siteId()` instead
- Removed `benf\neo\elements\db\BlockQuery::ownerSiteId()`; use `site()` or `siteId()` instead
- Removed `benf\neo\elements\db\BlockQuery::$ownerSiteId`; use `$siteId` instead
- Removed `benf\neo\services\Blocks::getSearchKeywords()`
- Removed `benf\neo\services\BlockTypes::renderTabs()`
- Removed `benf\neo\services\Fields::getSupportedSiteIdsForField()`

### Fixed
- Fixed a bug where it was possible to create a Neo field with no block types

## 2.13.19 - 2023-06-15

### Fixed
- Fixed an integrity constraint violation that could occur when saving an existing entry that has a Neo field

## 2.13.18 - 2023-05-18

### Changed
- Neo will no longer create revision block structures using queue jobs if the Craft project's `runQueueAutomatically` general setting is disabled

## 2.13.17 - 2023-05-02

### Changed
- When running Craft garbage collection, Neo will now delete any orphaned Neo block structure data

### Fixed
- Fixed a bug where Neo block structures belonging to provisional drafts weren't being deleted when saving the entry

## 2.13.16 - 2023-01-12

### Added
- Added `benf\neo\jobs\DeleteBlock`

### Fixed
- Fixed performance issues when applying entry drafts (deletion of the old draft Neo blocks is now performed by queue jobs)

## 2.13.15 - 2022-07-19

### Fixed
- Fixed a JavaScript error that occurred when creating a new Neo block type, if Quick Field was used to delete a field group that wasn't created using Quick Field

## 2.13.14 - 2022-07-18

### Fixed
- Fixed an error that could occur when merging changes to canonical Neo blocks into derivative blocks, due to Neo incorrectly trying to merge changes into derivative blocks that didn't belong to a block structure

## 2.13.13 - 2022-07-12

### Fixed
- Fixed a bug where Neo block type names could be overwritten by translations on a Neo field settings page

## 2.13.12 - 2022-06-29

### Added
- Added support for the Craft 3 version of the Quick Field plugin

### Fixed
- Fixed a bug where Codeception tests of Neo content were failing if the `dbSetup` option's `clean` and `setupCraft` were set to true, due to Neo not finding its block types
- Fixed a bug where eager loaded block children queries could return a non-0 indexed array

## 2.13.11 - 2022-06-04

### Fixed
- Fixed an error caused by Neo that occurred when upgrading from Craft 2 to Craft 3

## 2.13.10 - 2022-05-20

### Fixed
- Fixed a JavaScript error that occurred when collapsing a Neo block on a global set, which caused the collapsed state of the block not to be saved

## 2.13.9 - 2022-05-08

### Fixed
- Fixed a bug where filtering memoized block query results by criteria was incorrectly not returning results in some cases
- Fixed a bug where filtering memoized block query results by block type ID(s) would cause an error if an integer was passed instead of an array

## 2.13.8 - 2022-05-04

### Fixed
- Fixed some performance issues when saving Neo blocks

## 2.13.7 - 2022-05-03

### Fixed
- Fixed an error that could occur when saving Neo blocks, when checking if a block has a searchable block type

## 2.13.6 - 2022-04-30

### Fixed
- Fixed a JavaScript error that occurred on Neo field settings pages, when changing a block type's 'Max Sibling Blocks of This Type' value

## 2.13.5 - 2022-04-16

### Added
- Added the `php craft resave/neo-blocks` console command
- Added `benf\neo\elements\db\BlockQuery::field()`

## 2.13.4 - 2022-04-13

### Fixed
- Fixed an error that occurred on a Neo field settings page when copying and pasting a block type that was set to use all of the field's block types as child block types
- Fixed style issues with UI elements on Neo blocks

## 2.13.3 - 2022-03-24

### Changed
- Changed the migration from Neo 2.13.0 that adds the `groupId` column to the `neoblocktypes` table, to set each block type's `group` in the project config, instead of setting the `groupId` on the block type model and resaving the block type
- When applying project config changes and when applying the `groupId` migration, if any block types still have blank tabs from before Neo 2.8 / Craft 3.5, they will now be kept (but resaving them through the control panel will still cause the blank tabs to be lost)

## 2.13.2 - 2022-03-21

### Fixed
- Fixed a bug that could cause Neo project config data to not be applied when applying a project config to a different environment

## 2.13.1 - 2022-03-17

### Fixed
- Fixed an error that occurred when applying a project config from prior to Neo 2.13.0

## 2.13.0 - 2022-03-16

> {warning} If you're updating from a Neo version prior to 2.8.16, you're running a multi-site Craft install, and you've ever changed your Neo fields' propagation methods, run the `php craft neo/fields/reapply-propagation-method` command after updating to potentially fix a bug from prior to Neo 2.8.14 where changes to a field's propagation method weren't being applied to their blocks. If that command causes an error, run `php craft neo/fields/reapply-propagation-method --by-block-structure` instead (be aware that this may create many jobs in the queue).

### Added
- Added the `groupId` column to the `neoblocktypes` table
- Added the `getGroup()` method to the `BlockType` model
- Added the `getGroupById()` method to the `BlockTypes` service
- Added the `FilterBlockTypesEvent`, which allows filtering which block types or block type groups display on a Neo field depending on the entry or other element being edited (thanks @myleshyson)
- Added the `php craft neo/fields/reapply-propagation-method` command with options `--field-id=<your comma-separated Neo field IDs here>` and `--by-block-structure`

### Changed
- Code from a migration added in Neo 2.8.14 and updated in 2.8.16 to reapply Neo fields' propagation methods to their blocks has been moved to the `php craft neo/fields/reapply-propagation-method` command

## 2.12.5 - 2022-02-16

### Fixed
- Fixed a bug that prevented Matrix block menus from working properly within Neo fields (thanks @brandonkelly)

## 2.12.4 - 2022-02-01

### Fixed
- Fixed a bug affecting multi-site Craft installs, where applying changes to two sites from two different drafts would cause Neo blocks to be duplicated

## 2.12.3 - 2022-01-25

### Fixed
- Fixed a bug where restoring a trashed entry was not properly restoring the Neo blocks/structures for that entry on multisite Craft installs
- Fixed a bug that occurred when using `craft.neo.blocks()`, where trying to set the Neo block query's `owner` parameter to an owner element using a criteria array would cause the `owner` parameter to be ignored

## 2.12.2 - 2022-01-12

### Fixed
- Fixed an error that could occur when merging live entry Neo content into a draft if a live entry Neo parent block had been deleted in the draft

## 2.12.1 - 2021-12-16

### Fixed
- Fixed a bug that could cause Neo blocks to be saved out of order
- Fixed a deprecation warning that could occur when eager loading Neo fields on Craft installs that have a custom field with the handle `order`

## 2.12.0 - 2021-12-13

### Added
- Added the Custom propagation method

### Fixed
- Fixed a bug affecting element types using the old Live Preview mode (e.g. categories) where unsaved Neo blocks at the top level would be counted as children of the previous top-level block
- Fixed a bug where Neo block queries that were filtering by block ID weren't working in the old Live Preview mode if they were using an array of IDs
- Fixed a bug that could cause the `m201208_110049_delete_blocks_without_sort_order` migration to fail (thanks @naboo)

## 2.11.21 - 2021-12-06

### Fixed
- Fixed an error that occurred on Craft 3.7.24 when editing a Neo field's settings

## 2.11.20 - 2021-12-06

### Fixed
- Fixed a bug involving incorrect merging of Neo block content from drafts and revisions in some cases

## 2.11.19 - 2021-11-22

### Fixed
- Fixed a bug where GraphQL queries for asset fields in Neo blocks would return no results if the block type handle contained an underscore

## 2.11.18 - 2021-11-17

### Fixed
- Fixed a bug where GraphQL queries wouldn't return Neo child blocks in some cases
- Fixed a bug where an entry's provisional draft would be created or updated if a Neo block was expanded or collapsed
- Fixed a bug where expanding or collapsing a Neo block on a provisional draft wouldn't also set the expanded/collapsed state on the canonical block

## 2.11.17 - 2021-11-11

### Fixed
- Fixed a bug when adding new blocks to a Neo field using GraphQL mutations, where queries for the returned data to include child blocks would not return the child blocks
- Fixed an error that occurred when cloning or pasting a Neo block that uses the child blocks UI element

## 2.11.16 - 2021-11-08

### Fixed
- Fixed a bug where new entry blocks (e.g. from a newly-applied draft) could be merged into the incorrect position of an already existing provisional draft
- Fixed an error that could occur on a multi-site Craft install when saving a new entry from changes to an existing entry, if the entry had a Neo field with a propagation method other than "Only save blocks to the site they were created in" and the entry's section was disabled for any site

## 2.11.15 - 2021-10-22

### Fixed
- Fixed an issue where getting a Neo block's children in a GraphQL query could cause a deprecation warning to be logged if the block had no children

## 2.11.14 - 2021-10-06

### Fixed
- Fixed a bug when reverting an entry to a previous revision, where Neo blocks that had since been soft-deleted couldn't be restored
- Fixed a CSS issue with Neo fields in element editor slideouts, where unselected Neo block tab content would still be displayed along with the selected tab's content

## 2.11.13 - 2021-09-15

### Changed
- In GraphQL queries, Neo blocks now have a `level` field

### Fixed
- Fixed a bug where disabled Neo blocks could disappear if validation errors occurred elsewhere in the Neo field
- Fixed a bug where, when saving a Neo field, Neo block types with no child block types could have their `childBlocks` value stored as an empty string in the project config, rather than `null`
- Fixed a bug with GraphQL mutations, where mutating an existing Neo block without specifying a level argument would cause it to be moved to level 1 if it was at a different level

## 2.11.12 - 2021-09-09

### Fixed
- Fixed a potential error when saving an entry

## 2.11.11.1 - 2021-09-09

### Fixed
- Fixed an error that could occur with the Gatsby Helper and Gatsby Source plugins

## 2.11.10 - 2021-09-09

### Changed
- Neo now requires Craft 3.7.12 or later
- Improved the performance of applying drafts with Neo content, in combination with draft performance improvements in Craft 3.7.12

### Fixed
- Fixed a bug that could cause disabled Neo blocks to be validated when saving

## 2.11.9 - 2021-08-30

### Fixed
- Fixed a bug where Neo block queries could cause errors in preview mode, if the preview request token had no `draftId` or `revisionId` properties set

## 2.11.8 - 2021-08-26

### Fixed
- Fixed a bug affecting element types using the old Craft Live Preview system (e.g. categories) where querying for the children of unsaved blocks would return no results

## 2.11.7 - 2021-08-20

### Added
- Collapsed block previews can now display CodeMirror field content
- Collapsed block previews can now display oEmbed field input content

### Changed
- Changed the "Move Up" and "Move Down" Neo block action button text to "Move up" and "Move down"

### Fixed
- Fixed a bug where the "Move up" and "Move down" Neo block action button text couldn't be translated

## 2.11.6.1 - 2021-08-12

### Fixed
- Fixed an error with the Neo 2.11.6 migration on PHP 7.x

## 2.11.6 - 2021-08-12

> {warning} This release includes a migration to delete old Neo block structure data on single-site Craft installs that could cause duplicate blocks to appear when eager loading or using GraphQL. If your Craft install is single-site, make sure to backup your database before updating Neo.

### Fixed
- Fixed an issue affecting single-site Craft installs, where two Neo block structures could exist for a given field/owner, causing duplicate blocks to appear when eager loading or using GraphQL

## 2.11.5 - 2021-08-04

### Fixed
- Fixed a compatibility issue with Field Manager (thanks @engram-design)

## 2.11.4 - 2021-07-29

### Fixed
- Fixed a bug where changing the sort order of Neo blocks without otherwise modifying the Neo field's block structure would cause the new sort order not to be saved

## 2.11.3 - 2021-07-27

### Fixed
- Fixed a bug where new draft blocks could be lost in some cases when merging updated entry content into drafts
- Fixed a bug where a revision could lose blocks when reverting the entry to that revision
- Fixed a bug where Neo sub-fields' status indicators were not positioned correctly

## 2.11.2 - 2021-07-22

### Fixed
- Fixed a bug where block children/descendant queries weren't working on draft/revision previews
- Fixed a bug where drafts wouldn't autosave after using the 'move up' or 'move down' Neo block actions

## 2.11.1 - 2021-07-15

### Changed
- Neo now requires Craft 3.7.0 or later

### Fixed
- Fixed a bug where Neo field data could be lost when saving an entry, if a new Neo block had been created, and the entry was saved before the provisional draft could autosave after a content change

## 2.11.0 - 2021-07-13

### Added
- Added compatibility with provisional drafts in Craft 3.7
- Added compatibility with canonical/derivative element merging in Craft 3.7
- Added the ability to copy and paste block types between fields

### Changed
- Neo now requires Craft 3.7.0-beta.6 or later

### Deprecated
- Deprecated Neo blocks' `getModified()` and `setModified()` methods

### Fixed
- Fixed a bug where drafts wouldn't autosave after deleting, cloning or pasting Neo blocks, or when collapsing or expanding blocks by double-clicking the top bar

## 2.10.8 - 2021-07-14

### Fixed
- Fixed a bug where, when using the 'Add block above' action on a child block, new block buttons for block types that had reached their 'Max Sibling Blocks of This Type' limit were not being disabled

## 2.10.7 - 2021-07-08

### Fixed
- Fixed a bug when cloning or pasting a Neo block that uses the child blocks UI element, where two child block containers would appear

## 2.10.6 - 2021-07-06

### Fixed
- Fixed a bug where GraphQL queries for a Neo field's contents with the field's `level` argument set to `0` would only return level 1 blocks, rather than all blocks belonging to the field

## 2.10.5 - 2021-06-25

### Added
- Added the ability to clone a Neo field's block types within that field when editing the field's settings, from dropdown menus on the block types sidebar

### Changed
- When editing a Neo field's settings, block types can now be deleted from the new dropdown menus on the block types sidebar

## 2.10.4 - 2021-06-22

### Fixed
- Fixed a GraphQL error that could occur when accessing a Neo block's children on a query for entries from a non-default site

## 2.10.3 - 2021-06-21

### Fixed
- Fixed a bug where, if a Neo field was showing the "Add a block" dropdown and a non-empty block type group followed a non-empty block type group, some HTML would fail to be output, causing styling issues

## 2.10.2 - 2021-06-17

### Fixed
- Fixed an error when saving Neo fields from console commands (thanks @nstCactus)

## 2.10.1 - 2021-06-16

### Fixed
- Fixed a bug where, when trying to delete all block type group(s) belonging to a Neo field, they would fail to be deleted

## 2.10.0 - 2021-06-14

### Added
- Added the Child Blocks UI element, which allows for putting the child blocks in a single place anywhere in a block type's field layout, rather than displaying them at the bottom of every tab
- Added the `optimiseSearchIndexing` plugin setting (enabled by default)
- Added the `getByHandle()` method to the `BlockTypes` service

### Changed
- Neo now requires Craft 3.6.6 or later
- When Craft runs garbage collection, incomplete Neo element data will now be deleted
- When saving a Neo block that doesn't have a field layout, doesn't have any searchable sub-fields or belongs to a non-searchable Neo field, Neo will now opt out of updating search indexes for that block unless the `optimiseSearchIndexing` plugin setting is disabled
- Updated Neo fields' 'add block' button styling to match that of Matrix fields' 'add block' button
- Changed Neo's JavaScript dependency package management from Yarn to NPM

### Fixed
- Fixed a bug where Neo input blocks could be dragged to positions where they would exceed their field's Max Levels setting
- Fixed a bug where Neo blocks' enabled and collapsed states weren't retained in some cases when cloning them
- Fixed a bug where, if a Neo field was showing the "Add a block" dropdown, the headings of block type groups would always show, even if none of that group's block types could be added in that position in the field

## 2.9.13 - 2021-06-09

### Fixed
- Fixed a potential migration error when updating to Neo 2.9.11 or 2.9.12

## 2.9.12 - 2021-06-08

### Fixed
- Fixed incompatibility with Field Manager (thanks @engram-design)
- Fixed a bug that could sometimes cause Neo blocks to appear out of order

## 2.9.11 - 2021-06-04

### Fixed
- Fixed a bug when editing a Neo field's settings, where a new block type's handle would not be updated in the child block type checkbox values if the handle was auto-updated as a result of the block type name being updated; if the new block type was then used as a child block type, the child block types array would store an empty string instead of the block type's handle
- Runs a migration to remove empty strings from child block type arrays, which can cause JavaScript errors in the Craft control panel

## 2.9.10 - 2021-05-31

### Fixed
- Fixed a bug where Neo blocks' children could not be retrieved in GraphQL entry queries when the `draftId` or `revisionId` arguments were used

## 2.9.9 - 2021-05-25

### Fixed
- Fixed a bug where an error could occur when attempting to apply a project config

## 2.9.8 - 2021-05-18

### Fixed
- Fixed a bug when viewing the preview of an entry draft or revision, where querying for a Neo block's children or other relatives wouldn't return any blocks

## 2.9.7 - 2021-05-05

### Added
- Added the `allowOwnerDrafts` and `allowOwnerRevisions` parameters for Neo block queries (thanks @nickdunn)

## 2.9.6 - 2021-04-14

### Fixed
- Fixed a JavaScript error on Craft 3.6.11 and later

## 2.9.5 - 2021-03-30

### Fixed
- Fixed a potential 'Invalid owner ID' error when changing a Neo field's propagation method
- Fixed a potential 'Attempting to save an element in an unsupported site' error when resaving entries
- Fixed an error that occurred when publishing a draft with an outdated Neo field (thanks @brandonkelly)

## 2.9.4 - 2021-03-25

### Fixed
- Fixed a bug that occurred when generating search keywords for a Neo field, where a sub-field's search keywords would be included even if the sub-field was set not to be searchable

## 2.9.3 - 2021-03-17

### Fixed
- Fixed an issue that occurred on Craft installs running Craft 3.6.7 or later, on which the control panel is accessible on a separate subdomain, where links to an entry in a Redactor field on a Neo block would have unwanted attributes added

## 2.9.2 - 2021-03-09

### Fixed
- Fixed a bug with Neo 2.9.1 where the max levels warning would display in some cases where it shouldn't display

## 2.9.1 - 2021-03-09

### Fixed
- Fixed a bug that occurred when a Neo field's Max Levels setting is used, where a Neo block's child block buttons and max levels warnings would not show or hide when the block was dragged to or from the max level

## 2.9.0 - 2021-02-23

### Added
- Added move up/down options to Neo blocks' actions menus
- Added the Max Levels field setting
- Added support for the tag-based cache invalidation introduced in Craft 3.5
- Added support for GraphQL mutations introduced in Craft 3.5
- Added a default English translation file
- Added `getConfig()` methods for the `BlockType` and `BlockTypeGroup` models

### Changed
- Neo-to-Matrix conversion now supports converting Neo fields that have Super Table sub-field(s)
- Updated Neo's project config rebuild code to rebuild based on the `BlockType` and `BlockTypeGroup` `getConfig()` methods
- Neo blocks' tab names will now display in red when a field in that tab has had a validation error

### Fixed
- Fixed a display issue with the tab dropdown button icon on Neo input blocks on mobile devices
- Fixed some padding issues with Neo input blocks

## 2.8.23 - 2021-02-22

### Fixed
- Fixed a bug in Neo 2.8.22, where an exception could occur when editing a Neo field if one of its block types allows all of the field's block types as child block types

## 2.8.22 - 2021-02-22

### Fixed
- Fixed a bug when saving a Neo field, where a Neo block type's `childBlocks` property could mistakenly be set as a string representing an array, rather than an array, which could cause project config formatting issues

## 2.8.21 - 2021-02-19

### Fixed
- Fixed a bug in Neo 2.8.20 when loading a previously-saved draft, where Neo blocks would fail to resave when changing subfield values

## 2.8.20 - 2021-02-19

### Fixed
- Fixed a bug when editing drafts, where a Neo block could sometimes fail to resave if its subfield values were set back to the same values it had on page load

## 2.8.19 - 2021-02-10

### Fixed
- Fixed a bug where, for revisions of entries on sections that were not enabled for the primary site, Neo fields' block structures were not being saved, resulting in all blocks being treated as top-level blocks

## 2.8.18 - 2021-02-02

### Changed
- Updated the `standard` JavaScript dev dependency to 16.0.3

### Fixed
- Fixed a bug when using a Neo block's `useMemoized()` method and accessing a block's children, where a disabled block's enabled descendants would be attached to the previous enabled block existing at the same level as the disabled block

## 2.8.17 - 2021-01-12

### Fixed
- Fixed a GraphQL bug where, when a query included more than one reference to the same Neo field and did not specify a block level, the returned data would include blocks of all levels, rather than just the top level

## 2.8.16 - 2020-12-30

> {warning} This release includes a migration to hard-delete old Neo block data, no longer associated with an owner element, that may cause errors when setting a new propagation method for their fields.  Make sure to backup your database before updating Neo.

### Fixed
- Runs a migration to hard-delete any Neo blocks with an `ownerId` that doesn't exist in the `elements` table; fixes potential issues when setting a new propagation method for the fields those blocks belong to
- Fixed an issue where, in some cases, Neo blocks could remain in the database when they should have been hard-deleted
- Rewrote the Neo 2.8.14 migration to fix the "There was a problem getting the parent element" error that could occur in some cases

## 2.8.15.1 - 2020-12-10

### Fixed
- Fixed a PostgreSQL error with the migration in Neo 2.8.15 (thanks @boboldehampsink)

## 2.8.15 - 2020-12-09

> {warning} This release includes a migration to soft-delete old Neo block data, no longer associated with any block structure, that may cause errors when setting a new propagation method for their fields.  Make sure to backup your database before updating Neo.

### Fixed
- Runs a migration to soft-delete any Neo blocks without a `sortOrder`, which could have occurred if any blocks were no longer associated with a block structure prior to Neo 2.7.0; fixes an "Attempting to save an element in an unsupported site" error when setting a new propagation method for the fields those blocks belong to

## 2.8.14 - 2020-11-09

> {warning} This release includes a migration affecting multi-site Craft installations, which reapplies propagation methods to blocks belonging to Neo fields with propagation methods other than "save blocks to all sites the owner element is saved in", due to a bug where changes to a field's propagation method were not being applied to their blocks.  If your Craft install is multi-site, make sure to backup your database before updating Neo.

### Added
- In Neo's JavaScript code, added the `afterInit` event to `Neo.Input`

### Fixed
- Fixed an issue on multi-site Craft installs where, when a Neo field's propagation method was changed, the field's blocks were not having the new propagation method applied

## 2.8.13 - 2020-11-02

### Changed
- Attempts to apply project config changes that include a Neo block type that belongs to an invalid field UID will now throw an exception immediately on the failure to get the field's ID, rather than still trying (and failing) to insert the block type into the database

### Fixed
- Fixed an issue where Neo block types' field layout UIDs were not being saved in the project config, which could cause attempts to apply project config YAML file changes to fail to apply the correct block type settings on Craft 3.5 releases prior to Craft 3.5.13
- Fixed an issue when performing a project config rebuild, where Neo field layouts could lose their custom labels and UI elements

## 2.8.12 - 2020-10-12

### Fixed
- Fixed a bug when editing a Neo block type's field layout, where non-required fields that had custom labels or instructions were incorrectly showing as required on the field settings modal
- Fixed some Neo-to-Matrix conversion errors

## 2.8.11 - 2020-10-01

### Fixed
- Fixed a bug where asset thumbnails on a Neo block's hidden tabs would not be loaded when the tab was selected

## 2.8.10.1 - 2020-09-21

### Fixed
- Fixed an issue introduced in Neo 2.8.10 where some more complex GraphQL queries could produce duplicate results

## 2.8.10 - 2020-09-21

### Added
- Added the ability in GraphQL queries to query Neo field data according to the block level (thanks @smcyr)

### Fixed
- Fixed a bug where some eager loading queries of Neo field data could return duplicate block data
- Fixed an error that occurred when executing a Neo block query in which the `fieldId` and `ownerId` properties were both arrays

## 2.8.9 - 2020-09-18

### Fixed
- Fixed a bug when editing a Neo field's settings where, in some cases, sub-fields could incorrectly display as required in the field layout designer
- Fixed a bug with editing a Neo field's settings where, when clicking to delete a block type but then clicking cancel on the confirmation window, that block type would be removed from the Child Blocks settings field on all block types

## 2.8.8 - 2020-09-05

### Changed
- Neo now requires Craft 3.5.8 or later

### Fixed
- Fixed a compatibility issue introduced with Craft 3.5.8, where required Neo sub-fields would not appear as required in the field layout designer
- Fixed an issue where saving an otherwise-unmodified Neo block with a newly-added Super Table field, that has either a minimum number or rows or a static row, would cause an "Attempting to duplicate an unsaved element" error

## 2.8.7 - 2020-08-26

### Changed
- Neo's JavaScript source has been converted to use the Standard JS style

### Fixed
- Fixed a bug where some GraphQL queries of Neo field data could return duplicate block data
- Fixed a bug where Neo block pasting was not being disabled at the top level if a field had reached its max top level blocks

## 2.8.6 - 2020-08-18

### Fixed
- Fixed an error that could occur when simultaneously upgrading Craft to 3.5, Neo to 2.8 and Field Labels to 1.3
- Removed all cases of loose equality comparisons in Neo's JavaScript source

## 2.8.5 - 2020-08-17

### Changed
- Neo now requires Craft 3.5.4 or later

### Fixed
- Fixed a bug where Neo fields which were set to translatable on a Craft 2 install would not have their propagation method properly set when upgrading to Craft 3
- Fixed a compatibility issue with Craft 3.5.4 when editing a Neo field's settings, where the instructions on block type and block type group settings fields were not displaying properly
- Fixed a compatibility issue with Craft 3.5.4 when editing a Neo block type's field layout, where the hide label checkbox would not work on other fields after checking the box on one field
- Fixed a compatibility issue with Craft 3.5.4 when editing a Neo block type's field layout, where `__blank__` would display as a field's label if the field was set to have its label hidden
- Fixed a bug when editing a Neo field's settings, where the Field Layout tab would display and be selectable if a block type group was selected
- Some minor changes to JavaScript code related to new block button enabling/disabling, block dragging, and Neo-to-Matrix conversion

## 2.8.4 - 2020-08-12

### Fixed
- Fixed an issue where child blocks could not be dragged if `Max Sibling Blocks of This Type` was not set on the block type

## 2.8.3 - 2020-08-10

### Changed
- When saving a Neo field's settings, existing block type groups are now properly resaved, not just deleted and recreated

### Fixed
- Fixed an error, involving block type group resaving, that could occur when running `./craft project-config/apply` when Craft's `allowAdminChanges` setting is disabled
- Fixed an error that occurred with Craft 3.5 / Neo 2.8 when rebuilding the project config, if any Neo block type field layout consisted only of blank tab(s)
- Fixed a bug with previous Neo 2.8 releases where default values on dropdown fields were not being applied to new Neo blocks
- Fixed a potential GraphQL "Failed to validate the GQL Schema" error when querying for Neo field data
- Replaced the usage of various deprecated Twig classes

## 2.8.2 - 2020-08-05

### Changed
- Neo now requires Craft 3.5.0 or later

### Fixed
- Fixed a CSS issue where unselected Neo block tab content would still be displayed along with the selected tab's content when in live preview mode

## 2.8.1 - 2020-08-04

### Fixed
- Fixed a CSS issue where unselected Neo block tab content would still be displayed along with the selected tab's content
- Fixed a bug where Neo fields created prior to the 2.8.0 upgrade, and that had not since been saved, would cause the `craft project-config/apply` command to fail, due to the lack of `maxSiblingBlocks` property

## 2.8.0 - 2020-08-04

> {warning} As part of the changes to Neo 2.8 to support Craft 3.5's new field layout designer, it's no longer possible to save blank tabs in Neo block types' field layouts.  Existing blank tabs should be retained when upgrading to Craft 3.5 and Neo 2.8, but they will be lost if they're still blank next time the field is saved.  It's recommended to place a UI element in any blank tabs that should be kept.

### Added
- Full support for Craft 3.5's new field layout designer
- Added the `Max Sibling Blocks of This Type` block type setting, which sets the maximum number of blocks of that block type allowed under one parent block or at the top level

### Changed
- Neo now requires Craft 3.5.0-RC6 or later
- Updated node-sass to ^4.14.1

### Deprecated
- Deprecated `benf\neo\integrations\fieldlabels\FieldLabels`
- Deprecated `benf\neo\converters\Field` (Neo Field Converter for Schematic)
- Deprecated `benf\neo\converters\models\BlockType` (Neo BlockType Converter for Schematic)
- Deprecated `benf\neo\converters\models\BlockTypeGroup` (Neo BlockTypeGroup Converter for Schematic)

### Removed
- Removed unused JavaScript related to compatibility with Quick Field, a Craft 2 plugin which was not updated for Craft 3
- Removed usage of babel-polyfill

### Fixed
- Fixed a bug where validation errors that occurred when attempting to publish a draft were not displaying on Neo blocks
- Fixed the position of the new block type / block type group buttons on the Neo field configurator
- Fixed a bug where Neo-to-Matrix conversion failures were not being logged

## 2.7.25 - 2020-07-28

### Fixed
- Fixed propagation issues that happen when an entry is enabled for a new site (thanks @brandonkelly)

## 2.7.24 - 2020-07-19

### Fixed
- Fixed a bug where, when saving a Neo field's configuration, block types' field layouts were being deleted and recreated
- Updated the lodash version requirement in yarn.lock to 4.17.19

## 2.7.23 - 2020-07-17

### Fixed
- Fixed a JavaScript error preventing Neo input blocks from appearing, if the Neo field had any block types with the handles `filter` or `push`
- Corrected the position of a Neo input block's corner checkbox

## 2.7.22 - 2020-07-12

### Fixed
- Fixed an issue where, when editing a Neo field and selecting to make the field a Matrix field, the Neo-to-Matrix conversion prompt was not appearing
- Fixed an issue where a Neo field containing a Super Table field would cause Neo-to-Matrix conversion to fail, also causing attempts to uninstall Neo to fail

## 2.7.21 - 2020-07-02

### Fixed
- Fixed an issue where a collapsed block preview could still cover the settings/drag buttons in some cases
- Fixed the padding between parent block content and child blocks
- Fixed an issue where Neo blocks would show the mobile tab dropdown regardless of the device's screen size

## 2.7.20 - 2020-06-25

### Changed
- When loading an element edit page, Neo fields now display a spinner before they load

### Fixed
- Reduced the bottom padding on collapsed Neo blocks
- Fixed the side padding on Neo blocks with no fields or child block types
- Fixed an issue where the fade-out of collapsed block preview text was not displaying correctly in Safari

## 2.7.19 - 2020-06-21

### Added
- Collapsed block previews can now display Linkit field content

### Fixed
- Improved Neo's performance during a project config rebuild
- Fixed an issue with displaying a Typed Link Field's selected type in collapsed block previews

## 2.7.18 - 2020-06-18

### Fixed
- Fixed an issue where the sort order of Neo blocks was not being set properly when editing an element using an element editor modal, causing new blocks to disappear

## 2.7.17 - 2020-06-17

### Fixed
- Fixed an issue on entry drafts, where Neo blocks with a level that matched the entry's element ID would have their level overwritten with the entry draft element ID

## 2.7.16 - 2020-06-17

### Added
- Collapsed block previews can now display Category Groups Field content

### Changed
- Neo now requires Craft 3.4.24 or later

### Fixed
- In combination with an update to Craft 3.4.24, fixes an Exception that could occur when saving an entry (thanks @brandonkelly)
- Fixed issue where unmodified Neo fields on entry drafts were being incorrectly set as modified

## 2.7.15 - 2020-06-05

### Fixed
- Removed old Reasons plugin compatibility code from the Craft 2 version; fixes JavaScript errors when the Craft 3 version of Reasons is installed

## 2.7.14 - 2020-06-04

### Fixed
- Fixed issue where disabling a parent block, while editing an entry draft, would cause its child blocks to save at the field's top level

## 2.7.13 - 2020-06-03

### Fixed
- Fixed bug where collapsed block previews could cover the options dropdown and block drag buttons

## 2.7.12 - 2020-05-24

### Fixed
- Fixed bug where Neo blocks with asset fields with dynamic upload paths were not finding the correct path when editing a new category or product
- Compatibility with Field Labels v1.2.0

## 2.7.11 - 2020-05-20

### Fixed
- Fixed bug where blocks' correct modified states were not being set after validation errors, causing block content modified before the validation error, but not after, not to be saved

## 2.7.10 - 2020-05-07

### Changed
- Updated neo block styling to be more inline with craft 3.4. #346

## 2.7.9.1 - 2020-04-28

### Changed
- remove void return types since it's not compatible with php 7.0. #342

## 2.7.9 - 2020-04-27

### Changed
- changes to v2.7.x migration file #336

### Fixed
- need to pass the id for entries, globals and categories. #333
- remove the type for `_setupBlocksHasSortOrder` #342

## 2.7.8 - 2020-04-23

### Changed
- better postgresql block query fix (more clean)

### Added
- added some changes for when a user is updating craft to 3.4 from an older version

## 2.7.7.1 - 2020-04-17

### Changed
- use `$isModified` variable instead within `_createBlocksFromSerializedData`  - thanks @ronaldex

## 2.7.7 - 2020-04-16

### Fixed
- Fixed an issue when publishing a multisite draft #330, [comment of issue](https://github.com/spicywebau/craft-neo/issues/330#issuecomment-613833346)
- Added a fix for the neo structure job. Thanks @engram-design.
- Update the block preview on load so it correctly shows the preview. #331

### Changed
- further changes to the neo structure saving job + changes on how the structure is saved

## 2.7.6 - 2020-04-11

### Fixed
- Made changes for the neo structure job. #330 #325
- Fixed an issue with the draft and revision creation which children are displaced

## 2.7.5 - 2020-04-03

### Fixed
- Fixed multisite propagation issue #328.
- Made a change for redactor detection. #329

### Changes
- Small changes to the block query

## 2.7.4 - 2020-04-02

### - Fixed
- On publish of draft or reverting from revision, create the structure immediately #323, #325
- Fix postgresql issue #257, #324 and fix `sortOrder` issue relating to the changes.

## 2.7.3 - 2020-03-27

### Fixed
- Do not create a structure using a job task if a new entry is being created (since it's duplicated from the first draft). Create the structure immediately.

## 2.7.2.2 - 2020-03-25

### Changed
- we need to change the delay of the observer. redactor has 200ms delay when syncing. #319

## 2.7.2.1 - 2020-03-24

### Fixed
- Fixed the migration for `sortOrder`, should be using the `elementId` instead of `id` for `structureelements` - thanks @Mosnar

## 2.7.2 - 2020-03-24

### Fixed
- need to group by all selected values, not just `sortOrder` #316
- add the `structureId` to the element query. #317 #318

## 2.7.1.1 - 2020-03-23

### Changed
- if there are blocks to delete then we need to rebuild the structure.

## 2.7.1 - 2020-03-23

### Changed
- Add the `orderBy` clause for postgres only. #315

## 2.7.0 - 2020-03-22

> {note} ~This update contains a schema update which could potentially break the structure of the neo blocks. Make sure to backup your database and do the update locally first as it includes migration of all neo blocks. If the structure is indeed broken, re-saving the page should resolve it. See #336. I'm currently looking into this issue so it's best to wait at version v2.6.x for now.~
>
> The latest release v2.7.9+ has changes to the migration file + fixes the issue when updating from craft at 3.3 and lower #326 (this is most likely the cause of out of order/missing blocks). The migration should only update the new `sortOrder` column for each neo block and shouldn't cause any issues with missing blocks/out of order blocks.

### Changed
- Added `sortOrder` to blocks
- Modified how the the structure is being saved. #257
- Re-added the project config read only check.

### Added
- Added a migration file that creates a new column (`sortOrder`) for the `neoblocks` table and fills in the data using the current structure elements. #257
- Added further checks for delta updates.
- Added redactor field change detection

## 2.6.5.1 - 2020-03-18

### Fixed
- need to check if `wasModified` is set. #310

## 2.6.5 - 2020-03-18

### Fixed
- Fix an issue where the neo field couldn't be modified. #310

## 2.6.4 - 2020-03-12

### Fixed
- Fixed an issue where it was impossible to eager-load fields inside Neo fields. #306. - thanks @andris-sevcenko

### Changed
- Small change for the delta updates. Should be more reliable in determining which blocks should be updated. #257.

## 2.6.3 - 2020-03-04

### Added
- Group buttons will now be translated. #301.
- If there's an error within a field + it's collapsed, the block name will now be highlighted. #299

### Fixed
- Fixed an issue where it would throw an error about a missing function on the dashboard widgets. #304

## 2.6.2 - 2020-02-28

### Added
- Add German translation - thanks @gglnx

### Fixed
- Media fields using dynamic paths should now show the correct directory when adding/copy/cloning new blocks. #233
- Fixed an issue where the groups wouldn't allow the field to be saved when using the project config + further changes for the group duplicating issue.
- Any required matrix blocks (that's empty) will now correctly highlight the tab to show where the issue is. #299

## 2.6.1 - 2020-02-04

### Fixed
- Fixed a delta blocks namespace issue if the neo field was called 'blocks'
- Fixed an issue where the structure owner site id is 0 and causes an error when saving. It must be at least the primary site id. (craft 2 -> 3 issue)

### Changed
- updated the input controller for the namespace change.

## 2.6.0 - 2020-01-29

### Added
- Added Delta support
- Added Multi level ordering

### Fixed/Changes
- Fixes and updates for Craft 3.4

## 2.5.10 - 2020-01-22

### Added
- Added `getSupportedSiteIds()`

### Deprecated
- Deprecated `getSupportedSiteIdsForField()`. Used `getSupportedSiteIds()` instead.

### Fixed
- Check if `NerdsAndCompany\Schematic\Converters\Base\Field` and `NerdsAndCompany\Schematic\Converters\Models\Base` is available. #286
- Updated `isDraftPreview` to return false if it's a console request
- Don't set the project config value if in readOnly mode #217

## 2.5.9 - 2019-12-17

### Fixed
- Fix #287. Make sure there is post data when saving field labels for neo.

## 2.5.8 - 2019-11-14

### Fixed
- Fix a GraphQL issue where the children blocks are being returned in an incorrect order. #281.

## 2.5.7 - 2019-10-22

### Fixed
- Field Labels integration: Fixed issue with blank field layout - thanks @verbeeksteven

## 2.5.6 - 2019-10-18

### Fixed
- Fix #274 - removal of the data-confirm-unload attr is not needed anymore since a better solution for the "Leave Site" issue was implemented in a previous commit.

## 2.5.5 - 2019-10-16

### Fixed
- Fixed issue with field labels where removing relabeled field doesn't actually remove them.
- Removed ignoring revision and draft blocks by default in beforePrepare() as it was causing issues with graphql live preview and previewing drafts.

## 2.5.4 - 2019-10-11

### Changed
- reflect changes that was made in https://github.com/craftcms/cms/commit/80192a55f8f89b129abff2b43d4a0c7d66d60f45
- update format document spacing

### Fixed
- Fix issue #270

## 2.5.3 - 2019-10-01

### Changed
- Stop the blocktype always recreating a fieldlayout.uid - thanks @samuelbirch

## 2.5.2 - 2019-09-30

### Fixed
- Fix #250. When rebuilding the project.yaml file, the fieldLayouts will now correctly be included

## 2.5.1 - 2019-09-24

### Fixed
- Fix #263 - correctly get the children blocks (GraphQL)
- Add in a check to make sure we're getting the next level blocks only. (GraphQL)

## 2.5.0 - 2019-09-23

### Added
- GraphQL implementation
- Add GraphQL how to doc

## 2.4.8 - 2019-09-14

### Fixed
- Fix #232 - make sure to clear the uid when cloning the field so the original doesn't get overwritten when converting.

### Changed
- Update tar
- Update js-yaml

## 2.4.7 - 2019-09-10

### Fixed
- Fix #249
- Fix #255, removed 0 index with Field Labels Integration as it causes `Undefined offset: 0`

## 2.4.6 - 2019-09-10

### Fixed
- Field Labels compatibility update
- Fix #243. Revert handleDeletedBlockType changes as it causes some issues when deleting the block types in the neo field.

## 2.4.5 - 2019-08-30

### Fixed
- fix multi-site issue where on draft creation the contents of the draft is copied over to the other site drafts. #246
- Fixed issue with saving a new entry and the alert that appears (the "Do you want to leave" msg on save).
- Fixed issue with field deletion when there's multi level nested blocks #249


## 2.4.4 - 2019-08-21

### Fixed
- require the siteId for neo structures for eager loading
- fix getSupportedSiteIdsForField language comparison

### Changed
- Removed the queue job after changing the propagation method for the neo field as it was causing `Attempting to save an element in an unsupported site.`. Propagation changes will be applied once the entry containing the field is saved.

## 2.4.3 - 2019-08-16

### Fixed
- added beforeSave function to properly update the neo field propagation method by setting and checking the oldPropagationMethod variable.

- if PROPAGATION_METHOD_NONE is NOT set for the neo field, make sure to duplicate the block and structures for the other sites using the primary content.

- Fix indentation of code

## 2.4.2 - 2019-08-08

### Fixed
- Fix - Need to set the new key for neo structures since the ownerSiteId is now set

## 2.4.1 - 2019-08-08

### Fixed
- Fixed issue #239
- Fixed indentation, swapped to tabs.

### Changed
- Cleaned up the Field Service

## 2.4.0 - 2019-08-06

### Changed - 3.2 saving changes
- update composer
- fix multisite site id block save issue
- fix duplicateBlocks so it creates the neo structure for duplicated blocks
- changes for blockstructure and duplicateBlocks
- remove deletion of blocks from duplicateBlocks function
- FIX: Include 'ownerSiteId' when querying neo structure data.
- fix type error when updating search indexes
- fixes to neo structures for multisite
- deprecate ownerSiteId and additional changes to compensate.
- making neo more inline with craft

## 2.3.7 - 2019-08-01

### Fixed
- Fix #227 - fixed issue where Neo fields could lose their content when updating to Craft 3.2. - thanks @brandonkelly

## 2.3.6 - 2019-07-19

### Update - Minor Patch for Craft 3.2
- implement BlockElementInterface
- update getOwner and correctly return ElementInterface
- update afterSave

## 2.3.5.2 - 2019-08-01

### Fixed
- Fix craft constraint to allow update to 3.2

## 2.3.5.1 - 2019-07-31

### Fixed
- Fix #227 - fixed issue where Neo fields could lose their content when updating to Craft 3.2
- Update craft version constraints

## 2.3.5 - 2019-05-24

### Fixed
- Fix #210 - check if viewing a shared draft so it can retrieve the correct data

## 2.3.4 - 2019-05-22

### Fixed
- Fix #214 - added in type filtering function that was missing for live preview
- Fix #213 - get enabled blocks only instead of any status blocks for live preview

## 2.3.3 - 2019-05-04

### Fixed
- Fix PostgreSQL error when saving new block types - thanks @ttempleton
- Fixed issue where groups were duplicated when changing min/max blocks

## 2.3.2 - 2019-04-24

### Fixed
- Ensure field layout IDs are set when setting a field's block types - Thanks @ttempleton

## 2.3.1 - 2019-04-16

### Fixed
- Project Config - typecast group sortOrder to int

## 2.3.0.1 - 2019-04-11

### Changed
- Disable saveModifiedBlocksOnly for now

## 2.3.0 - 2019-04-03

### Added
- Added support for the project config rebuild functionality introduced in Craft 3.1.20
- Added the Max Top-Level Blocks field setting
- Added the `collapseAllBlocks` plugin setting, allowing all input blocks to display as collapsed by default
- Restored the `saveModifiedBlocksOnly` plugin setting (New to Neo 2; previously added to Neo 1.4.1)
- Restored support for the Field Labels plugin (New to Neo 2; previously added to Neo 0.5.0 under Field Labels' previous name, Relabel)
- Added `benf\neo\events\BlockTypeEvent`
- Added `benf\neo\services\BlockTypes::EVENT_BEFORE_SAVE_BLOCK_TYPE`
- Added `benf\neo\services\BlockTypes::EVENT_AFTER_SAVE_BLOCK_TYPE`
- Added CKEditor field content to collapsed block summaries

### Changed
- Neo now requires Craft 3.1.20 or later
- New icon
- By default, Neo will only save modified blocks when saving a Neo field's value (New to Neo 2; previously added to Neo 1.3.0)

### Fixed
- Fixed collapsed block summaries of colour fields on entry revisions

## 2.2.8 - 2018-03-27

### Fixed
- Fixed issue where duplicate block type groups could be created (thanks @boboldehampsink)

## 2.2.7 - 2019-03-23

### Fixed
- Fixed issue, when applying a project config to another environment or project, where a Neo field and block types could be synced before other fields they use, causing the block types not to have those fields in the target environment/project

## 2.2.6 - 2019-03-14

### Changed
- Neo now requires Craft 3.1.13 or later

### Fixed
- Fixed error when applying a project config to another project where a Neo field from the first project doesn't exist
- Fixed error in some cases when deleting a block type and that block type's blocks

## 2.2.5 - 2019-02-23

### Fixed
- Fixed issue in Neo 2.2.4 with disabled blocks being deleted when saving a Neo field's contents

## 2.2.4 - 2019-02-19

### Changed
- Now supports filtering an entry draft's Neo field content with query parameters

### Fixed
- Fixed error when creating a new section

## 2.2.3 - 2019-02-06

### Added
- Added support for the CP Field Inspect plugin

### Fixed
- Fixed issue with pasting or cloning blocks where number field values were not retained

## 2.2.2 - 2019-01-30

### Fixed
- Fixed issue where block types would lose any blank tabs in Neo 2.2.0 and 2.2.1

## 2.2.1 - 2019-01-28

### Fixed
- Fixed issue where Craft would try to run Neo's Craft 3 upgrade migration on updating to Neo 2.2.0 if Neo 2 was originally installed as a pre-release version

## 2.2.0 - 2019-01-27

### Added
- Added Craft 3.1 project config support
- Added Craft 3.1 soft-deletes support
- Restored the ability to convert Neo fields to Matrix (New to Neo 2.x; previously added to Neo 1.4.0)
- Restored automatic Neo-to-Matrix conversion on uninstalling Neo (New to Neo 2.x; previously added to Neo 1.4.0)

### Changed
- Neo now requires Craft 3.1.0 or newer
- Collapsed block summaries now also show content from child blocks

### Fixed
- Fixed issue where blocks would disappear if a Neo field's 'manage blocks on a per-site basis' setting was changed
- Fixed duplicated blocks when saving after setting an existing field to manage blocks on a per-site basis

## 2.1.8 - 2019-01-26

### Fixed
- Fixed multi-site issue, when enabling a site for a section, where a Neo field set not to manage its blocks on a per-site basis would cause the resaving of the section's entries to fail

## 2.1.7 - 2019-01-25

### Fixed
- Fixed issue where Neo blocks would not appear on duplicated entries

## 2.1.6 - 2019-01-24

### Fixed
- Fixed error when pasting or cloning blocks on single-site Craft installations

## 2.1.5 - 2019-01-17

### Fixed
- Fixed issue where structure IDs were not being set for block queries, causing duplicated blocks to appear in Craft 3.1.0 and newer

## 2.1.4 - 2019-01-13

### Fixed
- Fixed issue on multi-site Craft installs where entry/category fields in new or pasted/cloned Neo blocks were always listing elements from the primary site

## 2.1.3 - 2019-01-12

### Fixed
- Fixed error when duplicating an element with a Neo field set not to manage blocks on a per-site basis

## 2.1.2 - 2019-01-05

### Added
- Added the ability to copy multiple selected blocks from different levels, which can be pasted at the same level

### Fixed
- Now always saves the block type field layout when saving a block type; fixes an issue with Schematic importing Neo before its field layout fields are imported (thanks @boboldehampsink)

## 2.1.1 - 2018-12-10

### Fixed
- Fixed issue where Neo was sometimes causing Craft to fail to delete stale template caches

## 2.1.0 - 2018-12-01

### Added
- Added the ability to copy multiple selected blocks at the same level
- Added Schematic support (thanks @boboldehampsink)
- Restored the Duplicate menu option on blocks -- now named Clone

### Fixed
- Fixed incorrect block levels on drafts of existing entries
- Fixed issues with blocks using memoized datasets for queries
- Fixed field eager loading on PostgreSQL (thanks @boboldehampsink)
- Fixed block previews of unfilled color fields
- Fixed issue where the handle of an existing block type was changing when editing the name

## 2.0.4 - 2018-11-23

### Fixed
- Fixed 500 error accessing block relatives from relational fields in live preview

## 2.0.3 - 2018-11-22

### Fixed
- Fixed incorrect block levels being set in drafts
- Set correct Craft minimum version requirement of 3.0.17

## 2.0.2 - 2018-11-19

### Fixed
- Clean up block structures if duplicates exist, which would cause blocks to appear to duplicate

## 2.0.1 - 2018-11-02

### Fixed
- Block structure-related fixes
- Restored ability to translate block type names

## 2.0.0 - 2018-10-30
- Initial release for Craft 3
