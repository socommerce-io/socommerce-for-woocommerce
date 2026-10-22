#!/bin/sh
# Builds the plugin zip the way the release workflow does: the committed tree at HEAD (or the
# ref given), minus what .gitattributes marks export-ignore, under one top-level folder.
set -eu
cd "$(dirname "$0")/.."
ref="${1:-HEAD}"
git archive --format=zip --prefix=socommerce-for-woocommerce/ -o socommerce-for-woocommerce.zip "$ref"
echo "socommerce-for-woocommerce.zip built from $ref"
