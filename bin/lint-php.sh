#!/usr/bin/env sh
#
# Parse-level PHP lint on the PHP binary of the caller, without Docker.
#
# Runs `php -l` over every PHP file under the given paths (default: src, tests
# and the root tooling configs) and exits non-zero on any parse error, and
# also when it finds no PHP file to lint, so that a wrong path never passes
# silently. CI runs it on the PHP 8.3 job, the platform floor, which turns it
# into the PHP 8.3 parse lint.
#
# Usage: bin/lint-php.sh [path ...]
set -eu

PHP_BIN="${PHP_BINARY:-php}"

if [ "$#" -eq 0 ]; then
    set -- src tests
    for config in .php-cs-fixer.dist.php .php-rector.php; do
        if [ -f "$config" ]; then
            set -- "$@" "$config"
        fi
    done
fi

list="$(mktemp)"
trap 'rm -f "$list"' EXIT

for path in "$@"; do
    if [ ! -e "$path" ]; then
        echo "lint: path not found: $path" >&2
        exit 1
    fi
    find "$path" -type f -name '*.php' -print0 >>"$list"
done

count="$(tr -cd '\0' <"$list" | wc -c | tr -d ' ')"

if [ "$count" -eq 0 ]; then
    echo "lint: no PHP file found under: $*" >&2
    exit 1
fi

version="$("$PHP_BIN" -r 'echo PHP_VERSION;')"

# Since PHP 8.3, `php -l` takes several files and exits non-zero when any of
# them fails to parse; xargs then exits non-zero too.
status=0
output="$(xargs -0 -n 200 "$PHP_BIN" -n -l <"$list" 2>&1)" || status=$?

if [ "$status" -ne 0 ]; then
    printf '%s\n' "$output" | grep -v '^No syntax errors detected in ' >&2 || true
    echo "lint: PHP $version parse errors found (see above)." >&2
    exit 1
fi

echo "lint: $count files parse on PHP $version"
