# Console Commands

Neo offers two console commands for managing Neo blocks and fields.

## `neo/fields/reapply-propagation-method`

This command reapplies the propagation methods for Neo fields' blocks, optionally on a per-block-structure basis.

### Options
- `--field-id`: The ID(s) of the fields whose blocks should have the propagation method reapplied. If this option is omitted, then all Neo fields will be used.
- `--by-block-structure`: This option is usually unnecessary, but may be required if omitting it causes an error due to a bug in Neo versions prior to 2.8.14. Note that this may create many jobs in the queue.

## `resave/neo-blocks`

This command resaves Neo blocks and has the same options as Craft's in-built [`resave/matrix-blocks`](https://craftcms.com/docs/3.x/console-commands.html#resave-matrix-blocks) command.
