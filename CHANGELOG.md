# Changelog

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
