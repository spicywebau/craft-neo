# FAQ

### Neo won't install – what gives?

Make sure your environment meet the [requirements](installation.md#requirements) for the plugin. Neo requires Craft 2.6 or greater and PHP 5.4 or greater.

> But Craft supports PHP 5.3! Why doesn't Neo?

Yes that's true, and the honest answer is that 5.3 lacked some features that I wanted to take advantage of when building the plugin. And given that 5.3 is [no longer supported](https://en.wikipedia.org/wiki/PHP#Release_history), I didn't want Neo to be held back by a severely out-of-date language.

--

### Why do asset fields with `{slug}` as an upload location break on Neo blocks?

This is because when parsing the upload location, it applies any `{property}` tags to the fields' containing element. An asset field added to an entry will reference the entry, but an asset field added to a Neo block will reference the block. This is why the instructions change slightly when adding an asset field to a Matrix block - it says to use `{owner.slug}` instead of `{slug}`.

There are two ways around this. The first is to create a duplicate field that uses `{owner.slug}` instead of `{slug}` just for Neo block types.

The second is to use a little bit of Twig logic in your upload location. To begin, `{slug}` can actually be replaced with `{{ object.slug }}`. This shows that we have the ability to use double brace tags, which means we can use logic. So the idea is to check to see if `object` is a Neo block, and if so, use `object.owner.slug` instead. Normally this can't be done, but Neo provides a custom Twig extension that allows you test if some value is a Neo block.

In short, replace `{slug}` with `{{ object is neoblock ? object.owner.slug : object.slug }}`.

--

### Why should I trust a third-party plugin to handle my content?

I understand relying too heavily on third-party plugins is not ideal. Neo has the potential to be a major component of Craft sites, which understandably is a cause for concern. I realise my assurances that this plugin will remain supported long-term mean little, so the best I can offer is a graceful degradation process should you choose to uninstall the plugin.

As of 1.4.0, the plugin supports [automatic conversion of Neo fields to Matrix](https://github.com/spicywebau/craft-neo/issues/17). Hopefully this will ease any concern you might have using this plugin.

As a side note, if you think this plugin deserves to be in core, [vote for the feature request](https://github.com/craftcms/cms/issues/814). I have my own reservations about this which you can read following the link, but it should be the community and ultimately Pixel & Tonic's decision.

--

### I found a bug, what do I do?

That sucks 👎

First, check to make sure the issue hasn't already been documented in the [issues section](https://github.com/spicywebau/craft-neo/issues). If it hasn't, the best course of action is to then open an issue.
