#!/bin/sh
# Runs a command in wp-env's tests-cli image, from the plugin's directory, in a fresh container.
#
# `wp-env run` executes inside the long-lived container, and on Docker Desktop for macOS that
# container's nested bind mounts (the plugins under /var/www/html) go stale whenever the host
# directory tree is rewritten under them, so the plugin shows up empty. A `docker compose run`
# container mounts everything afresh and sees the plugin as it is now, on macOS and on Linux.
set -eu
cd "$(dirname "$0")/.."
compose_file="$(npx wp-env install-path)/docker-compose.yml"
exec docker compose -f "$compose_file" run --rm --no-deps -T \
	-w /var/www/html/wp-content/plugins/socommerce-for-woocommerce \
	--user "$(id -u):$(id -g)" \
	tests-cli "$@"
