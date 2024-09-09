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
	php -f tests/PrepareForTest.php

incoming:
	cd src &&  php -f ParujPrijateFaktury.php && cd ..
outcoming:
	cd src &&  php -f ParujVydaneFaktury.php && cd ..
newtoold:
	cd src &&  php -f ParujFakturyNew2Old.php && cd ..
parujnew2old:
	cd src &&  php -f ParujFakturyNew2Old.php && cd ..

match: incoming outcoming parujnew2old
phpunit: pretest match

test72:
	@echo '################################################### PHP 7.2'
	php7.2 -f tests/PrepareForTest.php
	cd src &&  php7.2 -f ParujPrijateFaktury.php && cd ..
	cd src &&  php7.2 -f ParujVydaneFaktury.php && cd ..

test73:
	@echo '################################################### PHP 7.3'
	php7.3 -f tests/PrepareForTest.php
	cd src &&  php7.3 -f ParujPrijateFaktury.php && cd ..
	cd src &&  php7.3 -f ParujVydaneFaktury.php && cd ..

test80:
	@echo '################################################### PHP 8.0'
	php8.0 -f tests/PrepareForTest.php
	cd src &&  php8.0 -f ParujPrijateFaktury.php && cd ..
	cd src &&  php8.0 -f ParujVydaneFaktury.php && cd ..


testphp: test71 test72 test7.3 test8.0

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
        
#drun: dimage
#	docker run  -dit --name AbraFlexiMailer -p 2323:80 vitexsoftware/abraflexi-mailer

release:
	echo Release v$(nextversion)
	dch -v $(nextversion) `git log -1 --pretty=%B | head -n 1`
	debuild -i -us -uc -b
	git commit -a -m "Release v$(nextversion)"
	git tag -a $(nextversion) -m "version $(nextversion)"


buildimage:
	docker build -f Containerfile  -t vitexsoftware/abraflexi-mailer:latest .

buildx:
	docker buildx build  -f Containerfile  . --push --platform linux/arm/v7,linux/arm64/v8,linux/amd64 --tag vitexsoftware/abraflexi-mailer:latest

drun:
	docker run  -f Containerfile --env-file .env vitexsoftware/abraflexi-mailer:latest



.PHONY : install
	