TOOLS := tools/vendor/bin
PHP81 := docker run --rm --user "$$(id -u):$$(id -g)" -v "$(CURDIR):/app" -w /app php:8.1-cli

install: ## Install package and tool dependencies
	composer install
	composer install --working-dir=tools

check: ## Static checks (incl. PHP 8.1 syntax lint)
	$(TOOLS)/phpstan analyse --memory-limit=512M -c phpstan.dist.neon
	$(TOOLS)/php-cs-fixer check
	composer validate --strict --no-check-publish
	$(TOOLS)/rector process -n
	$(TOOLS)/deptrac analyse --config-file=deptrac.yaml --no-progress --fail-on-uncovered
	composer audit
	$(MAKE) lint-81

bc-check: ## Compare the public API with the latest release tag (Roave)
	@base=$$(git describe --tags --abbrev=0 --match 'v[0-9]*' --exclude='*-*') || { echo "No release tag to compare with."; exit 1; }; \
	$(TOOLS)/roave-backward-compatibility-check --from=$$base --install-development-dependencies --no-interaction

lint-81: ## Lint sources with the PHP 8.1 parser
	$(PHP81) sh -c "find src tests -name '*.php' -not -path 'tests/Fixtures/Projects/Scan/Syntax.php' -not -path 'tests/Fixtures/Projects/Scan/Unparsable.php' -print0 | xargs -0 -r -n1 php -l > /dev/null"

test: ## Run the tests on the local PHP
	vendor/bin/phpunit

test-81: ## Run the tests on PHP 8.1
	$(PHP81) vendor/bin/phpunit

test-targets: ## Lint, smoke-test and analyse the generated code on PHP 8.1 … 8.5 (Docker)
	sh tests/Targets/run.sh

verify: check test test-81 ## Full gate: static checks + tests on local PHP and PHP 8.1

# Mutation testing on every core makes a laptop unusable; THREADS=$(nproc) for a full-speed run.
THREADS ?= 4

infection: ## Mutation testing
	XDEBUG_MODE=coverage php -d memory_limit=2G $(TOOLS)/infection --threads=$(THREADS) --no-interaction

fix: ## Apply Rector and code style
	$(TOOLS)/rector process
	$(TOOLS)/php-cs-fixer fix

help: ## List commands
	@grep -E '^[a-zA-Z_0-9-]+:.*?## ' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "%-12s %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
.PHONY: install check bc-check lint-81 test test-81 test-targets verify infection fix help
