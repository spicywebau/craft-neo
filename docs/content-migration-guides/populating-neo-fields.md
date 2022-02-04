# Populating Neo Fields

Setting or adding to a Neo field's content takes the following format. This example assumes a Neo field with the handle `neoField`, with two block types with handles `blockType1` and `blockType2`, with a subfield with the handle `plainTextField`.

```php
$entry = \Craft::$app->entries->getEntryById($entryId);

$entry->setFieldValues([
    'neoField' => [
        'blocks' => [
            'new1' => [
                'type' => 'blockType1',
                'level' => 1,
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'plainTextField' => 'This is a new block.',
                ],
            ],
            'new2' => [
                'type' => 'blockType2',
                'level' => 2,
                'enabled' => true,
                'collapsed' => false,
                'fields' => [
                    'plainTextField' => 'This is another new block.',
                ],
            ],
        ],
        'sortOrder' => [
            'new1',
            'new2',
        ],
    ],
]);

\Craft::$app->elements->saveElement($entry);
```

For new blocks, the following properties are optional:

- `level` (defaults to 1)
- `enabled` (defaults to `true`)
- `collapsed` (defaults to `false`)

For existing blocks, any properties not specified will just retain their existing values.

When adding new blocks to a Neo field that already has blocks, or editing existing blocks, you only need to set the new blocks and any edited blocks (using the block IDs as keys instead of `new1`, `new2`, etc.) in the `blocks` array. However, you must include all of the field's block IDs in the `sortOrder` array. The `sortOrder` array exists not only to put the blocks in the correct order, but also to tell Neo which existing, unedited blocks should still exist after saving the field value. Thus, any existing, unedited blocks in the field being updated that don't have their IDs included in the `sortOrder` array will be deleted.
