# Magento 2 Module for Nofrixion Payments ##

NoFrixion.com online payments for your Magento 2 store.

## Installation, Upgrades & Removal ##

It is recommended to use the `composer` PHP package manager for production magento deployments. `composer` can be used to install, update and remove the NoFrixion magento 2 module.

### Installation ###

- Install the files:
  - Using composer (recommended). Run `composer require nofrixion/magento2-payments-module`
  - Using a ZIP file (not recommended). Unzip the ZIP file in `app/code/Nofrixion/Payments`
- Enable the module by running `php bin/magento module:enable Nofrixion_Payments`
- Apply database updates by running `php bin/magento setup:upgrade` (for production, also add the parameter `--keep-generated` or you will need to run `php bin/magento setup:di:compile` again.)
- Flush the cache by running `php bin/magento cache:flush`

### Updates ###

To update the payments module:

- Run `composer update nofrixion/magento2-payments-module` to update to the latest stable release (this assumes installation using the `compsoser require ...` command detailed in the previous section).
- Check the version specific release notes to see if the database needs to be upgraded.
- Flush the cache by running `php bin/magento cache:flush`

### Plugin removal ###

The plugin can be removed by running `composer update nofrixion/magento2-payments-module`.

## Configuration ##

Please find all configuration opens in Magento Admin > Stores > Configuration > Sales > Payment Methods > NoFrixion

- Enter your API key
- Set the mode to production or sandbox
- Change the other settings to your liking

## Troubleshooting ##

If something goes wrong during installation or during deployment, just follow the typical Magento 2 module installation steps. The NoFrixion Payments module follows all Magento 2 standards and should not be any different.

1. Switch or make sure you are in developer mode
2. Remove all temporary files to make sure your latest changes are being applied. This is done by emptying the cache (typically in `MAGENTO_ROOT/var/cache` or your cache server, like Redis) and the files generated in `MAGENTO_ROOT/generated` and `MAGENTO_ROOT/var/view_preprocessed`.
3. Try again
4. If this is a production server, make sure you switch back to production mode
