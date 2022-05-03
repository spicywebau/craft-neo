# Creating Neo fields

<img src="https://raw.githubusercontent.com/spicywebau/craft-neo/1.x/demo/creating-field.png" width="790">

#### 1. Field layout tab
Design the field layout for each block type using the familiar field layout designer. One small thing to look out for is asset fields that use `{property}` tags in their directory settings. [Read more about it here.](faq.md#why-do-asset-fields-with-slug-as-an-upload-location-break-on-neo-blocks)

--

#### 2. Max blocks of type setting
Use this field to limit the number of blocks of a certain type in a field. Note that the max blocks setting applies regardless of whether the block is a child of another. You cannot use this setting to restrict the number of child blocks in a block.

--

#### 3. Allowed child block types setting
Here you can define what blocks of certain types can be added as children. If there are any child blocks set, it will add a row of buttons at the bottom of each block. This setting will allow you to nest the same block type recursively.

--

#### 4. Top-level only setting
This setting will determine if blocks of this type will only be allowed as children to another block. If this setting is disabled, then the button for this block type will be hidden on the input – except when it's inside another block.

--

#### 5. Block type group
This is an example of a block type group. All block types down until another group will belong to this group. If you want to simply close a group without creating a new one, you can create a group without a name.

--

#### 6. Parent/child block type example
This is an example of how you might use the child blocks and top level settings – a slideshow. The `Slideshow` block type allows the `Slide` block type as a child. This `Slide` block type has its "top-level only" setting disabled, indicated by the graphical indentation. On the input, the `Slide` block type button will not be seen except for inside a `Slideshow` block.

--

#### 7. Block type and group buttons
The buttons for creating block types and groups.
