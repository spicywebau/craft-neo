# Console Commands

Neo offers console commands for managing Neo blocks, block types, block type groups and fields.

## `neo/block-type-groups/delete`

Deletes a Neo block type group.

### Options
- `--group-id` (required): The ID of the block type group to delete.
- `--delete-block-types`: Delete block types belonging to the block type group. If omitted, the block types will be reassigned to the previous group in the field (if any).

## `neo/block-type-groups/edit`

Edits a Neo block type group.

### Options
- `--group-id` (required): The ID of the block type group to edit.
- `--set-name`: A new name to set for the block type group.
- `--blank-name`: Set a blank name for the block type group.
- `--dropdown`: What behaviour should be used for showing the block type group's dropdown — either 'show', 'hide', or 'global'.

Please note that at most one of `--set-name` and `--blank-name` can be specified.

## `neo/block-types/delete`

Deletes a Neo block type.

### Options
- `--type-id`: The ID of the block type to delete.
- `--handle`: The handle of the block type to delete.
- `--field-id`: The field ID of the block type to delete.

One of `--type-id` and `--handle` must be specified, and if `--handle` is specified and the specified handle is used on more than one Neo field, `--field-id` must also be specified.

## `neo/block-types/edit`

Edits a Neo block type.

### Options
- `--type-id`: The ID of the block type to delete.
- `--handle`: The handle of the block type to delete.
- `--field-id`: The field ID of the block type to delete.
- `--set-name`: A new name to set for the block type.
- `--set-handle`: A new handle to set for the block type.
- `--set-description`: A new description to set for the block type.
- `--unset-description`: Whether to remove the block type's description.
- `--set-min-blocks`: A new min blocks value to set for the block type. Set this to 0 to remove the limit.
- `--set-max-blocks`: A new max blocks value to set for the block type. Set this to 0 to remove the limit.
- `--set-max-sibling-blocks`: A new max sibling blocks value to set for the block type. Set this to 0 to remove the limit.
- `--set-min-child-blocks`: A new min child blocks value to set for the block type. Set this to 0 to remove the limit.
- `--set-max-child-blocks`: A new max child blocks value to set for the block type. Set this to 0 to remove the limit.
- `--set-child-blocks`: The child block types of this block type, either as comma-separated block type handles, or the string '*' representing all of the Neo field's block types.
- `--unset-child-blocks`: Whether to set this block type as having no child block types.
- `--set-top-level`: Whether to set the block type as being allowed at the top level.
- `--unset-top-level`: Whether to set the block type as not being allowed at the top level.

One of `--type-id` and `--handle` must be specified, and if `--handle` is specified and the specified handle is used on more than one Neo field, `--field-id` must also be specified. Furthermore, at most one of `--set-description` and `--unset-description` may be used, at most one of `--set-top-level` and `--unset-top-level` may be used, and at most one of `--set-child-blocks` and `--unset-child-blocks` may be used.

## `neo/fields/reapply-propagation-method`

This command reapplies the propagation methods for Neo fields' blocks, optionally on a per-block-structure basis.

### Options
- `--field-id`: The ID(s) of the fields whose blocks should have the propagation method reapplied. If this option is omitted, then all Neo fields will be used.
- `--by-block-structure`: This option is usually unnecessary, but may be required if omitting it causes an error due to a bug in Neo versions prior to 2.8.14. Note that this may create many jobs in the queue.
- `--with-propagation-method`: A comma-separated list of propagation methods that Neo fields need to have, in order to have their propagation methods reapplied - accepted options are `none`, `siteGroup`, `language`, `custom`, and `all`. If not specified, it will default to fields with any propagation method except "Save blocks to all sites the owner element is saved in".

## `resave/neo-blocks`

This command resaves Neo blocks and has the same options as Craft's in-built [`resave/matrix-blocks`](https://craftcms.com/docs/4.x/console-commands.html#resave-matrix-blocks) command.
