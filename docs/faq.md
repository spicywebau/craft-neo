# FAQ

### Why do asset fields with `{slug}` as an upload location break on Neo blocks?

This is because when parsing the upload location, it applies any `{property}` tags to the fields' containing element. An asset field added to an entry will reference the entry, but an asset field added to a Neo block will reference the block. This is why the instructions change slightly when adding an asset field to a Matrix block - it says to use `{owner.slug}` instead of `{slug}`.

There are two ways around this. The first is to create a duplicate field that uses `{owner.slug}` instead of `{slug}` just for Neo block types.

The second is to use a little bit of Twig logic in your upload location. To begin, `{slug}` can actually be replaced with `{{ object.slug }}`. This shows that we have the ability to use double brace tags, which means we can use logic. So the idea is to check to see if `object` is a Neo block, and if so, use `object.owner.slug` instead. Normally this can't be done, but Neo provides a custom Twig extension that allows you test if some value is a Neo block.

In short, replace `{slug}` with `{{ object is neoblock ? object.owner.slug : object.slug }}`.

If your asset field is on a Matrix or Super Table field which is being used on a Neo block, you would instead need to check whether the owner of the Matrix block or Super Table row is a Neo block: `{{ owner is neoblock ? owner.owner.slug : owner.slug }}`.

--

### How do I get support for Neo?

The primary method of support is to post an issue here on the GitHub repository. Besides that, many other experienced developers are active on [Craft's Discord](https://craftcms.com/discord), as well as the [Stack Exchange](http://craftcms.stackexchange.com/).

--

### I found a bug, what do I do?

That sucks 👎

First, check to make sure the issue hasn't already been documented in the [issues section](https://github.com/spicywebau/craft-neo/issues). If it hasn't, the best course of action is to then open an issue.
