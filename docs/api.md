# API

## Element Criteria Model

Like Matrix field values, Neo field values are [element criteria models](https://craftcms.com/docs/2.x/templating/elementcriteriamodel.html). As Neo fields contain structure, this API contains a bit more than the Matrix field.

### Parameters

| Parameter          | Description                                                                                                                                               |
|--------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------|
| `ancestorOf`       | Only fetch blocks that are an ancestor of a given block. Accepts a `Neo_BlockModel` object.                                                               |
| `ancestorDist`     | Only fetch blocks that are a given number of levels above the block specified by the `ancestorOf` parameter.                                              |
| `level`            | Only fetch blocks located at a certain level.                                                                                                             |
| `descendantOf`     | Only fetch blocks that are an descendant of a given block. Accepts a `Neo_BlockModel` object.                                                             |
| `descendantDist`   | Only fetch blocks that are a given number of levels below the block specified by the `descendantOf` parameter.                                            |
| `id`               | Only fetch the block with the given ID.                                                                                                                   |
| `limit`            | Limits the results to _X_ blocks.                                                                                                                         |
| `nextSiblingOf`    | Only fetch the block which is the next sibling of the given block. Accepts either a `Neo_BlockModel` object or a block's ID.                              |
| `offset`           | Skips the first _X_ blocks.                                                                                                                               |
| `positionedAfter`  | Only fetch blocks which are positioned after the given block. Accepts either a `Neo_BlockModel` object or a block's ID.                                   |
| `positionedBefore` | Only fetch blocks which are positioned before the given block. Accepts either a `Neo_BlockModel` object or a block's ID.                                  |
| `prevSiblingOf`    | Only fetch the block which is the previous sibling of the given block. Accepts either a `Neo_BlockModel` object or a block's ID.                          |
| `relatedTo`        | Only fetch blocks that are related to certain other elements. (See [Relations](https://craftcms.com/docs/2.x/relations.html) for the syntax options.)              |
| `search`           | Only fetch blocks that match a given search query. (See [Searching](https://craftcms.com/docs/2.x/searching.html) for the syntax and available search attributes.) |
| `siblingOf`        | Only fetch blocks which are siblings of the given block. Accepts either a `Neo_BlockModel` object or a block's ID. |
| `status`           | Only fetch blocks with the given status. Possible values are `'enabled'`, `'disabled'`, and `null`. The default value is `'enabled'`. `null` will return all blocks regardless of status. |
| `type`             | Only fetch blocks that belong to a given block type(s), referenced by its handle.                                                                         |
| `typeId`           | Only fetch blocks that belong to a given block type(s), referenced by its ID.                                                                             |

--

## Neo Block

### Properties

| Property         | Description                                                                                                 |
|------------------|-------------------------------------------------------------------------------------------------------------|
| `ancestors`      | Alias of `getAncestors()`.                                                                                  |
| `children`       | Alias of `getChildren()`.                                                                                   |
| `collapsed`      | Whether the block is collapsed in the control panel.                                                        |
| `dateCreated`    | A `DateTime` object of the date the block was created.                                                      |
| `dateUpdated`    | A `DateTime` object of the date the block was last updated.                                                 |
| `descendants`    | Alias of `getDescendants()`.                                                                                |
| `enabled`        | Whether the block is enabled.                                                                               |
| `field`          | Alias of `getField()`.                                                                                      |
| `fieldId`        | The ID of the field that the block belongs to.                                                              |
| `hasDescendants` | Whether the block has any descendants. This will return `true` even if all of the descendants are disabled. |
| `id`             | The block's ID.                                                                                             |
| `level`          | The block's level.                                                                                          |
| `next`           | Alias of `getNext()`.                                                                                       |
| `nextSibling`    | Alias of `getNextSibling()`.                                                                                |
| `owner`          | Alias of `getOwner()`.                                                                                      |
| `ownerId`        | The ID of the element that the block belongs to.                                                            |
| `parent`         | Alias of `getParent()`.                                                                                     |
| `prev`           | Alias of `getPrev()`.                                                                                       |
| `prevSibling`    | Alias of `getPrevSibling()`.                                                                                |
| `siblings`       | Alias of `getSiblings()`.                                                                                   |
| `type`           | Alias of `getType()`.                                                                                       |
| `typeId`         | The ID of the block type that the block belongs to.                                                         |

### Methods

| Method                     | Description                                                                                                                                                                                             |
|----------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `getAncestors(distance)`   | Returns an `ElementCriteriaModel` prepped to return the block's ancestors. You can limit it to only return ancestors that are up to a certain distance away by passing the distance as an argument.     |
| `getChildren()`            | Returns an `ElementCriteriaModel` prepped to return the block's children. (This is an alias for `getDescendants(1)`)                                                                                    |
| `getDescendants(distance)` | Returns an `ElementCriteriaModel` prepped to return the block's descendants. You can limit it to only return descendants that are up to a certain distance away by passing the distance as an argument. |
| `getField()`               | Returns a `FieldModel` object representing the block's field.                                                                                                                                           |
| `getNext(params)`          | Returns the next block that should show up in a list based on the parameters entered. This function accepts either an `ElementCriteriaModel` object, or a parameter array.                              |
| `getNextSibling()`         | Returns the block's next sibling (regardless if it's disabled), if there is one.                                                                                                                        |
| `getOwner()`               | Returns an `ElementModel` object representing the block's owner. This could be an entry, category, or some other element type model.                                                                    |
| `getParent()`              | Returns the block's parent (regardless if it's disabled), if it's not a top-level block.                                                                                                                |
| `getPrev(params)`          | Returns the previous block that should show up in a list based on the parameters entered. This function accepts either an `ElementCriteriaModel` object, or a parameter array.                          |
| `getPrevSibling()`         | Returns the block's previous sibling (regardless if it's disabled), if there is one.                                                                                                                    |
| `getSiblings()`            | Returns an `ElementCriteriaModel` prepped to return the block's siblings.                                                                                                                               |
| `getType()`                | Returns a `Neo_BlockType` object representing the block's type.                                                                                                                                         |
| `hasDescendants()`         | Returns whether the block has any descendants.                                                                                                                                                          |
| `isAncestorOf(block)`      | Returns whether the block is an ancestor of another block.                                                                                                                                              |
| `isChildOf(block)`         | Returns whether the block is a direct child of another block.                                                                                                                                           |
| `isDescendantOf(block)`    | Returns whether the block is a descendant of another block.                                                                                                                                             |
| `isNextSiblingOf(block)`   | Returns whether the block is the next sibling of another block.                                                                                                                                         |
| `isParentOf(block)`        | Returns whether the block is a direct parent of another block.                                                                                                                                          |
| `isPrevSiblingOf(block)`   | Returns whether the block is the previous sibling of another block.                                                                                                                                     |
| `isSiblingOf(block)`       | Returns whether the block is a sibling of another block.                                                                                                                                                |

--

## Neo Block Type

### Properties

| Property | Description                   |
|----------|-------------------------------|
| `handle` | The handle of the block type. |
| `id`     | The ID of the block type.     |
| `name`   | The name of the block type.   |
