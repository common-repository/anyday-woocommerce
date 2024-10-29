=== Anyday WooCommerce ===
Contributors: anyday2020
Tags: Payments, Instalments, WooCommerce, Payment Gateway, Buy Now Pay Later, BNPL, Conversion Rate, Basket Size, Anyday
Requires at least: 4.3.1
Tested up to: 6.0.3
Stable tag: 1.7.8
License: MIT
License URI: https://opensource.org/licenses/MIT

Anyday is a new way to pay. An interest-free financing solution with no fees or interest for your customers.

== Description ==

Anyday is a fair and transparent installment payment method you can add to your online store. It is interest-free and without any unexpected expenses and unpleasant surprises.

The plugin allows your customers to split their payments into 4 equal installments. The first installment is always paid at checkout and the remaining installments are paid on the last banking day of the following three months.

Anyday is always completely free of interest and your customers pay no additional fees as long as their installments are paid on time. In short: Anyday is both fair and transparent.

To sign up for Anyday customers go through a quick but thorough credit evaluation and upon being accepted they will be granted credit. The credit with Anyday is revolving, which allows customers to utilize their available credit multiple times after paying off installments. Regardless of how many times a customer chooses to utilize their credit the terms remain the same - no fees, no interest.

When you sign an agreement and implement Anyday in your online store you will be added to Anyday’s shop collection. Furthermore, Anyday will upon request provide you with marketing material, feature you in their consumer newsletters, and link to your store via social media. All are completely free of charge.

Anyday assumes the credit risk when a customer chooses to pay with Anyday so you can focus on what you do best - running your business. Orders will be paid out to you in full on a weekly basis.

It is in everyone’s best interest that customers pay installments on time. Therefore, Anyday makes sure to inform customers of when they need to pay and how much they need to pay.

Anyday is fully compliant with Danish legislation.

= How to Get Started =
1. Sign up for [Anyday](https://www.anyday.io/webshop)
2. Install the plugin.
3. Configure the Anyday WooCommerce plugin

== Frequently Asked Questions ==

= What does it cost to get Anyday? =

Nothing. There is no sign up fee with Anyday and no monthly subscription fee. You pay a fixed transaction fee of 3.95 %, and this is your only expense.

Additionally, Anyday will happily help install their plugin on your site. Free of charge. Feel free to contact onboarding@anyday.io with any questions you may have.

= When will I receive the money for an order? =

You will be paid weekly. Orders captured Monday through Sunday are paid out in full the following Thursday.

= What happens if my customer does not pay their installments? =

Anyday assumes all credit risk as soon as a purchase is made. You will receive all of your money minus the transaction fee on the specified payout date.

== Changelog ==

= 1.0 =

#### 🚀 Enhancements
- Releasing first version on AnyDay WooCommerce plugin.

= 1.1 =

#### 🚀 Enhancements
- Updating plugin assets.

= 1.2 =

#### 🚀 Enhancements
- Adding caching functionality to store external JS to the Wordpress server.

= 1.3 =

#### 🚀 Enhancements
- Minor version fixing.

= 1.4 =

#### 👾 Bug Fixes
- Fixing JS caching bug which happens after deactivation of plugin.

= 1.5 =

#### 🚀 Enhancements
- Updating plugin to accept purchases up to 30k of order amount.
- Updated translations
- Refresh the cached anyday js script on the upgrade of the plugin.

= 1.6 =

#### 🚀 Enhancements
- Updating plugin description appearing in plugin directory.

= 1.7 =

#### 🚀 Enhancements
- Disabling SSL verification while fetching public script from the server.

= 1.7.1 =

#### 🚀 Enhancements
- Updating not to fetch scripts on update immediatly on plugin upgrade.

= 1.7.2 =

#### 🚀 Enhancements
- Changed JS file cached with CURL.

= 1.7.3 =

#### 🚀 Enhancements
- Adding bulk order status update in backend.
- Anyday payment is enabled for orders with payments greater than and equals to 300 DKK.
- Stock levels should update only when order is completed or processing. Stock added back on order cancelled and refunded.
- Fixing number format throughout the plugin.
- Adding validation to check number format while input for capture/refund from the backend.

#### 👾 Bug Fixes
- Fixing cached JS URL for some instances using permalinks.
- Fixed backend configuration page which was broken.
- Minimum price limit for pricetag isn't working for on-sale product.

= 1.7.4 =

#### 👾 Bug Fixes
- Fixed miscalculations on order details page, after payment capture/refunds.
- Fixed decimal point bug which captures incorrect amount due to wrong decimal value conversion. Now users would be able to enter an integer e.g. 1500 or 250, or may enter decimals e.g. 1.500,00 or 250,00. If entered a decimal in the thousandths place, you must use two decimals. e.g. 1.500,00

= 1.7.5 =

#### 🚀 Enhancements
- Implemented callback feature to receive transactions processed by Order API.

#### 👾 Bug Fixes
- IEX - WooCommerce API issue fixed which used to return blank response from WooCommerce setting API.
- Added support to Sequential order plugin which takes order id from sequence created from plugin.

= 1.7.6 =

#### 🚀 Enhancements
- Added new column to display Anyday OrderID and Anyday Order status in WooCommerce Order list page.
- Order status changes in WooCommerce will capture/refund/cancel payments.

#### 👾 Bug Fixes
- Adjustment order notes, transaction history to make it consistency with action from button.
- Anyday WooCommerce plugin spamming wpdebug logs after activation.

= 1.7.7 =

#### 🚀 Enhancements
- Improved UX in Merchant authentication using Anyday credentials.

#### 👾 Bug Fixes
- Fixing error message displaying in Anyday column if meta values are null.

= 1.7.8 =

#### 👾 Bug Fixes
- Fixing callback which updates order as cancelled after charge expired.
