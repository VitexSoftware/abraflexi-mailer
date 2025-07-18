# vim: set tabstop=8 softtabstop=8 noexpandtab:
.PHONY: help
help: ## Displays this list of targets with descriptions
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: static-code-analysis
static-code-analysis: vendor ## Runs a static code analysis with phpstan/phpstan
	vendor/bin/phpstan analyse --configuration=phpstan-default.neon.dist --memory-limit=-1

.PHONY: static-code-analysis-baseline
static-code-analysis-baseline: check-symfony vendor ## Generates a baseline for static code analysis with phpstan/phpstan
	vendor/bin/phpstan analyze --configuration=phpstan-default.neon.dist --generate-baseline=phpstan-default-baseline.neon --memory-limit=-1

.PHONY: tests
tests: vendor
	vendor/bin/phpunit tests

.PHONY: vendor
vendor: composer.json composer.lock ## Installs composer dependencies
	composer install

.PHONY: cs
cs: ## Update Coding Standards
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --diff --verbose


composer:
	composer update

fresh:
	echo fresh

install:
	echo install

build:
	echo build

pretest:
	composer --ansi --no-interaction update

phpunit: pretest
	vendor/bin/phpunit --configuration phpunit.xml --bootstrap tests/bootstrap.php

phpstan:
	vendor/bin/phpstan analyse  --xdebug --level 4 -n  src

clean:
	rm -rf debian/abraflexi-mailer
	rm -rf debian/*.substvars debian/*.log debian/*.debhelper debian/files debian/debhelper-build-stamp
	rm -rf vendor composer.lock

deb:
	dpkg-buildpackage -A -us -uc

dimage: deb
	mv ../abraflexi-mailer_*_all.deb .
	docker build -t  .

dtest:
	docker-compose run --rm default install

drun: dimage
	docker run  -dit --name AbraFlexiMailer -p 2323:80 vitexsoftware/abraflexi-mailer
buildimage:
	docker build -f Containerfile  -t vitexsoftware/abraflexi-mailer:latest .

buildx:
	docker buildx build  -f Containerfile  . --push --platform linux/arm/v7,linux/arm64/v8,linux/amd64 --tag vitexsoftware/abraflexi-mailer:latest

.PHONY : install
