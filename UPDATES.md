### 2026-11-10 23:27
- [Init]: Plugin scaffolded using create-plugin.sh

### 2026-11-10 23:59
- [New Feature]: WooCommerce hooks mapped to Commerce Intelligence events through EventMapper (pure, unit tested).
- [New Feature]: OutboxRepository implements the SDK Outbox over the ci_outbox table, created by CreateOutboxTable.
- [New Feature]: Settings page (Settings, Commerce Intelligence) with collector URL, key id, secret and the last error.
- [New Feature]: FlushOutbox cron on a one minute schedule, plus one flush on shutdown after a request that tracked events.
- [Side Effect]: Uninstall drops ci_outbox and deletes every ci_woocommerce_ option.
