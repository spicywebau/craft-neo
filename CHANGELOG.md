# Changelog

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
