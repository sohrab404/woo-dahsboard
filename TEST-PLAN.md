# Salesbin Test Plan

Run these scenarios before production.

| # | Scenario | Expected |
| --- | --- | --- |
| 1 | WooCommerce inactive | Admin notice + missing screen. No fatal. |
| 2 | Store with zero orders | Empty states, KPIs at 0, growth `—`. |
| 3 | Large order volume | Dashboard loads via cached aggregations; no N+1 loops on KPI. |
| 4 | Guest order | Recent orders and top customers show guest label. |
| 5 | Registered customer | Name grouped by user id. |
| 6 | Refund | Net sales decreases; refund notification if enabled. |
| 7 | Cancelled order | Excluded from default sales KPI; visible if status filter includes it. |
| 8 | Failed order | Same as cancelled for default sales. |
| 9 | Custom order status | Appears in status dropdown dynamically. |
| 10 | Product without stock management | Never listed as low stock. |
| 11 | Low stock | Listed when managing stock and qty ≤ threshold. |
| 12 | HPOS on | Aggregations use `wc_orders` / stats; CRUD list works. |
| 13 | HPOS off | Fallback to posts + postmeta. |
| 14 | Custom date range | Jalali UI converts to Gregorian API dates. |
| 15 | Previous period = 0 | Growth shows «جدید» or `—`, no division error. |
| 16 | Daily goal = 0 | Message, no division by zero; sales still shown. |
| 17 | CSV export | UTF-8 BOM, headers, chunked, formula-safe cells. |
| 18 | User without capability | Menu hidden / REST 403. |
| 19 | REST without nonce | 403 `salesbin_invalid_nonce`. |
| 20 | XSS in product name | Escaped in dashboard HTML. |
| 21 | SQL injection in range/status | Prepared SQL + sanitization; invalid range 400. |
| 22 | Mobile layout | KPI 2-col, cards stack. |
| 23 | RTL | Entire dashboard `dir=rtl`. |
| 24 | Admin bar widget | Today sales/orders update periodically. |
| 25 | Cache invalidation | New order increments `salesbin_cache_version`; new totals appear after TTL/version. |
