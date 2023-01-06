# Settings

## `collapseAllBlocks`

This setting, which defaults to `false`, controls whether all Neo input blocks should display as collapsed when loading an element editor. When this is enabled, expanding or collapsing previously-existing blocks will not cause their new collapsed state to be saved, however the collapsed state of new blocks will be saved, in case the setting is disabled later.

## `defaultAlwaysShowGroupDropdowns`

This setting, which defaults to `true`, controls the global setting for whether Neo block type groups should always have their dropdowns shown, even when only one block type from a group is available to use. When set to `false`, in such a case, the block type button will be shown instead. This behaviour can also be set on a per-group basis, through the Neo field settings edit page.

## `optimiseSearchIndexing`

This setting, which defaults to `true`, controls whether to skip updating search indexes for Neo blocks that have no sub-fields set to use their values as search keywords, or that belong to Neo fields that aren't set to use the field's values as search keywords.

## `useNewBlockGrid`

This setting, which defaults to `false`, controls whether to use a new block grid on Neo input fields, instead of new block buttons/dropdowns.
