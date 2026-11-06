#!/bin/sh
# Copies the JSON Schema of Pingpong's protocol from a checkout of folkloreinc/pingpong, and records
# its commit. Usage: tests/protocol/update.sh <pingpong checkout>
set -eu

checkout="${1:?Usage: tests/protocol/update.sh <pingpong checkout>}"
directory="$(cd "$(dirname "$0")" && pwd)"

cp "$checkout/packages/protocol/schema/pingpong.schema.json" "$directory/pingpong.schema.json"
git -C "$checkout" rev-parse HEAD > "$directory/VERSION"
echo "Copied the protocol at $(cat "$directory/VERSION")."
