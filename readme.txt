=== Voucherly ===
Contributors: voucherly
Tags: voucherly, meal vouchers, buoni pasto, welfare, payments
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Accept meal voucher payments on your online store with Voucherly. The best way to use meal vouchers!

== Description ==

Voucherly lets you accept digital payments with meal vouchers (buoni pasto), cards and alternative payment methods, affordably and securely. That makes it the best solution for your business!

It is just as easy for your customers:
- Select Voucherly on the checkout page.
- Choose their favourite meal vouchers and decide the amount to pay.
- Pay the remaining balance with other payment methods.
- The order is complete. All that is left is to wait for it to arrive!

A simple process lowers the barriers for customers and reduces abandoned carts at checkout.

To activate the service, complete the onboarding at [https://dashboard.voucherly.it](https://dashboard.voucherly.it). Registration is free, with no activation or cancellation fees.
By using the service you fully and unconditionally accept:
 - [Terms of Service](https://legal.voucherly.it/category/terms-of-service).
 - [Privacy Policy](https://legal.voucherly.it/privacy/privacy-policy).

For more information, visit [https://voucherly.it](https://voucherly.it).

The source code, including the unminified JavaScript and its build tools, is available at [https://github.com/voucherly/voucherly-woocommerce](https://github.com/voucherly/voucherly-woocommerce).

**Ready in minutes**
Create an account, enter your business details, configure your payment methods and start collecting payments right away.

**Grow your sales**
Increase your sales by attracting new customers who want to spend their meal vouchers.

**Everything under control**
Monitor sales, generate reports and manage your operations from a single interface.

== External services ==

This plugin connects to the Voucherly API (https://api.voucherly.it) to process payments. A Voucherly account is required.

* When a customer places an order, the plugin sends the order lines (product names, descriptions, images, prices, taxes), discounts, shipping costs and the customer's name, email, country and billing address to create the payment, then redirects the customer to the Voucherly hosted checkout (https://checkout.voucherly.it).
* When Voucherly confirms a payment, the plugin reads the payment status from the Voucherly API to complete the order.
* Refunds requested from WooCommerce are sent to the Voucherly API.
* For logged-in customers, the plugin reads their saved payment methods from the Voucherly API to show them at checkout.
* Once a day, and when the settings are saved, the plugin reads the list of payment methods enabled on the merchant's Voucherly account to show their icons at checkout.

The service is provided by Voucherly: [Terms of Service](https://legal.voucherly.it/category/terms-of-service), [Privacy Policy](https://legal.voucherly.it/privacy/privacy-policy).

== Changelog ==
= 1.3.0 =
* Requires WordPress 6.5 or later and PHP 7.4 or later, tested up to WordPress 7.1
* Update voucherly/voucherly-php-sdk to 2.0.0
* Fix "Category for food products", which sent every product as non-food
* Fix cancellation of pending orders whose Voucherly payment was cancelled, voided or expired
* Fix fatal error at checkout when a product has no featured image
* Fix fatal error when saving an invalid API key
* Show an error instead of a fatal error when Voucherly cannot be reached from the settings page
* Make the payment callback idempotent when Voucherly delivers the same payment more than once
* Show a card saved during a payment at the next checkout without waiting for the cache to expire
* Hide expired saved cards
* Hide manual and custom payment methods from the checkout icons
* Fix "translation loading triggered too early" notice
* Fix the Voucherly Dashboard link in the configuration notice
* Plugin Check fixes

= 1.2.0 =
* Fix usermeta keys (with migration)
