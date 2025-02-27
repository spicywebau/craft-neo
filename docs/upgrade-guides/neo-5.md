# Upgrading to Neo 5

In some cases, content can disappear from Neo blocks when upgrading to Craft 5, due to a field layout element UUID mismatch issue that can occur for older block types. To avoid this issue, the general process you should follow when using the [official Craft 5 upgrade guide](https://craftcms.com/docs/5.x/upgrade.html) should be:

- Ensure you are running a version of Neo that provides the `php craft neo/block-types/resave` command
    - If you have Neo 3, this is at least version 3.10.0
    - If you have Neo 4, this is at least version 4.4.0
- Follow the guide up to the step before the first deployment of project config changes to your live environment
- Run `php craft neo/block-types/resave`
- Deploy your project config changes and follow the rest of the guide

If you still experience content loss after following the above, please add a comment to [Neo issue #943 on GitHub](https://github.com/spicywebau/craft-neo/issues/943).
