# Commerce Intelligence for WooCommerce

Sends WooCommerce server events (cart, orders, payments, refunds, logins) to the Commerce Intelligence collector. Wraps the PHP library [`djia/php-analytic-tracker`](https://packagist.org/packages/djia/php-analytic-tracker).

Requires PHP 7.4, WordPress 6.2, WooCommerce 7.0.

## Install

1. In this folder run `composer install --no-dev`. The SDK is installed from Packagist into `vendor/`.
2. Copy the folder `ci-woocommerce` into `wp-content/plugins/` (without `tests/`, `phpunit.xml`) and activate it.
3. Open Settings, Commerce Intelligence and enter the collector URL (default `http://ci-collector:8080`), key id and secret. Until a key id and secret are saved, no event is recorded.

## How it works

- Hooks write events into the `ci_outbox` table. Nothing is sent during the request.
- A cron job on a one minute schedule sends up to 100 events per run. After a request that recorded events, one extra send runs on `shutdown`, after `fastcgi_finish_request()` when it exists.
- The last delivery or storage error is shown read only on the settings page.
- The visitor id comes from the `ci_aid` cookie. At checkout it is saved in order meta `_ci_anonymous_id`, so cancel and refund events done in the admin still carry it.
- `user_id` is the WordPress customer id as a string, omitted when zero.
- Prices are integers: `(int) round($price * 10 ** $decimals)` using `wc_get_price_decimals()`. The plugin differs from the contract below in this and a few other points, listed under "Known deviations" in `.project_docs/events.md`.

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

Copy of the contract in `.project_docs/events.md`, which is canonical. The collector does not check property contents, so this is a contract, not validation.

### Rules for every event

- **Money** is an integer in minor units by the currency's ISO 4217 exponent, not by the shop's display decimals. RSD and EUR have exponent 2, so 125.00 RSD is `12500`, and a shop that displays RSD without decimals still sends `12500`. JPY has exponent 0.
- **`currency`** is an ISO 4217 code, for example `RSD`.
- **Totals** (`revenue`, `payment_confirmed.amount`, `total`) are the grand total the customer pays: products after discounts, plus shipping, including tax.
- **`unit_price`** is the price the customer pays per unit: after discounts, including tax.
- **Ids** (`order_id`, `product_id`, `variation_id`, `refund_id`, `category_id`, `recommendation_id`) are strings, even when the shop uses numbers.
- **`variation_id`** is optional. Omit it when the product has no variation. Never send `0` or `null`.
- **Extra properties** are allowed. The tables list the minimum set; a sender may add its own keys.
- **Required** in the tables means a reader may rely on it. Optional keys are omitted when there is no value, not sent as `null`.

### Item

`order_completed.items`, `cart_viewed.items` and `checkout_started.items` use this shape:

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_id` | string | yes | Product id |
| `variation_id` | string | no | Variation id, omitted when none |
| `quantity` | int | yes | Units |
| `unit_price` | int | yes | Price per unit, minor units, after discounts, including tax |

### Server events

Sent by the shop server through the PHP SDK, never by the browser.

#### order_completed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `order_id` | string | yes | Shop order id |
| `order_number` | string | no | Order number shown to the customer, when it differs from `order_id` |
| `items` | list of Item | yes | One entry per order line |
| `revenue` | int | yes | Grand total: products, shipping and tax |
| `shipping` | int | no | Shipping part of `revenue`, including tax |
| `currency` | string | yes | ISO code |

Send it exactly once per order. The collector deduplicates only by `event_id`, and every `Event::create()` makes a new one, so a second send is stored as a second order. The sender keeps a "tracked" flag on the order and skips the event when it is set.

#### payment_confirmed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `order_id` | string | yes | Shop order id |
| `order_number` | string | no | Order number shown to the customer |
| `amount` | int | yes | Amount paid: the grand total, as in `revenue` |
| `currency` | string | yes | ISO code |
| `method` | string | yes | Payment method id, for example `bacs` |

#### order_cancelled

| Property | Type | Required | Meaning |
|---|---|---|---|
| `order_id` | string | yes | Shop order id |
| `order_number` | string | no | Order number shown to the customer |
| `status` | string | yes | Why the order was cancelled, one value from the list below |
| `reason` | string | no | Free text cancellation note, omitted when none |

`status` values. Every site maps its own statuses to exactly one of these; a value outside the list breaks reports.

| Value | Use when |
|---|---|
| `customer_cancelled` | The customer cancelled or gave up on the order |
| `payment_failed` | The payment was declined or failed |
| `payment_timeout` | The order was not paid in time and was cancelled automatically |
| `out_of_stock` | The shop cancelled because it could not supply the goods |
| `merchant_cancelled` | The shop cancelled for any other reason, for example suspected fraud or an undeliverable address |
| `other` | None of the above fits, or the cause is unknown |

#### order_refunded

| Property | Type | Required | Meaning |
|---|---|---|---|
| `order_id` | string | yes | Shop order id |
| `order_number` | string | no | Order number shown to the customer |
| `refund_id` | string | yes | Refund id |
| `amount` | int | yes | Refunded amount, positive |
| `currency` | string | yes | ISO code |
| `items` | list | no | Refunded lines, omitted for an amount only refund |
| `items[].product_id` | string | yes | Product id |
| `items[].variation_id` | string | no | Variation id, omitted when none |
| `items[].quantity` | int | yes | Refunded units, positive |
| `items[].amount` | int | yes | Refunded amount for the line, positive, including tax |

### Events sent by either side

#### product_added_to_cart

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_id` | string | yes | Product id |
| `variation_id` | string | no | Variation id, omitted when none |
| `quantity` | int | yes | Units added |
| `unit_price` | int | yes | Price per unit, minor units, after discounts, including tax |
| `currency` | string | yes | ISO code |

#### product_removed_from_cart

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_id` | string | yes | Product id |
| `variation_id` | string | no | Variation id, omitted when none |
| `quantity` | int | yes | Units removed |

#### user_identified

No properties. It links `user_id` (the shop customer id) to `anonymous_id` (the `ci_aid` visitor id). In the browser, `djia_ci('identify', userId)` sends it once per visitor and user: calling it on every page sets `user_id` on each event but sends `user_identified` only when the user differs from the last one identified in this browser. On the server, pass both to `Event::create()`. One side is enough; the WooCommerce plugin sends it on login and registration.

### Browser events

Sent by the browser tracker.

#### product_viewed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_id` | string | yes | Product id |
| `category` | string | no | Category id or name of the product |

#### category_viewed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `category_id` | string | yes | Category id |
| `page` | int | no | Listing page number, starting at 1 |

#### product_compared

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_ids` | list of string | yes | Products in the comparison |

#### search_performed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `query` | string | yes | Search text |
| `filters` | object | no | Active filters, filter name to value or list of values |
| `result_count` | int | no | Number of results |

#### filter_changed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `category_id` | string | no | Category being filtered |
| `filters` | object | yes | Filters after the change, filter name to value or list of values |
| `result_count` | int | no | Number of results |

#### cart_viewed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `items` | list of Item | yes | Cart lines |
| `total` | int | yes | Cart total as shown, including tax |
| `currency` | string | yes | ISO code |

#### checkout_started

| Property | Type | Required | Meaning |
|---|---|---|---|
| `items` | list of Item | yes | Cart lines |
| `total` | int | yes | Cart total as shown, including tax |
| `currency` | string | yes | ISO code |

#### recommendation_clicked

| Property | Type | Required | Meaning |
|---|---|---|---|
| `recommendation_id` | string | yes | Id of the recommendation shown |
| `product_id` | string | yes | Product clicked |

#### recommendation_purchased

Not defined yet. The recommendation engine does not exist, so nothing sends it.
## Tests

`composer install` then `vendor/bin/phpunit` (EventMapper only, no WordPress needed).
