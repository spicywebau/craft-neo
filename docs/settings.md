# Settings

## `collapseAllBlocks`

This setting, which defaults to `false`, controls whether all Neo input blocks should display as collapsed when loading an element editor. When this is enabled, expanding or collapsing previously-existing blocks will not cause their new collapsed state to be saved, however the collapsed state of new blocks will be saved, in case the setting is disabled later.

## `optimiseSearchIndexing`

This setting, which defaults to `true`, controls whether to skip updating search indexes for Neo blocks that have no sub-fields set to use their values as search keywords, or that belong to Neo fields that aren't set to use the field's values as search keywords.
