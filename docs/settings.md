# Settings

## `collapseAllBlocks`

This setting, which defaults to `false`, controls whether all Neo input blocks should display as collapsed when loading an element editor. When this is enabled, expanding or collapsing previously-existing blocks will not cause their new collapsed state to be saved, however the collapsed state of new blocks will be saved, in case the setting is disabled later.

## `defaultAlwaysShowGroupDropdowns`

This setting, which defaults to `true`, controls the global setting for whether Neo block type groups should always have their dropdowns shown, even when only one block type from a group is available to use. When set to `false`, in such a case, the block type button will be shown instead. This behaviour can also be set on a per-group basis, through the Neo field settings edit page.

## `optimiseSearchIndexing`

This setting, which defaults to `true`, controls whether to skip updating search indexes for Neo blocks that have no sub-fields set to use their values as search keywords, or that belong to Neo fields that aren't set to use the field's values as search keywords.

## `newBlockMenuStyle`

This setting, which defaults to `'classic'`, controls the type of new block buttons/dropdowns that will be used on Neo input fields. The following options ara available:

- `'classic'`: buttons in the style of a Matrix field's buttons (prior to Neo 3.6.0 the only style available)
- `'grid'`: a new block grid using block type icons, inspired by [Vizy](https://github.com/verbb/vizy)
- `'list'`: show new block buttons in a permanent dropdown style, that also shows block type icons

## `blockTypeIconSources`

If `newBlockMenuStyle` is set to something other than `'classic'`, this setting, which defaults to `'*'` (allowing all sources), controls which icon asset sources are allowed to be used for setting block type icons.

## `enableBlockTypeUserPermissions`

Type: `bool`
Default: `true`

This setting controls whether to allow setting user permissions for creating, editing and deleting blocks of a certain type. Note that, if disabled, resaving a user's or user group's permissions will cause any existing block type permissions to be lost.

## `enableLazyLoadingNewBlocks`

Type: `bool`
Default: `true`

This setting controls whether to lazy load input block HTML for the first new block of a type created after loading an element editor page.
