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

## Issues

If you're seeing errors like `Schema must contain unique named types but contains multiple types named...`. Check the schema by adding the below to the query.

```
__schema {
    types {
        name
        description
    }
}
```

For some reason it'll fix the error above. If it doesn't then submit an issue and we'll have a look at it further.