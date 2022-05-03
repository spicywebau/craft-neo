# Templating

## Examples

### Basic

```twig
<ol>
    {% for block in entry.neoField.level(1) %}
        {% switch block.type.handle %}
            {% case 'someBlockType' %}
                <li>
                    {{ block.someField }}
                    {% if block.children is not empty %}
                        ...
                    {% endif %}
                </li>
            {% case ...
        {% endswitch %}
    {% endfor %}
</ol>
```

This is typically the most you'd need to know. Similar to how Matrix fields work, but with a notable difference. For Neo fields that have child blocks, you will first need to filter for blocks on the first level. It's essentially the same API as the [`craft.entries`](https://craftcms.com/docs/2.x/templating/craft.entries.html) element criteria model.

### Recursive

```twig
<ol>
    {% nav block in entry.neoField %}
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

Because Neo blocks have a `level` attribute, Neo fields are compatible with the [`{% nav %}`](https://craftcms.com/docs/2.x/templating/nav.html) tag.

### More information

For a more in-depth breakdown of templating for Neo, [see this issue](https://github.com/spicywebau/craft-neo/issues/34).
