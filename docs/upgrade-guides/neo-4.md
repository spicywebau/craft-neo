# Upgrading to Neo 4

Most changes and removal of classes or methods in Neo 4 should only affect internal functionality. There are a handful of changes to be aware of if you are a developer of a plugin or module that extends Neo functionality.

- `benf\neo\elements\Block::getOwner()` will now return `null` if the block's owner no longer exists, instead of throwing `yii\base\InvalidConfigException`. While the return type of this method has always allowed the possibility for `null` to be returned, in practice, until Neo 4, it has either returned a Craft element or thrown an exception. If you have code that at some point calls this method in a context where its owner might have been deleted, you will need to handle the `null` case.
- `benf\neo\assets\FieldAsset`, which was deprecated in Neo 3.0.0, has been removed. If you have code that uses `EVENT_FILTER_BLOCK_TYPES`, replace `FieldAsset` with `InputAsset`.
- `benf\neo\assets\SettingsAsset::EVENT_SET_CONDITION_ELEMENT_TYPES`, which was deprecated in Neo 3.6.0, has been removed. Code using this will need to use `benf\neo\services\BlockTypes::EVENT_SET_CONDITION_ELEMENT_TYPES` instead.
