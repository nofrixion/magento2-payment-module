# Magento 2 Module for Nofrixion Payments ##

NoFrixion.com online payments for your Magento 2 store.

## System Requirements ##

The NoFrixion payments module is designed to be compatible Magento 2.4.4 onwards (note, support for the 2.4.0-2.4.3 release line [ended on November 28, 2022](https://experienceleague.adobe.com/docs/commerce-operations/release/versions.html?lang=en)).

For the underlying Magento dependencies, please refer to the [Magento system requirements](https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/system-requirements.html).

## Installation, Updates & Removal ##

It is recommended to use the `composer` PHP package manager for production magento deployments. `composer` can be used to install, update and remove the NoFrixion magento 2 module.

### Installation ###

Install the NoFrixion payments module using composer by running the following commands:

```bash
composer require nofrixion/magento2-payments-module
php bin/magento module:enable Nofrixion_Payments
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

\* it is also possible to install the module by downloading the ZIP file from github and extracting it to `{magento-install-directory/app/code/Nofrixion/Payments`. Then run the last four commands in the sequence above to enable the plugin. This method is NOT recommended for production environments.

Note, there are several third-party caching products that may be deployed in your Magento environment and prevent the payments module from appearing in the Magento administration interface. If the Nofrixion Payments module is not visible after following the above steps, most third party caches will be cleared by restarting the apache server.

### Updates ###

If you have installed the payments module using the composer command specified above, you can update the plugin using composer. From a shell session on your magento server, run:

```bash
composer update nofrixion/magento2-payments-module
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

If you are updating a production environment, we recommend [placing the store in maintenance mode](https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/tutorials/maintenance-mode.html) first.

### Removal ###

To remove the payment module:

- Login to the Magento storefront administration panel and:
  - Disable the module in the `Stores -> Configuration -> Sales -> Payment Methods` section.
  - Go to the cache management page and refresh any caches with a status of 'invalidated'.
- Open a shell to your Magento server and:
  - Disable the module at server level by running `php bin/magento module:disable Nofrixion_Payments`.
  - Run `composer remove nofrixion/magento2-payments-module`.
  - Apply database updates by running `php bin/magento setup:upgrade` (for production, also add the parameter `--keep-generated` or you will need to run `php bin/magento setup:di:compile` again.)
- Depending on how caching services are configured on your Magento server it may be necessary to restart the application stack at this point.

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
