=== Webhook Helper ===
Contributors: api2cartdev
Tags: WooCommerce plugin, woocommerce webhooks, woocommerce integration, woocommerce shopping cart updates, api2cart, woocommerce
Requires PHP: 5.6
Requires at least: 4.1
Tested up to: 6.6
Stable tag: 1.7
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin was developed to improve integration with WooCommerce. It adds extra webhook topics.

With this plugin, you can get webhook notifications for shopping cart updates, for example when the item is added or deleted.

WooCommerce shopping cart doesn’t provide the ability to get webhook notifications for basket updates, and API2Cart developed this functionality itself.

WHAT IS API2Cart?

[API2Cart](https://api2cart.com/) is a unified API to integrate with 40+ shopping carts and marketplaces including Magento, Shopify, BigCommerce, WooCommerce, PrestaShop, Demandware, Amazon and others.
We are also constantly expanding our list of platforms to meet the needs of our customers.

== Experience the API2Cart Advantage ==
* **Seamless Integration:** Integrate seamlessly with WooCommerce and eliminate the hassle of managing multiple platforms.
* **Real-time Notifications:** Receive instant notifications for cart updates, including item additions and deletions.
* **Unified API:** Manage 40+ shopping carts and marketplaces with a single API.
* **Comprehensive Data Management:** Easily retrieve, add, delete, update, and synchronize store data such as orders, customers, products, baskets, and categories from all or any of the supported platforms. See [all methods and platforms](https://api2cart.com/supported-api-methods/) we support.
* **Developer-Friendly Tools:** Utilize our [SDK](https://api2cart.com/docs/sdk/) and [detailed documentation](https://api2cart.com/docs/) to connect multiple shopping carts and marketplaces with ease.

Take Your eCommerce Business to New Heights
If you have any questions, feel free to contact us at [manager@api2cart.com](mailto:manager@api2cart.com) or [submit the form](https://api2cart.com/contact-us/).

== Installation ==

1. Upload the `a2c-webhook-helper` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does this plugin require the Woocommerce plugin? =

Yes, the Woocommerce plugin must be installed and active.

== Screenshots ==

1. "Basket Updated" is a new webhook topic.

== Changelog ==
= 1.7 =
* Added webhooks 'Category Created, Category Updated, Category Deleted'

= 1.6.6 =
* Fix bugs

= 1.6.5 =
* Added customer IP address output for 'basket.add' and 'basket.update' webhooks.

= 1.6.4 =
* Add support webHooks from APP request

= 1.6.3 =
* Fix bug with multi-store

= 1.6.3 =
* Fix bug with multi-store

= 1.6.2 =
* Fix order.shipment webhook events

= 1.6.1 =
* Fix plugin compatibility

= 1.6 =
* Added support for integration with Woocommerce via REST API
* Added new REST API endpoints:
  * GET wc-a2c/v1/basket/{basket_id} - retrieve basket by ID,
  * PUT wc-a2c/v1/basket/{basket_id} - update basket,
  * GET wc-a2c/v1/customer/{customer_id}/basket - retrieve customer basket,
  * POST wc-a2c/v1/customer/{customer_id}/basket - create customer basket,
  * GET wc-a2c/v1/customer/{customer_id}/session - retrieve customer session data,
  * GET wc-a2c/v1/abandoned-order - get abandoned carts

= 1.5 =
* Added webhooks 'Variant Created, Variant Updated, Variant Deleted'

= 1.4 =
* Added webhooks 'Shipment Tracking Create' and 'Shipment Tracking Delete'

= 1.3 =
* Fix an issue with the code that set an empty value to a variable. This caused to PHP Warning on PHP 8

= 1.2 =
* Code refactored, added 'Basket Create' webhook, support guest baskets, added basket id into the order meta

= 1.1.0 =
* Added new Webhook topic 'Basket Deleted' and basket creation time

= 1.0.1 =
* Added plugin version tracking via Wordpress option

= 1.0 =
* First official release

== Upgrade Notice ==

= 1.0 =
* First version
