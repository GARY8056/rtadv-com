# Local Build And Deploy

This repo is a WordPress operations repo, not a frontend app bundle.

The safe local workflow is:

1. lint all `mu-plugins`
2. build a clean local artifact into `build/local`
3. deploy that artifact into a separate local WordPress site

This avoids editing the live working tree during local deployment.

## Files

- `scripts/build-local.sh`
- `scripts/deploy-local.sh`
- `Makefile`
- `.env.example`

## Setup

Create a local `.env` from `.env.example`.

Example:

```bash
cp .env.example .env
```

Then set one of:

```bash
LOCAL_WP_ROOT=/absolute/path/to/local-wordpress
```

or:

```bash
LOCAL_MU_PLUGIN_DIR=/absolute/path/to/local-wordpress/wp-content/mu-plugins
```

Optional backup override:

```bash
LOCAL_DEPLOY_BACKUP_DIR=/absolute/path/to/backups
```

## Commands

Lint only:

```bash
make lint
```

Build local artifact:

```bash
make build
```

Deploy to local WordPress:

```bash
make deploy-local
```

## Safety Rules

- `build/` is generated output only
- source files under `wp-content/mu-plugins/` remain the development source of truth
- deployment only targets a path ending in `/wp-content/mu-plugins`
- deployment always creates a timestamped backup first
- this workflow is local-only and does not touch Divi settings
