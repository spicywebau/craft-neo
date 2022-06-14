# Console Commands

Neo offers console commands for managing Neo blocks, block type groups and fields.

## `neo/block-type-groups/edit`

Edits a Neo block type group.

### Options
- `--group-id` (required): The ID of the block type group to edit.
- `--set-name`: A new name to set for the block type group.
- `--blank-name`: Whether to set a blank name for the block type group.
- `--dropdown [show|hide|global]`: What behaviour should be used for showing the block type group's dropdown.

Please note that at most one of `--set-name` and `--blank-name` can be specified.

## `neo/fields/reapply-propagation-method`

This command reapplies the propagation methods for Neo fields' blocks, optionally on a per-block-structure basis.

### Options
- `--field-id`: The ID(s) of the fields whose blocks should have the propagation method reapplied. If this option is omitted, then all Neo fields will be used.
- `--by-block-structure`: This option is usually unnecessary, but may be required if omitting it causes an error due to a bug in Neo versions prior to 2.8.14. Note that this may create many jobs in the queue.

## `resave/neo-blocks`

This command resaves Neo blocks and has the same options as Craft's in-built [`resave/matrix-blocks`](https://craftcms.com/docs/4.x/console-commands.html#resave-matrix-blocks) command.
