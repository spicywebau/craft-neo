# Updating, Duplicating and Creating Block Types

Updating an existing block type is, in most cases, relatively straightforward.  Because Neo's overall handling of saving block types is designed around the information provided by a field settings page, though, adding new block types to a field -- while certainly still possible -- is much trickier, as there are more moving parts to consider, such as the sort order and block type groups (block type groups actually work based on the sort order of block types, so they need to be updated as well).

Block types have the following properties:
- `id`
- `fieldId`
- `fieldLayoutId`
- `name`
- `handle`
- `maxBlocks`
- `maxChildBlocks`
- `childBlocks` (an array of handles)
- `topLevel`
- `sortOrder`
- `uid`

## Updating a block type

Let's say that you have an existing block type with a handle of `text`, which is attached to a field with ID 1, and you want to set another existing block type with the handle `media` as a child block.  You could achieve this with the following:

```php
// Check the DB and then set the block type model
$result = (new \craft\db\Query())
    ->select([
        'id',
        'fieldId',
        'fieldLayoutId',
        'name',
        'handle',
        'maxBlocks',
        'maxChildBlocks',
        'childBlocks',
        'topLevel',
        'sortOrder',
        'uid',
    ])
    ->from(['{{%neoblocktypes}}'])
    ->where([
        'fieldId' => 1,
        'handle' => 'text',
    ])
    ->one();

$blockType = new \benf\neo\models\BlockType($result);

// Ensure `childBlocks` is actually an array before updating it; if it has no child blocks, it may be an empty string
// If you're not updating that property, don't worry about it
if (empty($blockType->childBlocks)) {
    $blockType->childBlocks = [];
}

if (!in_array('media', $blockType->childBlocks)) {
    $blockType->childBlocks[] = 'media';
}

return \benf\neo\Plugin::$plugin->blockTypes->save($blockType);
```

Any other block type property could be updated in this way -- a block type could even be duplicated to another field by unsetting the ID/UID and updating the field ID (and then duplicating the field layout unless you want them to share one, but that would probably cause issues at some point).

## Duplicating a block type

Duplicating a block type to another field could be achieved by doing something like this after getting the block type model:

```php
$newField = \Craft::$app->getFields()->getFieldById($targetFieldId);

if ($newField) {
    $fieldLayout = $blockType->getFieldLayout();

    if ($fieldLayout) {
        $oldTabs = $fieldLayout->getTabs();
        $newUid = \craft\helpers\StringHelper::UUID();

        $newFieldLayout = clone $fieldLayout;
        $newFieldLayout->id = null;
        $newFieldLayout->uid = $newUid;
        \Craft::$app->getFields()->saveLayout($newFieldLayout);

        // Get the new field layout ID
        $layoutResult = (new \craft\db\Query())
            ->select([
                'id',
                'type',
                'uid'
            ])
            ->from('{{%fieldlayouts}}')
            ->where(['uid' => $newUid])
            ->one();
        $newFieldLayout = new \craft\models\FieldLayout($layoutResult);

        $newTabs = [];

        foreach ($oldTabs as $tab) {
            $tab->id = null;
            $tab->uid = null;
            $tab->layoutId = $newFieldLayout->id;
            $newTabs[] = $tab;
        }

        $newFieldLayout->setTabs($newTabs);

        $blockType->setFieldLayout($newFieldLayout);
        $blockType->fieldLayoutId = $newFieldLayout->id;
    }

    $blockType->id = null;
    $blockType->uid = null;
    $blockType->fieldId = $newField->id;
    $blockType->sortOrder = count($newField->getBlockTypes()) + 1;

    return \benf\neo\Plugin::$plugin->blockTypes->save($blockType);
}
```

## Creating a block type

As mentioned previously, creating and saving a new block type -- whether it's a top-level or child block -- can get a bit more complicated.  You could place it at the end of the field's block types and that would remove some of the complication, but if you're creating child block types, you probably want them placed right after their associated top-level block in the overall block type order.  If you do that, the sort order property for all subsequent block types will need to be updated, so you'll end up having to re-save many (if not all) of your existing block types.  Thus, the first step for this process is to get an array of all of the field's block types.

Now, the tricky situation with this is that the block type groups are positioned based on the same sort order, so order positions are unique across block types and groups.  If your field has no block type groups, then you could loop over the block types array after you've inserted your new block types where they need to be, and set their sort order values accordingly.  In all likelihood, though, unless this is a pretty basic Neo field, you'll probably be using block type groups, so you'd need to implement a solution that would update both block types and groups.

Example code follows, in which one new block type is created and saved, and the other block types and groups are updated accordingly.  Note that this example will skip the field layout creation -- Neo uses regular Craft field layouts, the same that you'd apply to a section, category group, etc. so you should refer to the Craft documentation for more details on that.

```php
// This example assumes the Neo field to be updated has ID 1; update yours as necessary
$fieldId = 1;
$blockTypes = \benf\neo\Plugin::$plugin->blockTypes->getByFieldId($fieldId);
$blockTypeGroups = \benf\neo\Plugin::$plugin->blockTypes->getGroupsByFieldId($fieldId);

// Create the block type and set basic info
$newBlockType = new \benf\neo\models\BlockType();
$newBlockType->fieldId = $fieldId;
$newBlockType->name = 'New Block Type';
$newBlockType->handle = \craft\helpers\StringHelper::toCamelCase($newBlockType->name);

// If this is a child block type, disallow it at the top level
$newBlockType->topLevel = false;

// Leave `id` and `uid` as null, but set any other properties that are needed
// If setting a field layout on the block type, do the following:
// $newBlockType->setFieldLayout($fieldLayout);
// $newBlockType->fieldLayoutId = $fieldLayout->id;

// For the purposes of this example, we will make this a child of the block type at array position 3
$parentIndex = 3;
$childBlocks = $blockTypes[$parentIndex]->childBlocks;

// Ensure `$childBlocks` is actually an array before updating it;
// if it has no child blocks, it may be an empty string
if (empty($childBlocks)) {
    $childBlocks = [];
}

// No need to check if a new block type is already a child before adding it
$childBlocks[] = $newBlockType->handle;

$blockTypes[$parentIndex]->childBlocks = $childBlocks;

// Insert the new block type into the block types array after the parent block
array_splice($blockTypes, $parentIndex + 1, 0, [$newBlockType]);

// Update the sort orders for everything
// This example assumes that the first item in the sort order is a block type group
// If that is not the case, set `$sortOrder` to 1
$sortOrder = 2;
$newBlockTypeCount = 0;

foreach ($blockTypes as $blockType) {
    $blockType->sortOrder = $sortOrder++;

    // Keep track of the new block types so we can find and update block type groups
    if ($blockType->id === null) {
        $newBlockTypeCount++;
    }

    // Check for a block type group with the current `$sortOrder - $newBlockTypeCount`
    foreach ($blockTypeGroups as $group) {
        if ($group->sortOrder == $sortOrder - $newBlockTypeCount) {
            $group->sortOrder = $sortOrder++;
            break;
        }
    }
}

// When saving a Neo field's settings, Neo actually deletes the old block type groups
// and saves new ones, so that needs to be done here, too
\benf\neo\Plugin::$plugin->blockTypes->deleteGroupsByFieldId($fieldId);

// Save everything
foreach ($blockTypes as $blockType) {
    \benf\neo\Plugin::$plugin->blockTypes->save($blockType);
}

foreach ($blockTypeGroups as $group) {
    \benf\neo\Plugin::$plugin->blockTypes->saveGroup($group);
}

return true;
```
