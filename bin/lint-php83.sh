#!/usr/bin/env sh
#
# Parse-level PHP 8.3 compatibility lint, on a real PHP 8.3 interpreter.
#
# The dev environment runs a newer PHP (8.4), whose parser accepts syntax that
# does not exist on the supported floor (PHP ^8.3) — e.g. `new X()->method()`.
# Such code passes `composer check` locally yet is a fatal parse error on 8.3.
# This runs bin/lint-php.sh inside the php:8.3-cli image over the source, the
# tests and the root tooling configs. CI runs bin/lint-php.sh directly on its
# PHP 8.3 job (`composer lint`).
#
# Requires Docker. Without it the lint cannot run, so it fails: a skipped lint
# must never read as a passing one.
set -eu

IMAGE="php:8.3-cli"

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
    echo "lint:php83 needs a running Docker (PHP 8.3 is not installed locally); nothing was linted." >&2
    echo "Run 'composer lint' on a PHP 8.3 binary instead." >&2
    exit 1
fi

exec docker run --rm -v "$(pwd)":/app -w /app "$IMAGE" sh bin/lint-php.sh
