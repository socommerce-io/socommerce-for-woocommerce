#!/bin/sh
# Refuses a release tag (vX.Y.Z) that is not the version the plugin says it is, in all three
# places: the plugin header, the version constant and readme.txt's Stable tag.
set -eu
cd "$(dirname "$0")/.."
[ $# -eq 1 ] || { echo "Usage: bin/check-version.sh vX.Y.Z"; exit 1; }
version="${1#v}"
grep -q "^ \* Version: *${version}$" socommerce-for-woocommerce.php || { echo "Plugin header is not ${version}"; exit 1; }
grep -q "^define( 'SOCOMMERCE_FOR_WOOCOMMERCE_VERSION', '${version}' );$" socommerce-for-woocommerce.php || { echo "Version constant is not ${version}"; exit 1; }
grep -q "^Stable tag: ${version}$" readme.txt || { echo "readme.txt stable tag is not ${version}"; exit 1; }
echo "The plugin is ${version} in all three places"
