# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Poisq.com is a Russian-language service directory / marketplace — users register services (businesses, freelancers), and visitors search for them by country, city, and keyword. The stack is vanilla PHP + MySQL + Meilisearch, no framework, no build tool.

## Development Commands

There is no build system. PHP files are served directly by Apache. Common operations:

```bash
# Reindex all services into Meilisearch (run from browser or CLI)
php /panel-5588/reindex.php

# Run cron jobs manually (requires ?secret=poisq_cron_2025 if via HTTP, or direct CLI)
php cron/notify-new-services.php
php cron/notify-views-digest.php

# View Apache/PHP errors (domain-specific log)
tail -f /var/log/apache2/domains/poisq.com.error.log
```

## Architecture

### Request Flow & URL Routing

`.htaccess` rewrites clean URLs before PHP sees them:
- `/fr/` → `results.php?country=fr`
- `/fr/paris/` → `results.php?country=fr&city_slug=paris`
- `/fr/paris/врач` → `results.php?country=fr&city_slug=paris&q=врач`
- `/service/{id}-{slug}` → `service.php?id=...`
- `/article/{country}/{slug}` → `article.php?country=...&slug=...`

`results.php` also handles legacy `?country=&city_id=&q=` query strings and 301-redirects them to clean URLs.

### Search: Meilisearch + MySQL Fallback

`/config/meilisearch.php` wraps a local Meilisearch instance at `http://127.0.0.1:7700` (index: `services`). Every search in `results.php` tries Meilisearch first; on any exception it falls back to MySQL `LIKE` queries. Autocomplete lives in `/api/suggest.php` with the same fallback pattern.

When services are created/updated/deleted, they must be synced to Meilisearch — this happens via the admin panel reindex or via individual calls to helpers in `meilisearch.php`.

### Database Access

`/config/database.php` returns a PDO singleton via `getDbConnection()`. All queries use prepared statements. The config file is gitignored — it lives only on the server.

Key tables: `users`, `services`, `cities`, `verification_codes`, `favorites`, `page_views`, `search_logs`, `settings`.

`services.status` values: `draft`, `pending`, `approved`, `rejected`.

JSON columns on `services`: `photo` (array of paths or single string), `hours`, `languages`, `services` (list), `social`.

`services.phone` stores the full international number including dial code (e.g. `+3364548583`). `add-service.php` and `edit-service.php` combine the `phone_country` POST field (dial code) with `phone` (bare number) at save time. Old records may lack the country code prefix.

### Authentication

- **Users**: Session-based (`$_SESSION['user_id']`, `user_name`, `user_avatar`). Email verified via 6-digit code (15-min TTL in `verification_codes`). Passwords via `password_hash()`/`password_verify()`.
- **Admin panel** (`/panel-5588/`): Double-layered — HTTP Basic Auth (`.htpasswd`) + its own session (`$_SESSION['admin_logged_in']`).

### Geolocation

- Homepage (`index.php`): calls `ipwhois.app` (no key), caches result in `/tmp/` for 24h.
- API endpoint `get-user-country.php`: calls `freeipapi.com` (60 req/min limit).
- Both extract the real IP from `X-Forwarded-For` / `X-Real-IP` proxy headers.

### Email

`/config/email.php` provides `sendVerificationEmail()`, `sendStatusEmail()` (service approved/rejected), and `sendAdminModerationEmail()` (notifies `support@poisq.com` when a service is submitted for moderation). Uses PHPMailer via SMTP on `mail.poisq.com:465` SSL. Config is gitignored.

### API Endpoints (`/api/`)

| File | Purpose |
|---|---|
| `suggest.php` | Autocomplete (Meilisearch → MySQL fallback) |
| `get-cities.php` | Cities by country code |
| `get-user-country.php` | Geo-detect visitor |
| `service-actions.php` | Toggle visibility, submit/recall moderation, delete |
| `favorites.php` | Save / unsave services |
| `update-avatar.php` | Upload user avatar |
| `update-profile.php` | Update user name/city/notification prefs |
| `get-articles.php` | List articles |
| `get-services.php` | Recent services |

### Admin Panel (`/panel-5588/`)

Handles: service moderation (approve/reject with comment), user management (block/unblock), city CRUD, Meilisearch reindex, analytics (page views, search logs), slot management.

### Cron Jobs

`/cron/notify-new-services.php` — daily digest to users subscribed to new services in their city.
`/cron/notify-views-digest.php` — weekly (Monday) and monthly (1st) view stats to service owners.

Both guard against web execution with `if (php_sapi_name() !== 'cli' && ($_GET['secret'] ?? '') !== 'poisq_cron_2025')`.

## Gitignored Sensitive Files

These files exist on the server but are NOT in the repo:
- `config/database.php`
- `config/email.php`
- `.htpasswd`
- `uploads/` (user-uploaded photos)
- `*.bak`, `*.backup`, `*.save` files

## Slug / URL Helpers

`/config/helpers.php`:
- `serviceUrl($service)` — builds `/service/{id}-{slug}` from the service name, transliterating Cyrillic to Latin.
- `articleUrl($article)` — builds `/article/{country}/{slug}`.
- `articleSlug($title)` — generates URL-safe slug from title.
