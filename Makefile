SHELL := /bin/zsh

.PHONY: build lint deploy-local wp-auth

build:
	./scripts/build-local.sh

lint:
	./scripts/build-local.sh --lint-only

deploy-local:
	./scripts/deploy-local.sh

wp-auth:
	@echo "Run: eval \$$(./scripts/wp-auth.sh)"
