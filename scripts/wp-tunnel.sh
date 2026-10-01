#!/usr/bin/env bash
set -euo pipefail

host=''
for _ in $(seq 1 60); do
	host="$(php -r 'echo json_decode((string) @file_get_contents("http://tunnel:2000/quicktunnel"), true)["hostname"] ?? "";')"
	[ -n "$host" ] && break
	sleep 1
done

if [ -z "$host" ]; then
	echo 'Tunnel did not start, check: docker compose logs tunnel' >&2
	exit 1
fi

# The site is publicly reachable while the tunnel is up, so the well-known default password must not stay valid.
password="$(php -r 'echo rtrim(strtr(base64_encode(random_bytes(18)), "+/", "-_"), "=");')"
wp user update admin --user_pass="$password" --skip-email >/dev/null

product_id="$(wp post list --post_type=product --name=pranzo-di-test --format=ids)"
echo
echo "Site:               https://${host}/"
echo "Admin:              https://${host}/wp-admin  (admin / ${password})"
echo "Add to cart:        https://${host}/?add-to-cart=${product_id}"
echo "Checkout blocks:    https://${host}/checkout/"
echo "Checkout classic:   https://${host}/checkout-classico/"
