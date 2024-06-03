# Templating

## Examples

### Basic

```twig
<ol>
    {% for block in entry.neoField.level(1).all() %}
        {% switch block.type.handle %}
            {% case 'someBlockType' %}
                <li>
                    {{ block.someField }}
                    {% if block.children.all() is not empty %}
                        ...
                    {% endif %}
                </li>
            {% case ...
        {% endswitch %}
    {% endfor %}
</ol>
```

This is typically the most you'd need to know. Similar to how Matrix fields work, but with a notable difference. For Neo fields that have child blocks, you will first need to filter for blocks on the first level. It's essentially the same API as the [`craft.entries()`](https://craftcms.com/docs/5.x/reference/element-types/entries.html#querying-entries) element query.

### Recursive

```twig
<ol>
    {% nav block in entry.neoField.all() %}
        <li>
            {{ block.someField }}
            {% ifchildren %}
                <ol>
                    {% children %}
                </ol>
            {% endifchildren %}
        </li>
    {% endnav %}
</ol>
```

Because Neo blocks have a `level` attribute, Neo fields are compatible with the [`{% nav %}`](https://craftcms.com/docs/5.x/reference/twig/tags.html#nav) tag.

### `craft.neo.blocks()`

If you need to get Neo blocks in your template in a way that isn't connected to a Neo field value on a specific Craft element, you can use `craft.neo.blocks()`. This returns a [Neo block query](api.md#element-query) which can then be used in the same way as a typical [Craft element query](https://craftcms.com/docs/5.x/development/element-queries.html).

### More information

For a more in-depth breakdown of templating for Neo, [see this issue](https://github.com/spicywebau/craft-neo/issues/34).
