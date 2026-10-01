# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WooCommerce payment gateway plugin for Voucherly (Italian meal voucher payments). Supports both classic WooCommerce checkout and modern WooCommerce Blocks checkout.

## Build & Development Commands

```bash
# JavaScript (WooCommerce Blocks frontend)
npm run build          # Production build (resources/js → assets/js)
npm start              # Watch mode for development

# PHP code formatting
composer exec php-cs-fixer fix

# Translations
npm run i18n:build     # Build .pot + JSON translation files

# Packaging
scripts/generate-package-zip.bat   # Create distributable ZIP

# Local test environment (Docker: WordPress + WooCommerce + Plugin Check, plugin mounted live)
npm run env:start        # http://localhost:8888, admin / password
npm run env:tunnel       # Public HTTPS URL (Voucherly rejects non-HTTPS callback URLs); prints URL and a new admin password
npm run env:tunnel-stop
npm run env:debug-log
npm run plugin-check     # Same check WordPress.org runs on updates
npm run wp -- <command>  # Any WP-CLI command
npm run env:destroy      # Delete containers and data
```

## Release

Releases work as in the Voucherly SDKs. `.github/workflows/ci.yml` checks every pull request and every push to `main`: PHP 7.4 and 8.4 lint, php-cs-fixer, and Plugin Check in the Docker environment.

A release is a pushed tag `vX.Y.Z`. Bump the version and add the `= X.Y.Z =` section to `changelog.txt` and `readme.txt` first (see Version Management). `.github/workflows/release.yml` checks that the tag, the plugin header, the readme `Stable tag`, `package.json` and both changelogs agree, runs the CI, publishes to the WordPress.org SVN repository (slug `voucherly`) with `10up/action-wordpress-plugin-deploy`, which syncs `trunk` and creates `tags/X.Y.Z` in one commit, and then creates the GitHub release with the changelog section as notes and the ZIP. Never create a GitHub release by hand: one only exists once the version is on WordPress.org.

A manual run of the release workflow is a dry run by default. Files listed in `.distignore` are not published. Publishing needs the `SVN_USERNAME` and `SVN_PASSWORD` secrets (the SVN password from the WordPress.org profile, not the account password). Banners and icons live only in the SVN `assets/` folder.

`readme.txt` must be written in English; Italian texts come from translate.wordpress.org.

## Architecture

### Entry Point & Initialization

`woocommerce-gateway-voucherly.php` is the plugin entry point. On `plugins_loaded`, it calls `voucherly_init()` which:
1. Loads `voucherly.php` (main gateway class)
2. Registers the gateway with WooCommerce
3. Registers WooCommerce Blocks integration
4. Sets up cron jobs

### Core Classes

- **`voucherly`** (`voucherly.php`) — Extends `WC_Payment_Gateway`. Handles admin settings, payment processing, refunds, webhooks, and classic checkout rendering. This is the main class containing most business logic.
- **`Voucherly_Blocks`** (`includes/blocks/voucherly-blocks.php`) — Extends `AbstractPaymentMethodType`. Registers the payment method for WooCommerce Blocks checkout with tokenization support.

### Payment Flow

1. `process_payment()` creates a `CreatePaymentRequest` via the Voucherly PHP SDK and redirects the customer to Voucherly's hosted checkout
2. On completion, Voucherly redirects back to `gateway_api(?action=redirect)` which updates order status
3. Server-to-server webhook `gateway_api(?action=callback)` finalizes the order independently

### Cron Jobs

- **`voucherly_finalize_orders_event`** (every 4 hours) — Finalizes pending/on-hold orders that may have been paid but not webhook-confirmed
- **`voucherly_update_payment_gateways_event`** (daily) — Fetches available payment methods from Voucherly API for icon display

### Asset Pipeline

JavaScript source lives in `resources/js/frontend/` and is built via webpack (`@wordpress/scripts`) to `assets/js/frontend/blocks.js`. The webpack config in `webpack.config.js` sets custom entry/output paths. CSS is a single static file at `assets/css/voucherly-styles.css`.

### SDK Integration

The plugin depends on `voucherly/voucherly-php-sdk` (^2.0) via Composer. Every call goes through a `VoucherlyApi\VoucherlyClient` instance: `voucherly::getVoucherlyClient()` creates it on first use with the sandbox or live key and the telemetry headers, and every method of the gateway reuses it. The client is created lazily because it rejects an empty key, which is the state of a fresh install. A key typed in the settings is verified with a second client (`isApiKeyValid()`), where an `ApiException` with status 401 means an invalid key.

Key namespaces: `VoucherlyApi\VoucherlyClient`, `VoucherlyApi\Request\*` (request bodies and list parameters), `VoucherlyApi\Model\*` (responses), `VoucherlyApi\Enum\*` (string constants such as `PaymentStatus`, `PaymentMode`, `LineType`), `VoucherlyApi\Exception\*` (`ApiException` for HTTP errors, `ConnectionException` for network errors and timeouts).

Requests are `VoucherlyApi\Request\*` objects that send only the properties assigned to them, so the plugin sets `mode` explicitly. Each line carries a nested `PaymentLineRequestProduct` with its `lineType`: products are `Food` or `NonFood` by category, and the shipping line is `Food` when "Shipping as food" is on, `Shipping` otherwise. Responses are typed `VoucherlyApi\Model\*` objects: `Payment::$metadata` is an array, read as `$payment->metadata['orderId']`. A Payment counts as paid when its status is `PaymentStatus::PAID` or `PaymentStatus::CONFIRMED` (`isPaidOrConfirmed()`).

The `gateways` option stores plain JSON built by the plugin, not SDK objects. `paymentMethods->list()` is paged and returns only the 10 most recent methods by default, so the plugin asks for 100 with `ListCustomerPaymentMethodParams::$length`. The payment methods transient stores the `PaymentMethod` objects returned by the SDK for 60 seconds.

## Version Management

Version must be updated in three places:
1. `woocommerce-gateway-voucherly.php` — plugin header (primary source of truth)
2. `package.json` — `version` field
3. `readme.txt` — `Stable tag`

Changelog is manually maintained in `changelog.txt` and `readme.txt`.

## Code Standards

- PHP: PSR2-based rules via PHP-CS-Fixer (`.php-cs-fixer.php`) and WooCommerce-Core PHPCS rules (`phpcs.xml`)
- No automated test suite is configured
- Text domain for i18n: `voucherly`
