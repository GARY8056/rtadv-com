# rtadv-com - Ops Memory (Hot Cache)

**Last Updated**: 2026-03-09
**Target Site**: `www.rtadv.com`
**Local Working Folder**: `/Users/ss/Documents/GitHub/rtadv-com`
**Status**: Active — 文章發布自動化 + inode cleanup

## 文章發布系統

### Skills（Claude Code slash commands）

| Skill | 用途 |
|-------|------|
| `/rtadv-publish-article <關鍵字>` | 全流程發文（撰寫→配圖→發布→SEO→驗證） |
| `/rtadv-seo-audit <URL或post_id>` | SEO/GEO/E-E-A-T 完整審計 + 自動修復 |

### Scripts（`scripts/` 目錄）

| 腳本 | 用途 |
|------|------|
| `wp-auth.sh` | WP 登入，export COOKIE_FILE + WP_NONCE |
| `wp-publish.sh` | REST API 發布文章 |
| `wp-rankmath.sh` | Rank Math SEO meta 設定 |
| `wp-cache-purge.sh` | WP Rocket 快取清除 |
| `gemini-generate.sh` | **Gemini 圖片生成（主要）** |
| `dalle-generate.sh` | DALL-E 3 圖片生成（備用） |
| `wp-upload-image.sh` | 上傳圖片到 WP 媒體庫 |
| `build-local.sh` | PHP lint + build artifact |
| `deploy-local.sh` | 部署 mu-plugins 到本地 WP |

### 圖片規範

遵循 `0304seo` 視覺系統（`/Users/ss/Documents/GitHub/0304seo/docs/`）：
- 共通硬規則：禁文字、禁箭頭、結構合理、焦點明確、可裁切、禁中文烘焙
- 風格：practical + credible + conversion-first
- 3 類 prompt：Hero / Detail / Context
- 生成前必須組裝 single input pack（不能只丟路徑）

### WordPress API 端點

- 文章 CRUD：`/wp-json/wp/v2/posts`
- 媒體上傳：`/wp-json/wp/v2/media`
- Rank Math：`/wp-json/rankmath/v1/updateMeta`
- REST nonce：`/wp-admin/admin-ajax.php?action=rest-nonce`
- 快取清除：`/?wprocket-purge-post=<id>`

### 文章分類

- ID 95：印刷包裝（主要使用）

## Working Rule

- Use `/Users/ss/Documents/GitHub/rtadv-com` as the local folder for `www.rtadv.com` work.
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
