# Configuration

## Creating the configuration file

There are a couple of advanced configuration settings you can change, which you can see below. To change any of these settings, you need to create the file `neo.php` under the `craft/config` directory. Inside that file, paste the following code:

```php
<?php

return [
    // Settings go here
];
```

At the moment, this doesn't do anything, but it's set up now to start making configuration changes.

Alternatively, you can copy and paste the contents of the `neo/config.php` file into `neo.php`. This will have all the settings written out for you already. If you're new to programming or PHP it's best to look at this file.


## Settings

| Setting                    | Default | Description                                                                                            |
|----------------------------|---------|--------------------------------------------------------------------------------------------------------|
| `saveModifiedBlocksOnly`   | `true`  | Optimizes the saving of Neo fields by only saving existing blocks that have had their content modified |
| `generateKeywordsWithTask` | `true`  | Optimizes the saving of elements with Neo fields by offloading generating search keywords to a task    |
