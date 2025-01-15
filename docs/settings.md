# Settings

## `blockTypeIconPath`

Type: `string`
Default: `'@webroot'`

If [`newBlockMenuStyle`][1] is set to something other than `'classic'` and [`blockTypeIconSelectMode`][2] is set to `'path'`, this setting sets the folder from which non-asset SVG files can be set as block type icons.

## `blockTypeIconSelectMode`

Type: `string`
Default: `'sources'`

This setting controls whether to select block type icons from asset sources or from non-asset SVG files in a specific folder. The following options are available:

- `'path'`: select block type icons from non-asset SVG files, in a specific folder set using [`blockTypeIconPath`][3]
- `'sources'`: select block type icons from asset sources

## `blockTypeIconSources`

Type: `string|string[]`
Default: `'*'` (allowing all sources)

If [`newBlockMenuStyle`][1] is set to something other than `'classic'` and [`blockTypeIconSelectMode`][2] is set to `'sources'`, this setting controls which icon asset sources are allowed to be used for setting block type icons.

## `collapseAllBlocks`

Type: `bool`
Default: `false`

This setting controls whether all Neo input blocks should display as collapsed when loading an element editor. When this is enabled, expanding or collapsing previously-existing blocks will not cause their new collapsed state to be saved, however the collapsed state of new blocks will be saved, in case the setting is disabled later.

## `defaultAlwaysShowGroupDropdowns`

Type: `bool`
Default: `true`

This setting controls the global setting for whether Neo block type groups should always have their dropdowns shown, even when only one block type from a group is available to use. When set to `false`, in such a case, the block type button will be shown instead. This behaviour can also be set on a per-group basis, through the Neo field settings edit page.

## `enableBlockTypeUserPermissions`

Type: `bool`
Default: `true`

This setting controls whether to allow setting user permissions for creating, editing and deleting blocks of a certain type. Note that, if disabled, resaving a user's or user group's permissions will cause any existing block type permissions to be lost.

## `enableLazyLoadingNewBlocks`

Type: `bool`
Default: `true`

This setting controls whether to lazy load input block HTML for the first new block of a type created after loading an element editor page.

## `newBlockMenuStyle`

Type: `string`
Default: `'classic'`

This setting controls the type of new block buttons/dropdowns that will be used on Neo input fields. The following options are available:

- `'classic'`: buttons in the style of a Matrix field's buttons (prior to Neo 3.6.0 the only style available)
- `'grid'`: a new block grid using block type icons, inspired by [Vizy](https://github.com/verbb/vizy)
- `'list'`: show new block buttons in a permanent dropdown style, that also shows block type icons

## `optimiseSearchIndexing`

Type: `bool`
Default: `true`

This setting controls whether to skip updating search indexes for Neo blocks that have no sub-fields set to use their values as search keywords, or that belong to Neo fields that aren't set to use the field's values as search keywords.

[1]: #newblockmenustyle
[2]: #blocktypeiconselectmode
[3]: #blocktypeiconpath
