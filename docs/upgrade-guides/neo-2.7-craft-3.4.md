# Upgrading to Craft 3.4 and 2.7.x+

There are some steps required when updating to Craft 3.4 and Neo 2.7.x+ from older versions. This is because Craft 3.4 re-saves the entries before Neo has a chance to add the database changes which throws an error when saving.

## Steps

1. Edit your composer.json.

    Craft should be `3.4.0` and Neo `2.6.5.1`. Your composer.json for Craft and Neo should look like this:
    ```
    "craftcms/cms": "3.4.0",
    "spicyweb/craft-neo": "2.6.5.1",
    ```

2. Run the update command

    Either `./craft update` or `composer update`

3. Visit the CMS backend and let it run the migration/update

4. Change your composer.json file back or like below:
    ```
    "craftcms/cms": "^3.4.0",
    "spicyweb/craft-neo": "^2.6.5.1",
    ```
   
5. Rerun the update command.
