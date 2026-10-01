#!/usr/bin/env bash
# "npm run env:start" runs this after every start, so each step must be safe to repeat.
set -euo pipefail

# The WordPress container copies core files into the shared volume on first boot.
until [ -f wp-config.php ] && wp db check --quiet >/dev/null 2>&1; do
	sleep 2
done

if ! wp core is-installed; then
	wp core install --url=http://localhost:8888 --title='Voucherly Test' \
		--admin_user=admin --admin_password=password --admin_email=admin@example.com --skip-email
fi

for plugin in woocommerce plugin-check; do
	wp plugin is-installed "$plugin" || wp plugin install "$plugin"
done

# The plugin is mounted under the wordpress.org slug so text domain and Plugin Check behave as in production.
wp plugin activate woocommerce plugin-check voucherly

wp language core install it_IT --activate || true
wp language plugin install --all it_IT || true

wp rewrite structure '/%postname%/'
wp option update blogname 'Voucherly Test'
wp option update woocommerce_default_country 'IT:MI'
wp option update woocommerce_currency 'EUR'
wp option update woocommerce_currency_pos 'right_space'
wp option update woocommerce_price_decimal_sep ','
wp option update woocommerce_price_thousand_sep '.'
wp option update woocommerce_coming_soon 'no'
wp option update woocommerce_allow_tracking 'no'
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp transient delete _wc_activation_redirect || true
wp wc tool run install_pages --user=admin

if [ -z "$(wp post list --post_type=product --name=pranzo-di-test --format=ids)" ]; then
	wp wc product create --user=admin --name='Pranzo di test' --slug=pranzo-di-test --regular_price=12.50 --status=publish
fi

if [ -z "$(wp post list --post_type=page --name=checkout-classico --format=ids)" ]; then
	wp post create --post_type=page --post_status=publish --post_title='Checkout classico' --post_name=checkout-classico \
		--post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
fi

product_id="$(wp post list --post_type=product --name=pranzo-di-test --format=ids)"
echo
echo "Admin:              http://localhost:8888/wp-admin  (admin / password)"
echo "Voucherly settings: http://localhost:8888/wp-admin/admin.php?page=wc-settings&tab=checkout&section=voucherly"
echo "Add to cart:        http://localhost:8888/?add-to-cart=${product_id}"
echo "Checkout blocks:    http://localhost:8888/checkout/"
echo "Checkout classic:   http://localhost:8888/checkout-classico/"
