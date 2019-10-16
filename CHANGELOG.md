# Changelog

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
