# Graphql

As of version 2.5.0, Neo supports GraphQL that's now a feature within Craft CMS.

## Fragment syntax
`{neo field handle}_{neo block type handle}_BlockType`

## How it use

In the following example we'll be using a neo field with the handle `body`.

### Single Level Queries

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

### Multi level Queries

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

#### Returned Data example:

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

----

## Mutations

As of Neo 2.9, Neo supports GraphQL mutations which were introduced in Craft 3.5.  Mutating Neo fields is largely the same process as with [Matrix fields](https://craftcms.com/docs/3.x/graphql.html#matrix-fields-in-mutations), but with two differences:

- Neo-related input types have Neo in their name, rather than Matrix; e.g. the input type for a Neo field with the handle `yourNeoField` is `yourNeoField_NeoInput`.
- Neo block data can include a `level` field for setting the block's level.  If `level` isn't specified, it will default to 1.
