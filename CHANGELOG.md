# Changelog

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
