# Commerce Intelligence for WooCommerce

Sends WooCommerce server events (cart, orders, payments, refunds, logins) to the Commerce Intelligence collector. Wraps the PHP library in `app/php-sdk`.

Requires PHP 7.4, WordPress 6.2, WooCommerce 7.0.

## Install

1. In this folder run `composer install --no-dev`. The SDK is copied from `../../php-sdk` (no symlink) into `vendor/`.
2. Copy the folder `ci-woocommerce` into `wp-content/plugins/` (without `tests/`, `phpunit.xml`) and activate it.
3. Open Settings, Commerce Intelligence and enter the collector URL (default `http://ci-collector:8080`), key id and secret. Until a key id and secret are saved, no event is recorded.

## How it works

- Hooks write events into the `ci_outbox` table. Nothing is sent during the request.
- A cron job on a one minute schedule sends up to 100 events per run. After a request that recorded events, one extra send runs on `shutdown`, after `fastcgi_finish_request()` when it exists.
- The last delivery or storage error is shown read only on the settings page.
- The visitor id comes from the `ci_aid` cookie. At checkout it is saved in order meta `_ci_anonymous_id`, so cancel and refund events done in the admin still carry it.
- `user_id` is the WordPress customer id as a string, omitted when zero.
- Prices are integers in minor units: `(int) round($price * 10 ** $decimals)` using `wc_get_price_decimals()`.

## Hooks and events

| Hook | Event |
|---|---|
| `woocommerce_add_to_cart` | `product_added_to_cart` |
| `woocommerce_cart_item_removed` | `product_removed_from_cart` |
| `woocommerce_checkout_order_processed`, `woocommerce_store_api_checkout_order_processed` | `order_completed` |
| `woocommerce_payment_complete` | `payment_confirmed` |
| `woocommerce_order_status_cancelled` | `order_cancelled` |
| `woocommerce_order_refunded` | `order_refunded` |
| `wp_login`, `user_register` | `user_identified` |

An order is marked with meta `_ci_order_completed_tracked` so `order_completed` is sent once per order.

## Event properties

The collector does not check property contents. The tables below are the convention the WooCommerce plugin and the browser tracker follow; nothing enforces them. Money is an integer in minor units, `round(price * 10 ** decimals)`, so 125.00 RSD is `12500`. `currency` is an ISO 4217 code such as `RSD`.

### order_completed

| Property | Type | Meaning |
|---|---|---|
| `order_id` | int | Shop order id |
| `items` | list | One entry per order line |
| `items[].product_id` | int | Product id |
| `items[].variation_id` | int | Variation id. The plugin sends `0` for a product without variations |
| `items[].quantity` | int | Units on the line |
| `items[].unit_price` | int | Line total divided by `max(1, quantity)`: discounted, excluding tax |
| `revenue` | int | Order total |
| `currency` | string | ISO code |

Send it once per order. The plugin guards this with order meta `_ci_order_completed_tracked`.

### payment_confirmed

| Property | Type | Meaning |
|---|---|---|
| `order_id` | int | Shop order id |
| `amount` | int | Amount paid, the plugin sends the order total |
| `currency` | string | ISO code |
| `method` | string | Payment method id (`$order->get_payment_method()`), for example `bacs` |

### order_cancelled

| Property | Type | Meaning |
|---|---|---|
| `order_id` | int | Shop order id |
| `reason` | string | Cancellation note from the status transition, empty string when none |

### order_refunded

| Property | Type | Meaning |
|---|---|---|
| `order_id` | int | Shop order id |
| `refund_id` | int | Refund id |
| `amount` | int | Refunded amount, positive |
| `currency` | string | ISO code |
| `items` | list | Refunded lines, empty for an amount only refund |
| `items[].product_id` | int | Product id |
| `items[].variation_id` | int | Variation id, `0` when none |
| `items[].quantity` | int | Refunded units, positive |
| `items[].amount` | int | Refunded amount for the line, positive |

### product_added_to_cart

| Property | Type | Meaning |
|---|---|---|
| `product_id` | int | Product id |
| `variation_id` | int | Variation id, `0` when none |
| `quantity` | int | Units added |
| `unit_price` | int | Price per unit |
| `currency` | string | ISO code |

### product_removed_from_cart

| Property | Type | Meaning |
|---|---|---|
| `product_id` | int | Product id |
| `variation_id` | int | Variation id, `0` when none |
| `quantity` | int | Units removed |

### user_identified

No properties. Pass the customer id as `$userId` and the visitor id as `$anonymousId`, which links the two. The plugin sends it on login and registration.

### Browser events

These are normally sent by the browser tracker. A server may send them too. Their types are not fixed anywhere: the tracker README gives only the names, and its examples use a string `product_id` (`'12345'`) where the plugin sends an int.

| Event | Properties |
|---|---|
| `product_viewed` | `product_id`, `category` |
| `category_viewed` | `category_id`, `page` |
| `product_compared` | `product_ids` |
| `search_performed` | `query`, `filters`, `result_count` |
| `filter_changed` | `category_id`, `filters`, `result_count` |
| `cart_viewed` | `items`, `total`, `currency` |
| `checkout_started` | `items`, `total`, `currency` |
| `recommendation_clicked` | `recommendation_id`, `product_id` |
| `recommendation_purchased` | Not defined yet |

## Tests

`composer install` then `vendor/bin/phpunit` (EventMapper only, no WordPress needed).
