# RTADV Blog Automation Bridge

## Purpose

This repo does not currently contain a full WordPress codebase. To keep the change non-destructive and compatible with the Divi guardrails, the implementation here is a drop-in MU plugin:

- it does not modify Divi modules, Theme Builder layouts, or shortcode structures
- it adds a WordPress admin tool plus a REST endpoint
- it creates reviewable **draft** posts only
- it reuses the existing RTADV shared blog automation workflow instead of duplicating model logic here

Plugin file:

- `wp-content/mu-plugins/rtadv-blog-automation-bridge.php`
- `wp-content/mu-plugins/rtadv-blog-automation/bridge-config.php`

## Shared Skill Alignment

The bridge intentionally follows the shared skill rules from `rtadv-blog-automation` and `write-and-generate-images`:

- draft-first workflow, not auto-publish
- preserves `keyword -> strategy -> draft -> image plans` flow
- keeps `hero / detail / lifestyle` image variants
- stores proofread and source-check data for editorial review
- imports rendered images only when the upstream workflow returns them
- keeps the writing and image direction tied to the same upstream draft payload

It now also mirrors the most relevant defaults from the `seo` project:

- `SEO_AUTO_DEFAULTS` style cadence (`dailyArticleCount`, tier quotas, selection mode) is stored as reference metadata on generated drafts
- default audience / search intent / internal links are prefilled from the shared SEO automation assumptions
- if future upstream payloads include SEO-style image metadata (`altText`, `title`, `caption`, `description`), the bridge will map them into WordPress media fields

## What The Plugin Adds

1. A REST endpoint at:
   - `/wp-json/rtadv/v1/blog-automation/draft`
2. A WordPress admin page at:
   - `Tools -> RTADV 自動草稿`
3. Draft post creation that:
   - writes article sections into `post_content`
   - stores raw markdown, proofread data, source checks, and image plans in post meta
   - stores SEO reference defaults and settings source in post meta
   - sets the `hero` image as featured image when the upstream response includes rendered base64 image data
   - applies image alt text / title / caption / description when those fields are available

## Upstream Dependency

This bridge assumes an upstream RTADV automation API exists and follows the existing shared contract from `new-rtadv-net`:

- `POST /api/blog-automation/draft`

Default upstream base URL:

- `https://www.rtadv.net`

The plugin combines that base URL with:

- `/api/blog-automation/draft`

So the default upstream request target is:

- `https://www.rtadv.net/api/blog-automation/draft`

## Configuration Hooks

If the upstream service should point elsewhere, override it with a filter in a safe custom plugin or snippet:

```php
add_filter('rtadv_blog_automation_bridge_base_url', function () {
	return 'https://staging.rtadv.net';
});
```

Optional request customization:

```php
add_filter('rtadv_blog_automation_bridge_request_args', function ($args, $payload) {
	$args['headers']['Authorization'] = 'Bearer your-token';
	return $args;
}, 10, 2);
```

Optional timeout override:

```php
add_filter('rtadv_blog_automation_bridge_timeout', function () {
	return 120;
});
```

## Local Settings File

The bridge now checks for a local config file before falling back to hardcoded defaults:

- `wp-content/mu-plugins/rtadv-blog-automation/bridge-config.php`

This file is the right place to keep site-specific defaults that are **inspired by** the `seo` project without modifying the plugin itself.

Current checked-in example aligns with:

- `/Users/gary/Documents/GitHub/seo/src/lib/seo-auto/config.ts`
- `/Users/gary/Documents/GitHub/seo/src/lib/seo-auto/article-generator.ts`
- `/Users/gary/Documents/GitHub/seo/src/lib/seo-auto/multi-image-generator.ts`

You can safely change:

- upstream base URL
- upstream endpoint path
- request timeout
- default audience / search intent
- default `internalLinks`
- reference-only `seoAutoDefaults`

## Request Shape

The local bridge accepts the same high-value fields as the shared workflow:

- `keyword` (required)
- `primaryKeyword`
- `secondaryKeywords`
- `queryVariants`
- `paaQuestions`
- `category`
- `titleHint`
- `audience`
- `searchIntent`
- `templateVariant`
- `internalLinks`
- `includeRenderedImages`

## Safe Rollout

1. Drop the MU plugin into the live WordPress install under `wp-content/mu-plugins/`.
2. Confirm the upstream `draft` endpoint is reachable from the WordPress host.
3. Run one test keyword from `Tools -> RTADV 自動草稿`.
4. Verify:
   - a draft post is created
   - proofread and image plan meta exists
   - featured image is attached only when rendered image data is returned

## What This Does Not Do

- It does not publish posts automatically.
- It does not edit Divi layouts.
- It does not inject generated content into Divi modules.
- It does not replace the upstream AI workflow.

If later you want full publish flow, batch generation, or automatic Divi module hydration, that should be added as a separate approved phase.
