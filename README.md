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

| Hook | Event | Properties |
|---|---|---|
| `woocommerce_add_to_cart` | `product_added_to_cart` | `product_id`, `variation_id`, `quantity`, `unit_price`, `currency` |
| `woocommerce_cart_item_removed` | `product_removed_from_cart` | `product_id`, `variation_id`, `quantity` |
| `woocommerce_checkout_order_processed`, `woocommerce_store_api_checkout_order_processed` | `order_completed` | `order_id`, `items` (`product_id`, `variation_id`, `quantity`, `unit_price`), `revenue`, `currency` |
| `woocommerce_payment_complete` | `payment_confirmed` | `order_id`, `amount`, `currency`, `method` |
| `woocommerce_order_status_cancelled` | `order_cancelled` | `order_id`, `reason` (empty string when none) |
| `woocommerce_order_refunded` | `order_refunded` | `order_id`, `refund_id`, `amount`, `currency`, `items` (`product_id`, `variation_id`, `quantity`, `amount`) |
| `wp_login`, `user_register` | `user_identified` | none |

An order is marked with meta `_ci_order_completed_tracked` so `order_completed` is sent once per order.

## Tests

`composer install` then `vendor/bin/phpunit` (EventMapper only, no WordPress needed).
