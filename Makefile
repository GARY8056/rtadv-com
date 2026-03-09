SHELL := /bin/zsh

.PHONY: build lint deploy-local

build:
	./scripts/build-local.sh

lint:
	./scripts/build-local.sh --lint-only

deploy-local:
	./scripts/deploy-local.sh
