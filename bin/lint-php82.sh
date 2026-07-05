#!/usr/bin/env sh
#
# Parse-level PHP 8.2 compatibility lint.
#
# The dev environment runs a newer PHP (8.4), whose parser accepts syntax that
# does not exist on the supported floor (PHP ^8.2) — e.g. `new X()->method()`.
# Such code passes `composer check` locally yet is a fatal parse error on 8.2.
# This runs `php -l` under a real PHP 8.2 interpreter (Docker) over the source,
# the tests, AND the root tooling configs (which no other check parses on 8.2).
#
# Requires Docker. CI enforces the same floor via the PHP 8.2 job.
set -eu

IMAGE="php:8.2-cli"

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
    echo "lint:php82 needs a running Docker (PHP 8.2 not installed locally). Skipping." >&2
    exit 0
fi

docker run --rm -v "$(pwd)":/app -w /app "$IMAGE" sh -c '
    set -e
    fail=0
    for f in $(find src tests -name "*.php") $(ls .php-cs-fixer.dist.php .php-rector.php 2>/dev/null); do
        php -l "$f" >/dev/null || fail=1
    done
    if [ "$fail" -ne 0 ]; then
        echo "PHP 8.2 parse errors found (see above)." >&2
        exit 1
    fi
    echo "PHP 8.2 parse lint OK"
'
