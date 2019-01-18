# Changelog

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
