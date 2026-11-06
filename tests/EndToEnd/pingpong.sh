#!/bin/sh
# Starts a Pingpong server from a built checkout of folkloreinc/pingpong, with SQLite and a project,
# and prints the variables of the end-to-end tests. Stops it with `down`.
#
#   export $(PINGPONG_CHECKOUT=../pingpong tests/EndToEnd/pingpong.sh up)
#   vendor/bin/phpunit --testsuite EndToEnd
#   tests/EndToEnd/pingpong.sh down
set -eu

state="${TMPDIR:-/tmp}/laravel-pingpong-e2e"
port="${PINGPONG_E2E_PORT:-3901}"

case "${1:-}" in
    up)
        checkout="$(cd "${PINGPONG_CHECKOUT:?Set PINGPONG_CHECKOUT to a built checkout of folkloreinc/pingpong}" && pwd)"
        cli="$checkout/packages/server/dist/cli/main.js"
        rm -rf "$state" && mkdir -p "$state"
        export PINGPONG_DATABASE_URL="sqlite://$state/pingpong.db"
        export PINGPONG_SECRET_KEY=0000000000000000000000000000000000000000000000000000000000000000
        export PINGPONG_HOST=127.0.0.1 PINGPONG_PORT="$port" PINGPONG_LOG_LEVEL=warn
        node "$cli" migrate > /dev/null
        node "$cli" projects create e2e > "$state/project.txt"
        nohup node "$cli" serve > "$state/server.log" 2>&1 &
        echo $! > "$state/pid"
        for attempt in $(seq 1 50); do
            if curl -fs "http://127.0.0.1:$port/health" > /dev/null 2>&1; then
                break
            fi
            sleep 0.2
        done
        echo "PINGPONG_E2E_URL=http://127.0.0.1:$port"
        echo "PINGPONG_E2E_PROJECT=e2e"
        echo "PINGPONG_E2E_KEY=$(sed -n 's/^key: *//p' "$state/project.txt")"
        echo "PINGPONG_E2E_SECRET=$(sed -n 's/^secret: *//p' "$state/project.txt")"
        echo "PINGPONG_E2E_CHECKOUT=$checkout"
        ;;
    down)
        [ -f "$state/pid" ] && kill "$(cat "$state/pid")" 2>/dev/null || true
        rm -rf "$state"
        ;;
    logs)
        cat "$state/server.log"
        ;;
    *)
        echo "Usage: tests/EndToEnd/pingpong.sh up|down|logs" >&2
        exit 64
        ;;
esac
