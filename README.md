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
- **Product reference.** Every event that names a product sends `product_id` when the sender knows it. When it does not (a shop whose pages do not expose the id), it sends `product_url` instead: the canonical product URL without query string, optionally with `sku`. The consumer resolves `product_url` and `sku` to `product_id` from the catalogue (`product_upserted`).
- **`query_id`** and **`parent_query_id`** are UUID strings that identify one rendering of a results list; see `search_performed`.
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

#### quote_requested

B2B. Sent by the server where a shop takes quote requests.

| Property | Type | Required | Meaning |
|---|---|---|---|
| `quote_id` | string | yes | Shop quote id |
| `items` | list of Item | yes | Requested lines |
| `total` | int | yes | Quoted total, minor units, including tax |
| `currency` | string | yes | ISO code |

#### product_upserted

Server event. The catalogue feed: sent through the PHP SDK when a product is saved and by a full sync command. The consumer keeps a per site map from `url` and `sku` to `product_id` and uses it to fill `product_id` on browser events that only had a URL or SKU.

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_id` | string | yes | Product id |
| `variation_id` | string | no | Variation id, omitted when none |
| `sku` | string | yes | Product SKU |
| `url` | string | yes | Canonical product URL without query string |
| `title` | string | yes | Product title |
| `category_path` | string | yes | Category path, for example `Tools > Drills` |
| `brand` | string | yes | Brand name |
| `price` | int | yes | Current price, minor units, including tax |
| `currency` | string | yes | ISO code |
| `in_stock` | bool | yes | Stock state |

### Events sent by either side

#### product_added_to_cart

| Property | Type | Required | Meaning |
|---|---|---|---|
| `product_id` | string | yes | Product id, or send `product_url` when unknown (see Product reference) |
| `product_url` | string | no | Canonical product URL without query string, when `product_id` is unknown |
| `sku` | string | no | Product SKU |
| `variation_id` | string | no | Variation id, omitted when none |
| `quantity` | int | yes | Units added |
| `unit_price` | int | yes | Price per unit, minor units, after discounts, including tax |
| `currency` | string | yes | ISO code |
| `query_id` | string | no | Search render the product came from; the browser tracker adds it from a click younger than 60 minutes, the sender may override it |
| `list` | string | no | Where the product was found: `search`, `category`, `recommendation`, `assistant`, `direct` |

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
| `product_id` | string | yes | Product id, or send `product_url` when unknown (see Product reference) |
| `product_url` | string | no | Canonical product URL without query string, when `product_id` is unknown |
| `sku` | string | no | Product SKU |
| `category` | string | no | Category id or name of the product |
| `query_id` | string | no | Search render the product came from; the browser tracker adds it from a click younger than 60 minutes, the sender may override it |
| `list` | string | no | Where the product was found: `search`, `category`, `recommendation`, `assistant`, `direct` |
| `in_stock` | bool | no | Stock state when viewed |
| `price` | int | no | Price shown, minor units |

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

Emit it every time a results list is rendered: a new query, and also a filter, sort or page change, an autocomplete pick that lands on results, or an assistant answer that shows products. Each render has its own `query_id`; a refinement points at the render it refines with `parent_query_id`.

| Property | Type | Required | Meaning |
|---|---|---|---|
| `query_id` | string | yes | UUID for this results render, generated by the tracker when absent |
| `parent_query_id` | string | no | Previous render this one refines (filter, sort, page) |
| `query` | string | yes | Text as typed, unchanged |
| `source` | string | yes | `search_box`, `autocomplete`, `assistant`, `quick_order`, `link` |
| `trigger` | string | yes | `submit`, `filter`, `sort`, `page`, `suggestion`, `assistant_turn` |
| `filters` | object | no | Active filters, name to value or list |
| `sort` | string | no | Sort key as the shop names it |
| `page` | int | no | Results page, from 1 |
| `page_size` | int | no | Results per page |
| `result_count` | int | yes | Total results; `0` marks a zero result search |
| `results` | list | yes | Products shown on this page, in order, at most 100 |
| `results[].product_id` | string | yes | Product id, or send `results[].product_url` when unknown (see Product reference) |
| `results[].product_url` | string | no | Canonical product URL without query string, when `product_id` is unknown |
| `results[].sku` | string | no | Product SKU |
| `results[].position` | int | yes | Absolute position across pages, from 1 |
| `results[].in_stock` | bool | no | Stock state when shown |
| `results[].price` | int | no | Price shown, minor units |
| `corrected_query` | string | no | Query the engine actually ran, when it changed it |
| `correction` | string | no | `spelling`, `fuzzy`, `synonym`, `redirect` |
| `redirect_url` | string | no | Where a search redirected instead of listing results |
| `engine` | string | no | `solr`, `woocommerce`, `woodmart_ajax`, `assistant`, ... |
| `conversation_id` | string | no | Assistant conversation, when `source` is `assistant` |
| `turn_id` | string | no | Assistant turn |

#### search_result_clicked

| Property | Type | Required | Meaning |
|---|---|---|---|
| `query_id` | string | yes | Render the click came from |
| `product_id` | string | yes | Product clicked, or send `product_url` when unknown (see Product reference) |
| `product_url` | string | no | Canonical product URL without query string, when `product_id` is unknown |
| `sku` | string | no | Product SKU |
| `position` | int | yes | Absolute position, from 1 |
| `target` | string | no | `title`, `image`, `add_to_cart`, `quick_view` |

#### autocomplete_shown

The tracker sends at most one per 500 ms of typing.

| Property | Type | Required | Meaning |
|---|---|---|---|
| `prefix` | string | yes | Text typed so far |
| `suggestions` | list | yes | In order, at most 20 |
| `suggestions[].type` | string | yes | `query`, `product`, `category`, `brand` |
| `suggestions[].value` | string | yes | Suggestion text, product id or category id |
| `suggestions[].position` | int | yes | From 1 |

#### autocomplete_selected

| Property | Type | Required | Meaning |
|---|---|---|---|
| `prefix` | string | yes | Text typed |
| `type` | string | yes | Suggestion type: `query`, `product`, `category`, `brand` |
| `value` | string | yes | Query text, product id or category id |
| `position` | int | yes | From 1 |

A product picked from autocomplete also counts as a result click: the tracker stores it with a generated `query_id` and `source` `autocomplete`.

#### page_viewed

Sent automatically by the tracker on load.

| Property | Type | Required | Meaning |
|---|---|---|---|
| `page_type` | string | yes | `home`, `search`, `category`, `product`, `cart`, `checkout`, `other`; set by the shop through `init` or `track`, default `other` |
| `title` | string | no | Page title |
| `utm` | object | no | The five standard UTM keys, when present |
| `referrer_type` | string | yes | `direct`, `search_engine`, `social`, `ai_assistant`, `email`, `paid`, `other`; classified in the tracker from a small domain list |

#### page_left

Sent automatically on `pagehide` through `sendBeacon`. The last `page_left` of a session shows where a visitor left.

| Property | Type | Required | Meaning |
|---|---|---|---|
| `engaged_ms` | int | yes | Visible time on the page, milliseconds |
| `max_scroll_pct` | int | yes | Deepest scroll position, 0 to 100 |

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

### Assistant events

Accepted by the collector but not sent in phase 1. They need no collector release when the assistant ships. An assistant answer that shows products sends `search_performed` with `source` `assistant`; a click on one of them sends `search_result_clicked`. Chat text is optional; the collector redacts emails, phone numbers and card numbers before it is published.

Common properties of every assistant event:

| Property | Type | Required | Meaning |
|---|---|---|---|
| `conversation_id` | string | yes | Assistant conversation |
| `turn_id` | string | yes | Assistant turn |
| `assistant_version` | string | yes | Assistant version |

#### assistant_opened

| Property | Type | Required | Meaning |
|---|---|---|---|
| `entry_point` | string | yes | `launcher`, `product_page`, `zero_results` |

#### assistant_message

| Property | Type | Required | Meaning |
|---|---|---|---|
| `role` | string | yes | Author of the message |
| `query_id` | string | no | Results render of the answer, when it showed products |
| `intent` | string | no | Detected intent |
| `constraints` | object | no | Constraints the assistant extracted |
| `outcome` | string | yes | `answered`, `no_results`, `unanswerable`, `error` |
| `unanswerable_reason` | string | no | `not_in_catalog`, `out_of_stock`, `no_knowledge`, `policy` |
| `text` | string | no | Message text, redacted by the collector |
| `latency_ms` | int | no | Time to answer, milliseconds |

#### assistant_feedback

| Property | Type | Required | Meaning |
|---|---|---|---|
| `rating` | string | yes | `up`, `down` |
| `reason` | string | no | Free text or reason code |

#### assistant_handoff

| Property | Type | Required | Meaning |
|---|---|---|---|
| `reason` | string | yes | Why the conversation went to a person |
| `channel` | string | yes | Where it went |

#### assistant_closed

| Property | Type | Required | Meaning |
|---|---|---|---|
| `turns` | int | yes | Number of turns |
| `resolution` | string | yes | `confirmed`, `assumed`, `none` |

## Tests

`composer install` then `vendor/bin/phpunit` (EventMapper only, no WordPress needed).
