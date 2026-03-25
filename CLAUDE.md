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
scripts/svn.sh                     # Deploy to WordPress plugin SVN
```

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

The plugin depends on `voucherly/voucherly-php-sdk` (^1.2) via Composer. Key namespaces: `VoucherlyApi\Api`, `VoucherlyApi\Payment\*`, `VoucherlyApi\Customer\Customer`, `VoucherlyApi\PaymentGateway\PaymentGateway`.

## Version Management

Version must be updated in two places:
1. `woocommerce-gateway-voucherly.php` — plugin header (primary source of truth)
2. `package.json` — `version` field

Changelog is manually maintained in `changelog.txt` and `readme.txt`.

## Code Standards

- PHP: PSR2-based rules via PHP-CS-Fixer (`.php-cs-fixer.php`) and WooCommerce-Core PHPCS rules (`phpcs.xml`)
- No automated test suite is configured
- Text domain for i18n: `voucherly`
