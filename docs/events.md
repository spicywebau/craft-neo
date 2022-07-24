# Events

## BlockTypeEvent

A `BlockTypeEvent` is triggered before and after a block type is saved.

### Example

```php
use benf\neo\events\BlockTypeEvent;
use benf\neo\services\BlockTypes;
use yii\base\Event;

Event::on(BlockTypes::class, BlockTypes::EVENT_BEFORE_SAVE_BLOCK_TYPE, function (BlockTypeEvent $event) {
    // Your code here...
});

Event::on(BlockTypes::class, BlockTypes::EVENT_AFTER_SAVE_BLOCK_TYPE, function (BlockTypeEvent $event) {
    // Your code here...
});
```

## FilterBlockTypesEvent

A `FilterBlockTypesEvent` is triggered for a Neo field when loading a Craft element editor page that includes that field. It allows for filtering which block types or block type groups belonging to that field are allowed to be used, depending on the element being edited.

Note that, if Neo blocks already exist in a context where their block type is filtered out, the blocks won't be rendered on the element editor page, and changes to the block structure will result in the filtered-out block(s) being deleted.

### Example

This example removes the ability to use a block type with the handle `quote`, and a block type group with the name `Structure`, from a `contentBlocks` Neo field when loading an entry from the `blog` section.

```php
use benf\neo\assets\InputAsset;
use benf\neo\events\FilterBlockTypesEvent;
use craft\elements\Entry;
use yii\base\Event;

Event::on(InputAsset::class, InputAsset::EVENT_FILTER_BLOCK_TYPES, function (FilterBlockTypesEvent $event) {
    $element = $event->element;
    $field = $event->field;

    if ($element instanceof Entry && $element->section->handle === 'blog' && $field->handle === 'contentBlocks') {
        $filteredBlockTypes = [];
        foreach ($event->blockTypes as $type) {
            if ($type->handle !== 'quote') {
                $filteredBlockTypes[] = $type;
            }
        }

        $filteredGroups = [];
        foreach ($event->blockTypeGroups as $group) {
            if ($group->name !== 'Structure') {
                $filteredGroups[] = $group;
            }
        }

        $event->blockTypes = $filteredBlockTypes;
        $event->blockTypeGroups = $filteredGroups;
    }
});
```

## SetConditionElementTypesEvent

A `SetConditionElementTypesEvent` is triggered when loading a Neo field's settings page. It allows other plugins to register element types that will have condition fields added to each block type's settings, to then allow users to control the conditions elements of that type must meet for that block to be allowed to be used.

### Example

```php
use benf\neo\assets\SettingsAsset;
use benf\neo\events\SetConditionElementTypesEvent;
use yii\base\Event;

Event::on(
    SettingsAsset::class,
    SettingsAsset::EVENT_SET_CONDITION_ELEMENT_TYPES,
    function (SetConditionElementTypesEvent $event) {
        $event->elementTypes[] = \some\added\ElementType::class;
    }
);
```
