# rtadv-com - Ops Memory (Hot Cache)

**Last Updated**: 2026-03-06
**Target Site**: `www.rtadv.com`
**Local Working Folder**: `/Users/gary/Documents/GitHub/rtadv-com`
**Status**: Active inode cleanup via FTP (safe scope)

## Working Rule

- Use `/Users/gary/Documents/GitHub/rtadv-com` as the local folder for `www.rtadv.com` work.
- Do not persist FTP credentials in repo files.
- Keep Divi settings untouched; cleanup is filesystem-level only (cache/log/backup residue).

## Authorization / Guardrails

- User authorized continuing safe cleanup without re-confirming each deletion command.
- Safe scope: caches, logs, backup residue, temp files.
- Divi remains read-only (no Divi module, Theme Builder, or shortcode edits).

## Confirmed Completed Cleanup (Latest)

### 1) ShortPixel Backups

- `wp-content/uploads/ShortpixelBackups/wp-content/uploads/2020`: deleted.
- `wp-content/uploads/ShortpixelBackups/wp-content/uploads/2021`: deleted.
- `wp-content/uploads/ShortpixelBackups/wp-content/uploads/2022`: deleted.
- `wp-content/uploads/ShortpixelBackups/wp-content/uploads/2023`: deleted.
- Empty `ShortpixelBackups` shell removed.

### 2) Imagify Backup Tree

- Target: `rtadv.com/public_html/imagify-backup/wp-content/uploads/cwv-webp-images`
- Batched delete result: `deleted_files=3700`, `failed_files=0`, `removed_dir=1`.
- Follow-up tree purge result on `/public_html/imagify-backup`: `deleted_files=51`, `deleted_dirs=11`, `failed_ops=0`.
- `imagify-backup` no longer listed under `/public_html`.

### 3) WooCommerce Logs (High inode gain, stable target)

- Target: `wp-content/uploads/wc-logs`
- Deleted all old `*.log` files (Jan-Feb 2025 set).
- Kept protection files:
  - `.htaccess`
  - `index.html`
- Current `wc-logs` listing contains only these 2 files.

## Cache Cleanup Progress

### WP Rocket (`wp-content/cache/wp-rocket/www.rtadv.com`)

- Historical completed passes include:
  - pass A: `deleted_files=7544`, `deleted_dirs=2636`, `failed_ops=234`
  - pass B: `deleted_files=2254`, `deleted_dirs=603`, `failed_ops=2`
- Behavior: frequent regeneration causes churn; additional passes continue to remove files but do not hold near zero.

### Divi et-cache (`wp-content/et-cache`)

- Root previously observed around `520` entries.
- Latest sample count: `~472` entries (includes `.` and `..`).
- A parallel purge attempt processed many child dirs but encountered FTP instability (`curl` exit 56), so this area is only partially reduced and still regenerates.

## Other Prior Results (Kept)

- `rtadv.com/logs`: reduced earlier from `32` items to `9`.
- `rtadv.com/webstats`: reduced earlier from `86` items to `30`.

## Current In-Flight / Next Safe Actions

1. Continue bounded cleanup passes on `et-cache` and `wp-rocket` (short runs, then re-check counts).
2. Prioritize non-regenerating targets next (old backup/temp directories) for deterministic inode drops.
3. Keep avoiding live media deletion under `wp-content/uploads/20xx/...` unless explicitly validated.

## Do Not Delete Blindly

- Primary live media under `wp-content/uploads/20xx/...`
- `wp-content/uploads/cwv-webp-images`
- `wp-content/uploads/web-vital-webp`
- `wp-content/uploads/siteground-optimizer-assets`

These may still be serving frontend assets and should only be removed after explicit usage verification.
