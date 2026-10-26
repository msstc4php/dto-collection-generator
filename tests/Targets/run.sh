#!/bin/sh
# Runs every golden profile on each PHP version from the profile's own up to 8.4 (lint, smoke test, ODM's persistent
# subclass), then PHPStan max with the profile's version. dto-collection 1.x declares php <8.5, so 8.5 only lints.
set -eu

workdir=$(mktemp -d "${TMPDIR:-/tmp}/dto-collection-generator-targets.XXXXXX")
trap 'rm -rf "$workdir"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

run_on() {
    docker run --rm --user "$(id -u):$(id -g)" -v "$PWD:/app" -w /app "php:$1-cli" sh -c "$2" sh "$3"
}

lint='for file in "$1"*.golden; do php -l "$file" > /dev/null || exit 1; done'

for profile in tests/Fixtures/Emitter/*/; do
    version=$(basename "$profile")
    for php in 8.1 8.2 8.3 8.4; do
        if [ "$(printf '%s\n%s\n' "$version" "$php" | sort -V | head -n1)" = "$version" ]; then
            run_on "$php" "$lint && php tests/Targets/smoke.php \"\$1\" && php tests/Targets/odm-subclass.php \"\$1\"" "$profile"
        fi
    done
    run_on 8.5 "$lint && echo \"lint ok \$1 on PHP 8.5\"" "$profile"

    config="$workdir/phpstan.neon"
    cat > "$config" <<NEON
parameters:
    level: max
    phpVersion: $(( ${version%.*} * 10000 + ${version#*.} * 100 ))
    fileExtensions: [golden]
    paths: ["$PWD/$profile"]
    scanDirectories: ["$PWD/tests/Fixtures/Model"]
    bootstrapFiles: ["$PWD/vendor/autoload.php"]
NEON
    tools/vendor/bin/phpstan analyse -c "$config" --no-progress --error-format=raw --memory-limit=512M
    echo "phpstan ok $profile"
done
