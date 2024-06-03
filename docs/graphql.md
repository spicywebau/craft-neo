# GraphQL

Neo fields, and content within them, can be accessed and manipulated using Craft's GraphQL API.

## Fragment syntax
`{neo field handle}_{neo block type handle}_BlockType`

## How to use

In the following example we'll be using a Neo field with the handle `body`.

### Single level queries

Example on how to query the text block type that has a text field within:
```
body {
    ... on body_text_BlockType {
        # text field
        text
    } 
}
```

Returned Data example:
```
"body": [
    {
        "text": "Test content"
    }
]
```

---

Another example with assets:
```
body {
    ... on body_media_BlockType {
        # asset field
        media {
            url
        }
    } 
}
```

Returned Data example:
```
"body": [
    {
        "media": [
            {
                "url": "assets/media/image.png"
            }
        ]
    }
]
```

------

### Multi level queries

Multi level block types will have a field called `children`. These will return the children blocks within the parent block type.

#### Example:
```
body {
    ... on body_text_BlockType {
        text
    }
    ... on body_myContent_BlockType {
        children {
            ... on body_text_BlockType {
                # text field
                text
            }

            ... on body_media_BlockType {
                # asset field
                media {
                    url
                }
            }
        }
    }
}
```

#### Returned data example:

```
"body": [
    {
        "text": test
    },
    {
        "children": [
            {
              "text": "test"
            },
            {
                "media": [
                    {
                      "url": "assets/media/image1.png"
                    }
                ]
            }
        ]
    }
]
```

---

#### Multiple levels example:
```
body {
    ... on body_myContent_BlockType {
        children {
            ... on body_text_BlockType {
                # text field
                text
            }

            ... on body_innerContent_BlockType {
                children: {
                    text
                }
            }

            ... on body_media_BlockType {
                # asset field
                media {
                    url
                }
            }
        }
    }
}
```

#### Returned data example:

```
"body": [
    {
        "children": [
            {
              "text": "test"
            },
            {
                "children": [
                    {
                      "text": "test test"
                    },
                    {
                      "text": "test test test"
                    }
                ]
            },
            {
                "media": [
                    {
                      "url": "assets/media/image1.png"
                    }
                ]
            }
        ]
    }
]
```

### Returning all blocks

By default, a Neo field query will return only the blocks at the top level of the field. Other levels can be targeted using the `level` argument, and setting `level` to `0` will return all blocks. Blocks' levels can be returned using the `level` field.

#### Returning all blocks example:

```
body(level: 0) {
    ... on body_text_BlockType {
        level
        text
    }
}
```

#### Returned data example:

```
"body": [
    {
        "level": 1,
        "text": "Parent text block"
    },
    {
        "level": 2,
        "text": "Child text block"
    },
    {
        "level": 1,
        "text": "Another top-level text block"
    }
]
```

----

## Mutations

Mutating Neo fields is largely the same process as with [Matrix fields in Craft 4](https://craftcms.com/docs/4.x/graphql.html#matrix-fields-in-mutations), but with two differences:

- Neo-related input types have Neo in their name, rather than Matrix; e.g. the input type for a Neo field with the handle `yourNeoField` is `yourNeoField_NeoInput`.
- Neo block data can include a `level` argument for setting the block's level. If `level` isn't specified for new blocks, it will default to 1.
