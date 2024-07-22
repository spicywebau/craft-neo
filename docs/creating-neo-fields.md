# Creating Neo Fields

## Configuration Sidebar

The sidebar of the configuration section of a Neo field settings page lists the block types and block type groups that exist for the field, and has buttons for creating block types and groups. If a block type group is created, all block types down until another group will belong to that group. If you want to simply close a group without creating a new one, you can create a group without a name.

## Block Types

### Settings tab

#### Entry type setting

Optionally set an entry type to associate with this block type, so that this block. If this is set, the name, handle and color settings fields, and the field layout tab, will be hidden, and the block type will use the entry type's settings for these.

#### Name setting

How the block type will be labeled throughout the control panel.

If an entry type is set for this block type, the name field will be hidden.

#### Handle setting

How the block type will be referenced from your templates.

If an entry type is set for this block type, the handle field will be hidden.

#### Description setting

Set the description to use as title text for this block type in the Neo field's new block menus.

#### Icon setting

Set an icon that blocks of this type will have on an element editor page. This icon will display on the top bar of blocks, and on the 'list' and 'grid' styles of [new block menu](settings.md#newblockmenustyle).

Neo does _not_ use the icon of any associated entry type for this block type.

#### Color setting

Set the color that blocks of this type will have on an element editor page.

If an entry type is set for this block type, the color field will be hidden.

#### Enabled setting

Whether to allow or disallow use of this block type. Disabling a block type can be useful in cases where its existence is not yet handled in your templates.

#### Min/max blocks of type setting

Use these fields to limit the number of blocks of this in a field. Note that the max blocks setting applies regardless of whether the block is a child of another. You cannot use this setting to restrict the number of child blocks in a block.

#### Min/max sibling blocks of type setting

Use these fields to limit the number of blocks of this type that can exist either as child blocks of a parent block, or at the top level of the Neo field.

#### Allowed child block types setting

Here you can define what blocks of certain types can be added as children. If any child blocks are set, it will add a new block menu at the bottom of each block. This setting will allow you to nest the same block type recursively.

#### Min/max child blocks setting

If any allowed child block types have been set, use these fields to limit the number of child blocks that blocks of this type can have.

#### Group child block types setting

If any allowed child block types have been set, use this field to set whether or not to show child block types in their groups in the new block menu.

#### Top-level only setting

This setting will determine if blocks of this type will only be allowed as children to another block. If this setting is disabled, then the button for this block type will be hidden on the input – except when it's inside another block.

#### Ignore permissions setting

Whether any user permissions set for this block type should be ignored. This is set by default, because all user permissions for newly-created block types are unchecked by default. Make sure to unset this if you want to make use of user permissions for block types.

#### Condition settings

Set conditions for where this block type can be used, depending on properties of the owner element. Neo includes support for the following owner element types:

- Entries
- Categories
- Assets
- Users
- Tags
- Addresses
- Global sets
- Craft Commerce products
- Craft Commerce variants
- Craft Commerce orders
- Craft Commerce subscriptions

Plugins and modules can listen for an [event to register more owner element types](events.md#setconditionelementtypesevent).

### Field layout tab

Design the field layout for each block type using the familiar field layout designer. One small thing to look out for is asset fields that use `{property}` tags in their directory settings. [Read more about it here.](faq.md#why-do-asset-fields-with-slug-as-an-upload-location-break-on-neo-blocks)

If an entry type is set for this block type, the field layout tab will be hidden.

### Screenshot

<img src="/docs/assets/neo-block-type-settings.png" width="882">
