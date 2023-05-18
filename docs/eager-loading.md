# Eager Loading

As of version 1.4.0, Neo supports [eager-loading](https://docs.craftcms.com/v3/dev/eager-loading-elements.html). However, given the nature of eager-loading, templating an eager-loaded Neo field is quite different to a non-eager-loaded Neo field. This page will attempt to educate you on the differences and provide an example of how to template with it. Keep in mind it expects you have a fairly solid understanding of the Elements system in Craft.

## How it works with Neo

The typical value of a Neo field is something called an [Element Query](https://docs.craftcms.com/v3/dev/element-queries/). At its core, it's simply a wrapper for creating database queries that retrieve elements, or in this case, Neo blocks. You can modify these element queries to more finely select elements &mdash; for example, you can add a level filter to only select Neo blocks from that particular level: `entry.neoField.level(1)`.

An eager-loaded Neo field is no longer an element query. The database query that an element query would make has already been made when eager-loading, with the resulting value of `entry.neoField` being a simple array. This means you can no longer filter your results like above.

## How to deal with this

The answer to this is an unfortunate &ldquo;it depends&rdquo; &mdash; but it mostly depends on whether your Neo field makes use of the child blocks feature. I'll split this into two cases; Neo fields with hierarchy and Neo fields without.

### Without hierarchy

Good news! Chances are you probably don't have to change your code. Though if you are filtering your blocks in some other way, you will have to change your template code a little bit. As an example, you might be filtering your blocks by some block type with the handle `pullQuote`:

```twig
{% for pullQuotes in entry.neoField.type('pullQuote').all() %}
    ...
{% endfor %}
```

For an eager-loaded Neo field, the above can simply be changed to the following:

```twig
{% for pullQuotes in entry.neoField %}
    {% if pullQuotes.type.handle == 'pullQuote' %}
        ...
    {% endif %}
{% endfor %}
```

### With hierarchy

This is where things start to get a little hairy. Using the same approach above to select only the top-level blocks will indeed work:

```twig
{% for block in entry.neoField %}
    {% if block.level == 1 %}
        ...
    {% endif %}
{% endfor %}
```

The problem shows up when you output a block's children &mdash; it'll end up creating another database query, which entirely defeats the purpose of eager-loading:

```twig
{% for block in entry.neoField %}
    {% if block.level == 1 %}
        {% for child in block.children.all() %}
            {# `block.children` is an element query 
               which will cause an unnecessary database call #}
        {% endfor %}
    {% endif %}
{% endfor %}
```

There is a feature in Neo that will allow you to avoid these database calls, and it works with or without eager loading! At the top of your loop, add the following: `{% do block.useMemoized(entry.neoField) %}`

```twig
{% for block in entry.neoField %}
    {% if block.level == 1 %}
        {% do block.useMemoized(entry.neoField) %}
        ...
    {% endif %}
{% endfor %}
```

What this does is it forces Neo blocks to use and query against a &ldquo;local database&rdquo; of blocks instead of going to the database. When eager-loading, all blocks of a Neo field are queried for, so the child blocks for any block will already exist in this eager-loaded array. This means instead of creating a database query to get these blocks, this array can just be scanned through.

Be aware though, every block (include all child blocks) should have the `useMemoized` method called in order to completely avoid all unnecessary database calls. If you split rendering your blocks into separate template files or macros, it'll get complicated as you'll have to manually pass around the `entry.neoField` eager loaded array. Therefore, I recommend doing this: iterate over all your blocks and call the `useMemoized` method _first_:

```twig
{# Preparation for the eager-loaded Neo field #}
{% for block in entry.neoField %}
    {% do block.useMemoized(entry.neoField) %}
{% endfor %}

{# The real-deal #}
{% for block in entry.neoField %}
    {% if block.level == 1 %}
        ...
    {% endif %}
{% endfor %}
```

The `useMemoized` method can be called without passing it an argument, which tells the block to query against an array of blocks without actually giving it the array to use. This is used in cases where the array has already been set. If `useMemoized` is used without the array already being set, the block's `setAllElements` method would need to be called separately, passing it the array. It's definitely recommended to just pass `useMemoized` the array instead, though.

## Eager loading fields inside Neo blocks

From here on out, eager-loading behaves the same way as the Matrix field type. [Refer to the official Craft documentation](https://docs.craftcms.com/v3/dev/eager-loading-elements.html#eager-loading-elements-related-to-matrix-blocks).

### Complete Example

Assuming you are using a Neo field on an Entry template:

1. Eager load the Neo fields (along with any other fields valid for Eager Loading).

```twig
{% do craft.app.elements.eagerLoadElements(
	className(entry),
	[entry],
	[
		'neoField.blockTypeHandle:fieldHandle',
		'neoField.blockTypeHandle:otherFieldHandle.childField',
		'imageFieldHandle'
	]
) %}
```

2. Prepare the Memoized functionality of Neo from those eager loaded fields

```twig
{% for block in entry.neoField %}
	{% do block.useMemoized(entry.neoField) %}
{% endfor %}
```

3. Use the field now everything is properly loaded

```twig
{% for block in entry.neoField %}
	{% if block.level == 1 %}
		{% include '_partials/neoField/' ~ block.type.handle ignore missing %}
	{% endif %}
{% endfor %}
```
