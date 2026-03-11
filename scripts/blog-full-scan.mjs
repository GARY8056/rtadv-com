#!/usr/bin/env node
/**
 * blog-full-scan.mjs
 *
 * Blog-only scanning and repair runner for rtadv.com.
 * Scope is limited to WordPress posts + image media.
 */

import fs from 'node:fs';
import https from 'node:https';
import http from 'node:http';
import path from 'node:path';
import { execFileSync, execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { BLOG_RULES, APPLY_ALLOWED_PHASES, IMPLEMENTED_PHASES } from './lib/blog-rules.mjs';
import {
  PROGRESS_FILE,
  buildReportPath,
  buildReportSkeleton,
  createRunId,
  hashText,
  log,
  readJson,
  saveManifest,
  saveProgress,
  saveSnapshot,
  writeJson,
} from './lib/blog-report.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const SITE = 'https://www.rtadv.com';
const MEDIA_VENV_PYTHON = '/tmp/rtadv-media-venv/bin/python';

loadEnv(path.join(__dirname, '.env'));
loadEnv('/Users/ss/Documents/GitHub/0304seo/ops/deployment/scripts/.env');

const args = parseArgs(process.argv.slice(2));
const PHASE = args.phase || 'scan';
const APPLY = args.apply === true;
const DRY_RUN = !APPLY;
const START = parseInt(args.start || '0', 10);
const COUNT = parseInt(args.count || '0', 10);
const ONLY = args.only ? parseInt(args.only, 10) : null;
const ID_FILE = args['id-file'] || null;
const YEAR = args.year ? parseInt(args.year, 10) : null;
const AFTER = args.after || null;
const BEFORE = args.before || null;
const FAIL_THRESHOLD = parseInt(args['fail-threshold'] || '25', 10);
const RESUME = args.resume === true;
const VERIFY_PHASE = args['verify-phase'] || null;
const DEEP_MEDIA_CHECK = args['deep-media-check'] === true;
const OLD_MEDIA_ID = args['old-media-id'] ? parseInt(args['old-media-id'], 10) : null;
const NEW_MEDIA_ID = args['new-media-id'] ? parseInt(args['new-media-id'], 10) : null;

let COOKIE_FILE = '';
let WP_NONCE = '';

const VALID_PHASES = [
  'scan',
  'scan-posts',
  'scan-media',
  'orphaned-media-analysis',
  'inline-image-gap-analysis',
  'inline-image-gap-plan',
  'inline-image-gap-fix',
  'seo-audit',
  'seo-fix',
  'post-audit',
  'structure-fix',
  'link-audit',
  'link-fix',
  'slug-plan',
  'slug-apply',
  'media-audit',
  'media-meta-fix',
  'media-file-fix',
  'featured-media-migration',
  'verify',
];

if (!VALID_PHASES.includes(PHASE)) {
  console.error(`Unknown phase: ${PHASE}`);
  process.exit(1);
}

if (APPLY && !APPLY_ALLOWED_PHASES.has(PHASE)) {
  console.error(`Phase ${PHASE} does not allow --apply`);
  process.exit(1);
}

function loadEnv(file) {
  if (!fs.existsSync(file)) return;
  for (const line of fs.readFileSync(file, 'utf8').split('\n')) {
    const match = line.match(/^(\w+)=(.+)$/);
    if (!match) continue;
    let value = match[2].trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    process.env[match[1]] = value;
  }
}

function parseArgs(argv) {
  const parsed = {};
  for (const arg of argv) {
    if (!arg.startsWith('--')) continue;
    const [key, value] = arg.slice(2).split('=');
    parsed[key] = value === undefined ? true : value;
  }
  return parsed;
}

function getFtpConfig() {
  return {
    host: process.env.FTP_HOST || 'sm31.siteground.biz',
    user: process.env.FTP_USER || 'image@rtadv.com',
    pass: process.env.FTP_PASS || '',
  };
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function stripHtml(value) {
  return String(value || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function truncate(value, max = 120) {
  return String(value || '').length > max ? `${String(value).slice(0, max - 1)}…` : String(value || '');
}

function hasCjk(value) {
  return /[\u3400-\u9fff]/.test(String(value || ''));
}

function cleanTitle(value) {
  return stripHtml(value).replace(/[｜|–—].*$/, '').trim();
}

function cleanKeyword(title) {
  const source = cleanTitle(title);
  const match = source.match(/(紙袋|紙盒|彩盒|紙管|包裝設計|印刷|食品包裝|禮盒)/);
  return match ? match[1] : truncate(source.replace(/\s+/g, ''), 12);
}

function buildSeoAuditPatch(item) {
  const patch = {};
  if (!item.has_focus_kw) patch.rank_math_focus_keyword = cleanKeyword(item.title || item.slug);
  if (!item.has_rm_title) patch.rank_math_title = `${truncate(cleanTitle(item.title || item.slug), 50)}｜圓廣創意印刷`;
  if (!item.has_rm_desc) patch.rank_math_description = `${truncate(cleanTitle(item.title || item.slug), 60)}。圓廣創意印刷提供專業包裝印刷服務。`;
  return patch;
}

function buildScope() {
  return {
    post_type: BLOG_RULES.scope.postType,
    start: START,
    count: COUNT || null,
    only: ONLY,
    id_file: ID_FILE,
    year: YEAR,
    after: AFTER,
    before: BEFORE,
    deep_media_check: DEEP_MEDIA_CHECK,
    old_media_id: OLD_MEDIA_ID,
    new_media_id: NEW_MEDIA_ID,
  };
}

function getResumeOffset() {
  if (!RESUME) return START;
  const progress = readJson(PROGRESS_FILE, {});
  if (progress.last_run?.phase !== PHASE) return START;
  return progress.last_run.next_index || START;
}

function loadIdFilter() {
  if (!ID_FILE) return null;
  const raw = fs.readFileSync(ID_FILE, 'utf8');
  const ids = raw.split(/\s+/).map(value => parseInt(value, 10)).filter(Number.isFinite);
  return new Set(ids);
}

function filterByScope(items) {
  const ids = loadIdFilter();
  let scoped = items;

  if (ONLY) scoped = scoped.filter(item => item.id === ONLY);
  if (ids) scoped = scoped.filter(item => ids.has(item.id));
  if (YEAR) scoped = scoped.filter(item => String(item.date || '').startsWith(String(YEAR)));
  if (AFTER) scoped = scoped.filter(item => String(item.date || '') >= AFTER);
  if (BEFORE) scoped = scoped.filter(item => String(item.date || '') <= BEFORE);

  const offset = getResumeOffset();
  if (COUNT > 0) return scoped.slice(offset, offset + COUNT);
  return scoped.slice(offset);
}

async function ensureAuth() {
  if (COOKIE_FILE && WP_NONCE) return;
  const output = execSync(`set -a && source ${__dirname}/.env && set +a && eval "$(${__dirname}/wp-auth.sh)" && printf 'COOKIE_FILE=%s\\nWP_NONCE=%s\\n' "$COOKIE_FILE" "$WP_NONCE"`, {
    shell: '/bin/zsh',
    encoding: 'utf8',
    env: process.env,
  });
  for (const line of output.split('\n')) {
    const [key, value] = line.split('=');
    if (key === 'COOKIE_FILE') COOKIE_FILE = value || '';
    if (key === 'WP_NONCE') WP_NONCE = value || '';
  }
  if (!COOKIE_FILE || !WP_NONCE) throw new Error('WordPress auth failed');
}

function readCookieHeader() {
  const lines = fs.readFileSync(COOKIE_FILE, 'utf8').split('\n');
  return lines
    .filter(line => line && !line.startsWith('# ') && !line.startsWith('#\n'))
    .map(line => line.startsWith('#HttpOnly_') ? line.slice('#HttpOnly_'.length) : line)
    .map(line => line.split('\t'))
    .filter(parts => parts.length >= 7)
    .map(parts => `${parts[5]}=${parts[6]}`)
    .join('; ');
}

async function api(method, endpoint, body) {
  await ensureAuth();
  const url = endpoint.startsWith('http') ? endpoint : `${SITE}/wp-json${endpoint}`;
  const parsed = new URL(url);
  const payload = body === undefined ? null : JSON.stringify(body);
  const headers = {
    'X-WP-Nonce': WP_NONCE,
    'User-Agent': 'rtadv-blog-full-scan-v2',
    Cookie: readCookieHeader(),
  };
  if (payload) headers['Content-Type'] = 'application/json';

  return new Promise((resolve, reject) => {
    const mod = parsed.protocol === 'https:' ? https : http;
    const req = mod.request(parsed, { method, headers }, res => {
      let data = '';
      res.on('data', chunk => {
        data += chunk;
      });
      res.on('end', () => {
        if (res.statusCode >= 400) {
          reject(new Error(`API ${res.statusCode}: ${truncate(data, 240)}`));
          return;
        }
        try {
          resolve(JSON.parse(data));
        } catch {
          resolve(data);
        }
      });
    });
    req.on('error', reject);
    req.setTimeout(30000, () => {
      req.destroy(new Error(`API timeout after 30000ms: ${method} ${endpoint}`));
    });
    if (payload) req.write(payload);
    req.end();
  });
}

function buildPostsEndpoint(includeContent, page, perPage) {
  const fields = includeContent
    ? 'id,date,modified,slug,link,title,featured_media,content,meta'
    : 'id,date,modified,slug,link,title,featured_media,meta';

  const params = new URLSearchParams({
    per_page: String(perPage),
    page: String(page),
    status: 'publish',
    context: 'edit',
    _fields: fields,
  });

  if (ONLY) params.set('include', String(ONLY));
  if (AFTER) params.set('after', `${AFTER}T00:00:00`);
  if (BEFORE) params.set('before', `${BEFORE}T23:59:59`);

  return `/wp/v2/posts?${params.toString()}`;
}

async function fetchPosts(includeContent = false) {
  const ids = loadIdFilter();
  if (ids && !ONLY) {
    const targeted = await fetchPostsByIds([...ids], includeContent);
    const filtered = filterByScope(targeted);
    return COUNT > 0 ? filtered.slice(0, COUNT) : filtered;
  }
  const perPage = ONLY ? 1 : (COUNT > 0 ? Math.min(Math.max(COUNT, 1), 100) : 100);
  const startPage = ONLY ? 1 : Math.floor(getResumeOffset() / perPage) + 1;
  const maxItems = ONLY ? 1 : (COUNT > 0 ? COUNT : Infinity);
  const items = [];

  for (let page = startPage; ; page++) {
    const batch = await api('GET', buildPostsEndpoint(includeContent, page, perPage));
    if (!Array.isArray(batch) || batch.length === 0) break;
    items.push(...batch);
    log(`Fetched posts page ${page}: +${batch.length} (total ${items.length})`);
    if (batch.length < 100) break;
    if (items.length >= maxItems) break;
  }
  const filtered = filterByScope(items);
  return COUNT > 0 ? filtered.slice(0, COUNT) : filtered;
}

async function fetchAllPostsForAnalysis(includeContent = false) {
  const perPage = 100;
  const items = [];
  const fields = includeContent
    ? 'id,slug,featured_media,content'
    : 'id,slug,featured_media';
  for (let page = 1; ; page++) {
    const params = new URLSearchParams({
      per_page: String(perPage),
      page: String(page),
      status: 'publish',
      context: 'edit',
      _fields: fields,
    });
    const batch = await api('GET', `/wp/v2/posts?${params.toString()}`);
    if (!Array.isArray(batch) || batch.length === 0) break;
    items.push(...batch);
    log(`Fetched analysis posts page ${page}: +${batch.length} (total ${items.length})`);
    if (batch.length < perPage) break;
  }
  return items;
}

async function fetchPostsByIds(ids, includeContent = false) {
  const uniqueIds = [...new Set(ids.filter(Boolean))];
  if (!uniqueIds.length) return [];
  const fields = includeContent
    ? 'id,date,modified,slug,link,title,featured_media,content,meta'
    : 'id,date,modified,slug,link,title,featured_media,meta';
  const items = [];
  for (let i = 0; i < uniqueIds.length; i += 100) {
    const include = uniqueIds.slice(i, i + 100).join(',');
    const batch = await api('GET', `/wp/v2/posts?include=${include}&per_page=100&status=publish&context=edit&_fields=${fields}`);
    items.push(...batch);
    log(`Fetched targeted posts batch: +${batch.length} (total ${items.length})`);
  }
  return items;
}

async function fetchSeoAuditResults(targetIds = null) {
  const wanted = targetIds ? new Set(targetIds.filter(Boolean)) : null;
  const found = [];
  for (let page = 1; ; page++) {
    const data = await api('GET', `/rtadv/v1/seo-audit?filter=all&per_page=100&page=${page}`);
    const results = Array.isArray(data.results) ? data.results : [];
    if (!results.length) break;
    for (const item of results) {
      if (!wanted || wanted.has(item.id)) {
        found.push(item);
      }
    }
    log(`Fetched SEO audit page ${page}: +${results.length} (matched ${found.length})`);
    if (wanted && found.length >= wanted.size) break;
    if (page >= (data.total_pages || page)) break;
  }
  return found;
}

function buildMediaEndpoint(page, perPage) {
  const params = new URLSearchParams({
    per_page: String(perPage),
    page: String(page),
    media_type: 'image',
    context: 'edit',
    _fields: 'id,date,slug,source_url,post,alt_text,title,caption,description,media_details',
  });

  if (ONLY) params.set('include', String(ONLY));
  if (AFTER) params.set('after', `${AFTER}T00:00:00`);
  if (BEFORE) params.set('before', `${BEFORE}T23:59:59`);

  return `/wp/v2/media?${params.toString()}`;
}

async function fetchMedia() {
  const ids = loadIdFilter();
  if (ids && !ONLY) {
    const targeted = await fetchMediaByIds([...ids]);
    const filtered = filterByScope(targeted);
    return COUNT > 0 ? filtered.slice(0, COUNT) : filtered;
  }
  const perPage = ONLY ? 1 : (COUNT > 0 ? Math.min(Math.max(COUNT, 1), 100) : 100);
  const startPage = ONLY ? 1 : Math.floor(getResumeOffset() / perPage) + 1;
  const maxItems = ONLY ? 1 : (COUNT > 0 ? COUNT : Infinity);
  const items = [];

  for (let page = startPage; ; page++) {
    const batch = await api('GET', buildMediaEndpoint(page, perPage));
    if (!Array.isArray(batch) || batch.length === 0) break;
    items.push(...batch);
    log(`Fetched media page ${page}: +${batch.length} (total ${items.length})`);
    if (batch.length < 100) break;
    if (items.length >= maxItems) break;
  }
  const filtered = filterByScope(items);
  return COUNT > 0 ? filtered.slice(0, COUNT) : filtered;
}

async function fetchMediaByIds(ids) {
  const uniqueIds = [...new Set(ids.filter(Boolean))];
  if (!uniqueIds.length) return [];
  const items = [];
  for (let i = 0; i < uniqueIds.length; i += 100) {
    const include = uniqueIds.slice(i, i + 100).join(',');
    const batch = await api('GET', `/wp/v2/media?include=${include}&per_page=100&media_type=image&context=edit&_fields=id,date,slug,source_url,post,alt_text,title,caption,description,media_details`);
    items.push(...batch);
    log(`Fetched targeted media batch: +${batch.length} (total ${items.length})`);
  }
  return items;
}

async function fetchMediaByParentIds(parentIds) {
  const uniqueIds = [...new Set(parentIds.filter(Boolean))];
  if (!uniqueIds.length) return [];
  const items = [];
  for (let i = 0; i < uniqueIds.length; i += 20) {
    const parent = uniqueIds.slice(i, i + 20).join(',');
    for (let page = 1; ; page++) {
      const batch = await api(
        'GET',
        `/wp/v2/media?parent=${parent}&per_page=100&page=${page}&media_type=image&context=edit&_fields=id,date,slug,source_url,post,alt_text,title,caption,description,media_details`
      );
      if (!Array.isArray(batch) || batch.length === 0) break;
      items.push(...batch);
      if (batch.length < 100) break;
    }
  }
  return items;
}

async function fetchPostsByFeaturedMedia(mediaId) {
  const items = [];
  for (let page = 1; ; page++) {
    const batch = await api(
      'GET',
      `/wp/v2/posts?per_page=100&page=${page}&featured_media=${mediaId}&context=edit&_fields=id,slug,title,featured_media`
    );
    if (!Array.isArray(batch) || batch.length === 0) break;
    items.push(...batch);
    if (batch.length < 100) break;
  }
  return items;
}

function extractInlineImageStats(content) {
  const html = String(content || '');
  const imgTags = [...html.matchAll(/<img\b([^>]*)>/gi)];
  let missingAlt = 0;
  let genericAlt = 0;
  for (const tag of imgTags) {
    const attrs = tag[1] || '';
    const alt = attrs.match(/\balt\s*=\s*["']([^"']*)["']/i)?.[1]?.trim() || '';
    if (!alt) missingAlt++;
    else if (/^(image|img|photo|pic|picture)(\s*\d+)?$/i.test(alt)) genericAlt++;
  }
  return {
    totalImages: imgTags.length,
    missingAlt,
    genericAlt,
    hasH2: /<h2\b/i.test(html),
  };
}

function extractInlineImageRefs(content) {
  const html = String(content || '');
  const refs = [];
  const seenIds = new Set();
  const seenSrc = new Set();
  for (const match of html.matchAll(/<img\b([^>]*)>/gi)) {
    const attrs = match[1] || '';
    const src = attrs.match(/\bsrc\s*=\s*["']([^"']+)["']/i)?.[1] || '';
    const classAttr = attrs.match(/\bclass\s*=\s*["']([^"']+)["']/i)?.[1] || '';
    const id = parseInt(classAttr.match(/\bwp-image-(\d+)\b/i)?.[1] || '', 10);
    if (Number.isFinite(id) && !seenIds.has(id)) {
      seenIds.add(id);
      refs.push({ id, src, match: 'wp-image-id' });
      continue;
    }
    if (src && !seenSrc.has(src)) {
      seenSrc.add(src);
      refs.push({ id: null, src, match: 'src' });
    }
  }
  return refs;
}

function buildMediaMetaPatch(item, parentTitleMap) {
  const parentTitle = cleanTitle(parentTitleMap.get(item.post) || '');
  const basis = parentTitle || cleanTitle(item.title?.rendered || item.slug || `media-${item.id}`);
  return {
    alt_text: `${basis} 示意圖`.slice(0, 120),
    title: basis.slice(0, 100),
    caption: `${basis}｜圓廣創意印刷`.slice(0, 150),
    description: `${basis}。此媒體已補齊圖片說明欄位，用於部落格文章的包裝印刷內容呈現。`.slice(0, 280),
  };
}

function getMediaExtension(sourceUrl) {
  try {
    const pathname = new URL(sourceUrl || '').pathname || '';
    return path.extname(pathname).toLowerCase().replace('.', '');
  } catch {
    return '';
  }
}

function getRemoteUploadPath(sourceUrl) {
  const raw = String(sourceUrl || '');
  const match = raw.match(/\/wp-content\/uploads\/(.+)$/);
  if (!match) return null;
  return `/rtadv.com/public_html/wp-content/uploads/${match[1]}`;
}

function getDerivativeCandidates(item, remotePath) {
  const sizes = item?.media_details?.sizes || {};
  const remoteDir = remotePath ? path.posix.dirname(remotePath) : null;
  const sourceDir = item?.source_url ? String(item.source_url).replace(/\/[^/]+$/, '') : null;
  const originalName = item?.source_url ? String(item.source_url).split('/').pop() : null;
  return Object.values(sizes)
    .filter(size => size && size.file)
    .filter(size => size.file !== originalName)
    .map(size => ({
      file: size.file,
      width: Number(size.width || 0),
      height: Number(size.height || 0),
      area: Number(size.width || 0) * Number(size.height || 0),
      remote_path: remoteDir ? `${remoteDir}/${size.file}` : null,
      source_url: sourceDir ? `${sourceDir}/${size.file}` : null,
    }))
    .sort((a, b) => b.area - a.area);
}

function getFtpRemotePathVariants(remotePath) {
  const variants = new Set();
  if (remotePath) variants.add(remotePath);
  if (remotePath?.startsWith('/rtadv.com/public_html/')) {
    variants.add(`/home/customer/www${remotePath}`);
  }
  if (remotePath?.startsWith('/home/customer/www/rtadv.com/public_html/')) {
    variants.add(remotePath.replace(/^\/home\/customer\/www/, ''));
  }
  return [...variants];
}

function buildMediaFileFixPlan(item, size) {
  const ext = getMediaExtension(item.source_url) || path.extname(String(item.slug || '')).toLowerCase().replace('.', '');
  const overBy = Math.max(size - BLOG_RULES.media.maxBytes, 0);
  let action = 'manual_review';
  let command_hint = null;
  const remote_path = getRemoteUploadPath(item.source_url);
  const derivative_candidates = getDerivativeCandidates(item, remote_path);
  const local_basename = `rtadv-media-${item.id}`;
  const download_path = `/tmp/${local_basename}.${ext || 'bin'}`;
  const optimized_path = `/tmp/${local_basename}-optimized.jpg`;
  let replacement_steps = [];

  if (item.issues?.includes('empty_or_unreachable_file')) {
    action = derivative_candidates.length ? 'restore_from_derivative' : 'regenerate_missing_media';
    replacement_steps = derivative_candidates.length
      ? [
          `curl --ssl-reqd -u '$FTP_USER:$FTP_PASS' -o '${download_path}' 'ftp://$FTP_HOST${derivative_candidates[0].remote_path}'`,
          `curl --ssl-reqd --ftp-create-dirs -u '$FTP_USER:$FTP_PASS' -T '${download_path}' 'ftp://$FTP_HOST${remote_path}'`,
        ]
      : ['# no derivative candidates found; regenerate image and upload replacement'];
  } else if (ext === 'jpg' || ext === 'jpeg') {
    action = 'recompress_jpeg_and_replace';
    command_hint = `sips -s format jpeg -s formatOptions 55 <downloaded-file> --out <optimized-file>.jpg && sips --resampleWidth 1200 <optimized-file>.jpg --out <optimized-file>.jpg`;
    replacement_steps = [
      `curl -L '${item.source_url}' -o '${download_path}'`,
      `sips -s format jpeg -s formatOptions 55 '${download_path}' --out '${optimized_path}'`,
      `sips --resampleWidth 1200 '${optimized_path}' --out '${optimized_path}'`,
      `curl --ssl-reqd --ftp-create-dirs -u '$FTP_USER:$FTP_PASS' -T '${optimized_path}' 'ftp://$FTP_HOST${remote_path}'`,
    ];
  } else if (ext === 'png') {
    action = 'convert_png_to_jpeg_and_replace';
    command_hint = `sips -s format jpeg -s formatOptions 60 <downloaded-file>.png --out <optimized-file>.jpg && sips --resampleWidth 1200 <optimized-file>.jpg --out <optimized-file>.jpg`;
    replacement_steps = [
      `curl -L '${item.source_url}' -o '${download_path}'`,
      `sips -s format jpeg -s formatOptions 60 '${download_path}' --out '${optimized_path}'`,
      `sips --resampleWidth 1200 '${optimized_path}' --out '${optimized_path}'`,
      `curl --ssl-reqd --ftp-create-dirs -u '$FTP_USER:$FTP_PASS' -T '${optimized_path}' 'ftp://$FTP_HOST${remote_path}'`,
    ];
  } else if (ext === 'webp') {
    action = 'convert_or_regenerate_webp';
    command_hint = `curl -L <source_url> -o <downloaded-file>.webp && sips -s format jpeg -s formatOptions 60 <downloaded-file>.webp --out <optimized-file>.jpg && sips --resampleWidth 1200 <optimized-file>.jpg --out <optimized-file>.jpg`;
    replacement_steps = [
      `curl -L '${item.source_url}' -o '${download_path}'`,
      `sips -s format jpeg -s formatOptions 60 '${download_path}' --out '${optimized_path}'`,
      `sips --resampleWidth 1200 '${optimized_path}' --out '${optimized_path}'`,
      `curl --ssl-reqd --ftp-create-dirs -u '$FTP_USER:$FTP_PASS' -T '${optimized_path}' 'ftp://$FTP_HOST${remote_path?.replace(/\.webp$/i, '.jpg') || ''}'`,
      '# if the site expects the original .webp URL, regenerate a smaller webp instead of jpg before upload',
    ];
  }

  return {
    id: item.id,
    post: item.post,
    slug: item.slug,
    source_url: item.source_url,
    size,
    target_max_bytes: BLOG_RULES.media.maxBytes,
    over_by_bytes: overBy,
    extension: ext || 'unknown',
    remote_path,
    derivative_candidates,
    download_path,
    optimized_path,
    action,
    command_hint,
    replacement_steps,
    requires_replace_workflow: true,
    notes: item.post
      ? []
      : ['post parent is null; check orphaned-media-analysis before replacing references'],
  };
}

function buildMediaFileFixScript(items) {
  const lines = [
    '#!/bin/zsh',
    'set -euo pipefail',
    '',
    '# Generated by blog-full-scan.mjs --phase=media-file-fix',
    '# This script is a staged replacement plan. Review each item before upload.',
    '',
    'FTP_HOST="${FTP_HOST:?FTP_HOST required}"',
    'FTP_USER="${FTP_USER:?FTP_USER required}"',
    'FTP_PASS="${FTP_PASS:?FTP_PASS required}"',
    '',
  ];

  for (const item of items) {
    lines.push(`# media_id=${item.id} action=${item.action} over_by=${item.over_by_bytes}B`);
    for (const step of item.replacement_steps || []) {
      lines.push(step);
    }
    lines.push('');
  }

  return `${lines.join('\n')}\n`;
}

function downloadFile(url, destPath) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const mod = parsed.protocol === 'https:' ? https : http;
    const file = fs.createWriteStream(destPath);
    const req = mod.get(parsed, res => {
      if ((res.statusCode || 500) >= 400) {
        file.close();
        fs.rmSync(destPath, { force: true });
        reject(new Error(`Download ${res.statusCode}: ${url}`));
        return;
      }
      res.pipe(file);
      file.on('finish', () => {
        file.close();
        resolve(destPath);
      });
    });
    req.on('error', error => {
      file.close();
      fs.rmSync(destPath, { force: true });
      reject(error);
    });
  });
}

function ftpDownloadFile(remotePath, destPath) {
  const ftp = getFtpConfig();
  if (!ftp.pass) throw new Error('FTP_PASS required for media-file-fix apply');
  try {
    execFileSync(
      'curl',
      [
        '-s',
        '--show-error',
        '--ssl-reqd',
        '-u',
        `${ftp.user}:${ftp.pass}`,
        '-o',
        destPath,
        `ftp://${ftp.host}${remotePath}`,
      ],
      { stdio: ['ignore', 'pipe', 'pipe'], timeout: 120000 }
    );
  } catch (error) {
    const stderr = error?.stderr ? String(error.stderr).trim() : '';
    const stdout = error?.stdout ? String(error.stdout).trim() : '';
    const detail = [stderr, stdout].filter(Boolean).join(' | ');
    throw new Error(detail ? `FTP download failed: ${detail}` : `FTP download failed: ${error.message}`);
  }
}

function compressWebpWithPillow(inputPath, outputPath, targetBytes) {
  if (!fs.existsSync(MEDIA_VENV_PYTHON)) {
    throw new Error(`Missing Pillow venv python: ${MEDIA_VENV_PYTHON}`);
  }
  const script = `
from PIL import Image
import os, sys
src, dst, target = sys.argv[1], sys.argv[2], int(sys.argv[3])
img = Image.open(src)
img.load()
if img.mode not in ("RGB", "RGBA"):
    img = img.convert("RGBA" if "A" in img.mode else "RGB")
width, height = img.size
qualities = [80, 72, 65, 58, 52, 46, 40]
scales = [1.0, 0.92, 0.85, 0.75, 0.66, 0.58]
best = None
for scale in scales:
    if scale != 1.0:
        resized = img.resize((max(1, int(width * scale)), max(1, int(height * scale))), Image.Resampling.LANCZOS)
    else:
        resized = img
    for quality in qualities:
        resized.save(dst, format="WEBP", quality=quality, method=6)
        size = os.path.getsize(dst)
        if best is None or size < best[0]:
            best = (size, quality, resized.size[0], resized.size[1])
        if size <= target:
            print(f"{size}|{quality}|{resized.size[0]}|{resized.size[1]}")
            raise SystemExit(0)
if best:
    print(f"{best[0]}|{best[1]}|{best[2]}|{best[3]}")
    raise SystemExit(0)
raise SystemExit(1)
`.trim();
  const output = execFileSync(
    MEDIA_VENV_PYTHON,
    ['-c', script, inputPath, outputPath, String(targetBytes)],
    { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }
  ).trim();
  const [size, quality, width, height] = output.split('|').map(Number);
  return {
    size,
    quality,
    width,
    height,
    overLimit: size > targetBytes,
  };
}

function ftpReplaceFile(localPath, remotePath) {
  const ftp = getFtpConfig();
  if (!ftp.pass) throw new Error('FTP_PASS required for media-file-fix apply');
  const attempts = [];
  for (const candidatePath of getFtpRemotePathVariants(remotePath)) {
    try {
      execFileSync(
        'curl',
        [
          '-s',
          '--show-error',
          '--ssl-reqd',
          '--ftp-create-dirs',
          '-u',
          `${ftp.user}:${ftp.pass}`,
          '-T',
          localPath,
          `ftp://${ftp.host}${candidatePath}`,
        ],
        { stdio: ['ignore', 'pipe', 'pipe'], timeout: 120000 }
      );
      return candidatePath;
    } catch (error) {
      const stderr = error?.stderr ? String(error.stderr).trim() : '';
      const stdout = error?.stdout ? String(error.stdout).trim() : '';
      const detail = [stderr, stdout].filter(Boolean).join(' | ') || error.message;
      attempts.push(`${candidatePath}: ${detail}`);
    }
  }
  throw new Error(`FTP replace failed: ${attempts.join(' || ')}`);
}

function buildImagePromptBundle(post) {
  const title = cleanTitle(post.title?.raw || post.title?.rendered || post.title || post.slug);
  const slugText = String(post.slug || '').replace(/-/g, ' ');
  const subject = title || slugText || 'packaging printing article';
  const base =
    `${subject}, packaging and printing subject, photorealistic commercial photography, ` +
    `clear focal subject, physically plausible structure, crop-safe composition, no readable text, no arrows, no Chinese text`;
  return {
    hero: `${base}, Hero image, clean studio lighting, premium packaging presentation on neutral background`,
    detail: `${base}, Detail image, close-up macro view, surface finishing texture, embossing, foil, print detail, shallow depth of field`,
    context: `${base}, Context image, product and packaging in realistic usage or shelf context, decision-oriented composition`,
  };
}

function buildFigureHtmlTemplate(role, alt, caption) {
  return [
    '<figure class="wp-block-image size-large">',
    `  <img src="<${role}_media_url>" alt="${alt}" class="wp-image-<${role}_media_id>" loading="lazy" />`,
    `  <figcaption>${caption}</figcaption>`,
    '</figure>',
  ].join('\n');
}

function buildInlineImagePlanScript(items) {
  const lines = [
    '# Inline image gap plan',
    '# Review prompts, generate images with scripts/gemini-generate.sh, upload via scripts/wp-upload-image.sh, then insert figure HTML.',
    '',
  ];
  for (const item of items) {
    lines.push(`## post_id=${item.id} slug=${item.slug}`);
    for (const image of item.image_plan) {
      lines.push(`### ${image.role}`);
      lines.push(`prompt: ${image.prompt}`);
      lines.push(`alt: ${image.alt}`);
      lines.push(`caption: ${image.caption}`);
      lines.push('figure_html:');
      lines.push(image.figure_html);
      lines.push('');
    }
  }
  return `${lines.join('\n')}\n`;
}

function shellEscape(value) {
  return `'${String(value).replace(/'/g, `'\\''`)}'`;
}

function parseKeyValueOutput(output) {
  const map = {};
  for (const line of String(output || '').split('\n')) {
    const idx = line.indexOf('=');
    if (idx <= 0) continue;
    map[line.slice(0, idx)] = line.slice(idx + 1);
  }
  return map;
}

function buildUploadedFigureHtml(media, alt, caption) {
  return [
    '<figure class="wp-block-image size-large">',
    `  <img src="${media.source_url}" alt="${alt}" class="wp-image-${media.id}" loading="lazy" />`,
    `  <figcaption>${caption}</figcaption>`,
    '</figure>',
  ].join('\n');
}

function insertFiguresIntoContent(content, figureHtmls) {
  let next = String(content || '');
  for (const figure of figureHtmls) {
    const h2Index = next.search(/<h2\b/i);
    if (h2Index > 0) {
      next = `${next.slice(0, h2Index)}\n\n${figure}\n\n${next.slice(h2Index)}`;
    } else {
      next = `${next}\n\n${figure}`;
    }
  }
  return next;
}

function generateImageAsset(prompt, outputBase) {
  const target = `${outputBase}.png`;
  const output = execSync(
    `cd ${shellEscape(path.dirname(__dirname))} && GEMINI_MODEL=gemini-3-pro-image-preview ./scripts/gemini-generate.sh ${shellEscape(prompt)} ${shellEscape(target)}`,
    {
      shell: '/bin/zsh',
      encoding: 'utf8',
      env: process.env,
      stdio: ['ignore', 'pipe', 'pipe'],
    }
  );
  const lines = output.trim().split('\n').filter(Boolean);
  return lines[lines.length - 1] || target;
}

function guessMimeType(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  if (ext === '.jpg' || ext === '.jpeg') return 'image/jpeg';
  if (ext === '.png') return 'image/png';
  if (ext === '.webp') return 'image/webp';
  return 'application/octet-stream';
}

function sanitizeUploadFilename(filename) {
  const ext = path.extname(String(filename || ''));
  const stem = path.basename(String(filename || ''), ext);
  const safeStem = stem
    .normalize('NFKD')
    .replace(/[^\x20-\x7E]+/g, '-')
    .replace(/[^a-zA-Z0-9._-]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 80) || `media-${Date.now()}`;
  const safeExt = ext && /^[.][a-zA-Z0-9]+$/.test(ext) ? ext.toLowerCase() : '.bin';
  return `${safeStem}${safeExt}`;
}

async function uploadMediaBinary(imagePath, filename) {
  await ensureAuth();
  const url = new URL(`${SITE}/wp-json/wp/v2/media`);
  const payload = fs.readFileSync(imagePath);
  const safeFilename = sanitizeUploadFilename(filename);
  const headers = {
    'X-WP-Nonce': WP_NONCE,
    'User-Agent': 'rtadv-blog-full-scan-v2',
    Cookie: readCookieHeader(),
    'Content-Disposition': `attachment; filename="${safeFilename}"`,
    'Content-Type': guessMimeType(imagePath),
    'Content-Length': String(payload.length),
  };

  return new Promise((resolve, reject) => {
    const req = https.request(url, { method: 'POST', headers }, res => {
      let data = '';
      res.on('data', chunk => {
        data += chunk;
      });
      res.on('end', () => {
        if ((res.statusCode || 500) >= 400) {
          reject(new Error(`Media upload ${res.statusCode}: ${truncate(data, 240)}`));
          return;
        }
        try {
          resolve(JSON.parse(data));
        } catch {
          reject(new Error(`Media upload returned invalid JSON: ${truncate(data, 240)}`));
        }
      });
    });
    req.on('error', reject);
    req.setTimeout(30000, () => {
      req.destroy(new Error(`Media upload timeout after 30000ms: ${safeFilename}`));
    });
    req.write(payload);
    req.end();
  });
}

async function uploadImageAsset(postId, imagePath, filename, alt, caption, title, description) {
  const uploaded = await uploadMediaBinary(imagePath, filename);
  return api('POST', `/wp/v2/media/${uploaded.id}`, {
    post: postId,
    title,
    description,
    alt_text: alt,
    caption,
  });
}

async function replaceFeaturedMediaReferences(oldMediaId, newMediaId) {
  const posts = await fetchPostsByFeaturedMedia(oldMediaId);
  for (let index = 0; index < posts.length; index++) {
    const post = posts[index];
    await api('POST', `/wp/v2/posts/${post.id}`, { featured_media: newMediaId });
    if ((index + 1) % 20 === 0) {
      log(`Updated featured_media references: ${index + 1}/${posts.length} for media ${oldMediaId} -> ${newMediaId}`);
      await sleep(300);
    }
  }
  return posts.map(post => ({ id: post.id, slug: post.slug }));
}

async function phaseFeaturedMediaMigration() {
  if (!OLD_MEDIA_ID || !NEW_MEDIA_ID) {
    throw new Error('featured-media-migration requires --old-media-id and --new-media-id');
  }
  const allPosts = await fetchPostsByFeaturedMedia(OLD_MEDIA_ID);
  const scopedPosts = filterByScope(allPosts);
  const items = [];
  let failed = 0;

  for (let index = 0; index < scopedPosts.length; index++) {
    const post = scopedPosts[index];
    const entry = {
      id: post.id,
      slug: post.slug,
      old_media_id: OLD_MEDIA_ID,
      new_media_id: NEW_MEDIA_ID,
    };
    if (APPLY) {
      try {
        await api('POST', `/wp/v2/posts/${post.id}`, { featured_media: NEW_MEDIA_ID });
        entry.applied = true;
      } catch (error) {
        failed++;
        entry.error = error.message;
      }
    }
    items.push(entry);
    if ((index + 1) % 20 === 0) {
      log(`Featured media migration progress: ${index + 1}/${scopedPosts.length} for ${OLD_MEDIA_ID} -> ${NEW_MEDIA_ID}`);
      await sleep(300);
    }
    if (failed >= FAIL_THRESHOLD) break;
  }

  return buildReportSkeleton({
    phase: PHASE,
    mode: DRY_RUN ? 'dry-run' : 'apply',
    scope: buildScope(),
    summary: {
      scanned: scopedPosts.length,
      flagged: scopedPosts.length,
      fixed: APPLY ? items.filter(item => item.applied && !item.error).length : 0,
      failed,
      total_old_references: allPosts.length,
      remaining_unprocessed: Math.max(allPosts.length - (getResumeOffset() + scopedPosts.length), 0),
    },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

function buildBreadcrumbHtml(title, slug) {
  const safeTitle = cleanTitle(title);
  const safeSlug = stripHtml(slug || '');
  return `<!-- wp:html -->
<nav class="rtadv-breadcrumb" aria-label="breadcrumb" style="font-size:14px;color:#666;margin-bottom:18px;">
  <a href="https://www.rtadv.com/">首頁</a>
  <span style="margin:0 6px;">›</span>
  <a href="https://www.rtadv.com/blog/">部落格</a>
  <span style="margin:0 6px;">›</span>
  <span data-slug="${safeSlug}">${safeTitle}</span>
</nav>
<!-- /wp:html -->`;
}

function buildArticleSchemaHtml(post) {
  const title = cleanTitle(post.title?.raw || post.title?.rendered || post.title || post.slug);
  const slug = post.slug || '';
  const schema = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: title,
    mainEntityOfPage: `https://www.rtadv.com/${slug}/`,
    datePublished: post.date || '',
    dateModified: post.modified || post.date || '',
    author: {
      '@type': 'Organization',
      name: '圓廣創意印刷',
    },
    publisher: {
      '@type': 'Organization',
      name: '圓廣創意印刷',
      url: 'https://www.rtadv.com',
    },
  };
  return `<!-- wp:html -->
<script type="application/ld+json">${JSON.stringify(schema)}</script>
<!-- /wp:html -->`;
}

function buildPostSnapshot(item) {
  return {
    id: item.id,
    slug: item.slug,
    title: cleanTitle(item.title?.raw || item.title?.rendered),
    modified: item.modified,
    content_hash: hashText(item.content?.raw || ''),
    meta_hash: hashText(JSON.stringify(item.meta || {})),
  };
}

function buildMediaSnapshot(item) {
  return {
    id: item.id,
    source_url: item.source_url,
    post: item.post,
    alt_text: item.alt_text || '',
    title: stripHtml(item.title?.raw || item.title?.rendered || ''),
    caption: stripHtml(item.caption?.raw || item.caption?.rendered || ''),
    description: stripHtml(item.description?.raw || item.description?.rendered || ''),
    meta_hash: hashText(JSON.stringify({
      alt_text: item.alt_text || '',
      title: item.title?.raw || item.title?.rendered || '',
      caption: item.caption?.raw || item.caption?.rendered || '',
      description: item.description?.raw || item.description?.rendered || '',
    })),
  };
}

async function checkSourceUrl(sourceUrl) {
  try {
    const res = await fetch(sourceUrl, { method: 'HEAD', redirect: 'follow' });
    const size = parseInt(res.headers.get('content-length') || '0', 10);
    return { ok: res.ok, status: res.status, size };
  } catch {
    return { ok: false, status: null, size: 0 };
  }
}

async function phaseScan(runId) {
  const postReport = await phaseScanPosts();
  const mediaReport = await phaseScanMedia();
  return buildReportSkeleton({
    phase: PHASE,
    mode: DRY_RUN ? 'dry-run' : 'apply',
    scope: buildScope(),
    summary: {
      scanned: (postReport.summary.scanned || 0) + (mediaReport.summary.scanned || 0),
      flagged: (postReport.summary.flagged || 0) + (mediaReport.summary.flagged || 0),
      fixed: 0,
      failed: 0,
    },
    items: [...postReport.items, ...mediaReport.items],
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseScanPosts() {
  const posts = filterByScope(await fetchPosts(true));
  const items = [];
  for (let index = 0; index < posts.length; index++) {
    const post = posts[index];
    const meta = post.meta || {};
    const inline = extractInlineImageStats(post.content?.raw || post.content?.rendered || '');
    const issues = [];
    if (!meta.rank_math_focus_keyword) issues.push('missing_focus_keyword');
    if (!meta.rank_math_title) issues.push('missing_seo_title');
    if (!meta.rank_math_description) issues.push('missing_seo_description');
    if (BLOG_RULES.post.requireFeaturedMedia && !post.featured_media) issues.push('missing_featured_media');
    if (inline.totalImages < BLOG_RULES.post.minInlineImages) issues.push(`inline_images_lt_${BLOG_RULES.post.minInlineImages}`);
    if (inline.missingAlt) issues.push(`missing_inline_alt:${inline.missingAlt}`);
    if (inline.genericAlt) issues.push(`generic_inline_alt:${inline.genericAlt}`);
    if (BLOG_RULES.post.requireH2 && !inline.hasH2) issues.push('missing_h2');
    if (BLOG_RULES.post.disallowChineseSlug && hasCjk(post.slug)) issues.push('slug_contains_cjk');
    if (issues.length) {
      items.push({ type: 'post', id: post.id, slug: post.slug, title: cleanTitle(post.title?.raw || post.title?.rendered), issues });
    }
    if ((index + 1) % 20 === 0) log(`Scanned posts: ${index + 1}/${posts.length}`);
  }
  return buildReportSkeleton({
    phase: PHASE === 'scan' ? 'scan-posts' : PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: { scanned: posts.length, flagged: items.length, fixed: 0, failed: 0 },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseScanMedia() {
  const posts = await fetchPosts(false);
  const parentTitleMap = new Map(posts.map(post => [post.id, cleanTitle(post.title?.raw || post.title?.rendered)]));
  const media = filterByScope(await fetchMedia());
  const items = [];

  for (let index = 0; index < media.length; index++) {
    const item = media[index];
    const issues = [];
    const localSize = item.media_details?.filesize || 0;
    let state = { ok: true, status: null, size: localSize };

    if (DEEP_MEDIA_CHECK) {
      state = await checkSourceUrl(item.source_url);
      if (!state.ok || state.size === 0) issues.push('empty_or_unreachable_file');
      if (state.size > BLOG_RULES.media.maxBytes) issues.push('oversized_media');
    } else {
      if (localSize === 0) issues.push('possible_empty_file');
      if (localSize > BLOG_RULES.media.maxBytes) issues.push('possible_oversized_media');
    }

    if (!item.alt_text) issues.push('missing_alt_text');
    if (!stripHtml(item.title?.raw || item.title?.rendered)) issues.push('missing_title');
    if (!stripHtml(item.caption?.raw || item.caption?.rendered)) issues.push('missing_caption');
    if (!stripHtml(item.description?.raw || item.description?.rendered)) issues.push('missing_description');
    if (!item.post) issues.push('orphaned_media');

    if (issues.length) {
      items.push({
        type: 'media',
        id: item.id,
        post: item.post,
        parent_title: parentTitleMap.get(item.post) || '',
        issues,
        size: state.size,
      });
    }

    if ((index + 1) % 20 === 0) log(`Scanned media: ${index + 1}/${media.length}`);
    if (DEEP_MEDIA_CHECK && (index + 1) % 20 === 0) await sleep(500);
  }

  return buildReportSkeleton({
    phase: PHASE === 'scan' ? 'scan-media' : PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: { scanned: media.length, flagged: items.length, fixed: 0, failed: 0 },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseSeoAudit() {
  const ids = ONLY || ID_FILE ? [...(loadIdFilter() || new Set()), ...(ONLY ? [ONLY] : [])] : null;
  const rows = await fetchSeoAuditResults(ids);
  return phaseSeoAuditForRows(filterSeoAuditRows(rows));
}

function filterSeoAuditRows(rows) {
  let scoped = rows;
  const ids = loadIdFilter();
  if (ONLY) scoped = scoped.filter(item => item.id === ONLY);
  if (ids) scoped = scoped.filter(item => ids.has(item.id));
  if (COUNT > 0) return scoped.slice(getResumeOffset(), getResumeOffset() + COUNT);
  return scoped.slice(getResumeOffset());
}

function phaseSeoAuditForRows(rows) {
  const items = [];
  for (let index = 0; index < rows.length; index++) {
    const post = rows[index];
    const missing = [];
    if (!post.has_focus_kw) missing.push('focus_keyword');
    if (!post.has_rm_title) missing.push('seo_title');
    if (!post.has_rm_desc) missing.push('seo_description');
    if (missing.length) {
      items.push({ id: post.id, slug: post.slug, title: cleanTitle(post.title), issues: missing });
    }
    if ((index + 1) % 20 === 0) log(`SEO audited posts: ${index + 1}/${rows.length}`);
  }
  return buildReportSkeleton({
    phase: PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: { scanned: rows.length, flagged: items.length, fixed: 0, failed: 0 },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseSeoFix(runId) {
  const ids = ONLY || ID_FILE ? [...(loadIdFilter() || new Set()), ...(ONLY ? [ONLY] : [])] : null;
  const posts = filterSeoAuditRows(await fetchSeoAuditResults(ids));
  const snapshot = [];
  const items = [];
  let failed = 0;

  for (let index = 0; index < posts.length; index++) {
    const post = posts[index];
    const patch = buildSeoAuditPatch(post);
    if (!Object.keys(patch).length) continue;

    snapshot.push({
      id: post.id,
      slug: post.slug,
      title: cleanTitle(post.title),
      rm_focus_kw: post.focus_kw || '',
      rm_title: post.rm_title || '',
      rm_desc: post.rm_desc || '',
      meta_hash: hashText(JSON.stringify({ focus_kw: post.focus_kw || '', title: post.rm_title || '', desc: post.rm_desc || '' })),
    });
    const item = { id: post.id, slug: post.slug, patch };
    if (APPLY) {
      try {
        await api('POST', '/rankmath/v1/updateMeta', { objectType: 'post', objectID: post.id, meta: patch });
      } catch (error) {
        failed++;
        item.error = error.message;
      }
    }
    items.push(item);
    if ((index + 1) % 20 === 0) await sleep(500);
    if (failed >= FAIL_THRESHOLD) break;
  }

  const snapshotPath = saveSnapshot(runId, PHASE, snapshot);
  return {
    report: buildReportSkeleton({
      phase: PHASE,
      mode: DRY_RUN ? 'dry-run' : 'apply',
      scope: buildScope(),
      summary: { scanned: posts.length, flagged: items.length, fixed: APPLY ? items.length - failed : 0, failed },
      items,
      ruleVersion: BLOG_RULES.version,
    }),
    snapshotPath,
  };
}

async function phasePostAudit() {
  const posts = filterByScope(await fetchPosts(true));
  return phasePostAuditForPosts(posts);
}

function phasePostAuditForPosts(posts) {
  const items = [];
  for (let index = 0; index < posts.length; index++) {
    const post = posts[index];
    const content = post.content?.raw || post.content?.rendered || '';
    const inline = extractInlineImageStats(content);
    const issues = [];
    if (!post.featured_media) issues.push('missing_featured_media');
    if (inline.totalImages < BLOG_RULES.post.minInlineImages) issues.push(`inline_images_lt_${BLOG_RULES.post.minInlineImages}`);
    if (inline.missingAlt) issues.push(`missing_inline_alt:${inline.missingAlt}`);
    if (inline.genericAlt) issues.push(`generic_inline_alt:${inline.genericAlt}`);
    if (!inline.hasH2) issues.push('missing_h2');
    if (!content.includes('application/ld+json')) issues.push('missing_schema');
    if (!content.includes('breadcrumb')) issues.push('missing_breadcrumb');
    if (issues.length) items.push({ id: post.id, slug: post.slug, title: cleanTitle(post.title?.raw || post.title?.rendered), issues });
    if ((index + 1) % 20 === 0) log(`Post-audit progress: ${index + 1}/${posts.length}`);
  }
  return buildReportSkeleton({
    phase: PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: { scanned: posts.length, flagged: items.length, fixed: 0, failed: 0 },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseStructureFix(runId) {
  const posts = filterByScope(await fetchPosts(true));
  const snapshot = [];
  const items = [];
  let failed = 0;

  for (let index = 0; index < posts.length; index++) {
    const post = posts[index];
    const content = post.content?.raw || post.content?.rendered || '';
    const title = cleanTitle(post.title?.raw || post.title?.rendered);
    let nextContent = content;
    const patch = {};

    if (!content.includes('breadcrumb')) {
      nextContent = `${buildBreadcrumbHtml(title, post.slug)}\n\n${nextContent}`;
      patch.breadcrumb = 'inserted';
    }

    if (!content.includes('application/ld+json')) {
      nextContent = `${nextContent}\n\n${buildArticleSchemaHtml(post)}`;
      patch.schema = 'inserted';
    }

    if (!Object.keys(patch).length) continue;

    snapshot.push(buildPostSnapshot(post));
    const entry = { id: post.id, slug: post.slug, patch };
    if (APPLY) {
      try {
        await api('POST', `/wp/v2/posts/${post.id}`, { content: nextContent });
      } catch (error) {
        failed++;
        entry.error = error.message;
      }
    }
    items.push(entry);
    if ((index + 1) % 20 === 0) await sleep(500);
    if (failed >= FAIL_THRESHOLD) break;
  }

  const snapshotPath = saveSnapshot(runId, PHASE, snapshot);
  return {
    report: buildReportSkeleton({
      phase: PHASE,
      mode: DRY_RUN ? 'dry-run' : 'apply',
      scope: buildScope(),
      summary: { scanned: posts.length, flagged: items.length, fixed: APPLY ? items.length - failed : 0, failed },
      items,
      ruleVersion: BLOG_RULES.version,
    }),
    snapshotPath,
  };
}

async function phaseMediaAudit() {
  const media = filterByScope(await fetchMedia());
  return phaseMediaAuditForItems(media);
}

async function phaseOrphanedMediaAnalysis() {
  const media = filterByScope(await fetchMedia());
  const orphaned = media.filter(item => !item.post);
  if (!orphaned.length) {
    return buildReportSkeleton({
      phase: PHASE,
      mode: 'dry-run',
      scope: buildScope(),
      summary: {
        scanned: media.length,
        flagged: 0,
        fixed: 0,
        failed: 0,
        orphaned_total: 0,
        true_orphaned: 0,
        featured_referenced: 0,
        content_referenced: 0,
        both_referenced: 0,
      },
      items: [],
      ruleVersion: BLOG_RULES.version,
    });
  }

  const posts = await fetchAllPostsForAnalysis(true);
  const featuredMap = new Map();
  const contentRefMap = new Map();

  for (const post of posts) {
    const content = String(post.content?.raw || post.content?.rendered || '');
    if (post.featured_media) {
      const refs = featuredMap.get(post.featured_media) || [];
      refs.push({ id: post.id, slug: post.slug });
      featuredMap.set(post.featured_media, refs);
    }
    for (const item of orphaned) {
      const byClass = content.includes(`wp-image-${item.id}`);
      const byUrl = item.source_url ? content.includes(item.source_url) : false;
      if (!byClass && !byUrl) continue;
      const refs = contentRefMap.get(item.id) || [];
      refs.push({
        id: post.id,
        slug: post.slug,
        match: byClass ? 'wp-image-id' : 'source_url',
      });
      contentRefMap.set(item.id, refs);
    }
  }

  const items = orphaned.map(item => {
    const featuredRefs = featuredMap.get(item.id) || [];
    const contentRefs = contentRefMap.get(item.id) || [];
    let classification = 'true_orphaned';
    if (featuredRefs.length && contentRefs.length) classification = 'featured_and_content_referenced';
    else if (featuredRefs.length) classification = 'featured_referenced';
    else if (contentRefs.length) classification = 'content_referenced';

    return {
      id: item.id,
      slug: item.slug,
      source_url: item.source_url,
      classification,
      featured_refs: featuredRefs,
      content_refs: contentRefs,
      issues: ['orphaned_media'],
    };
  });

  return buildReportSkeleton({
    phase: PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: {
      scanned: media.length,
      flagged: items.length,
      fixed: 0,
      failed: 0,
      orphaned_total: items.length,
      true_orphaned: items.filter(item => item.classification === 'true_orphaned').length,
      featured_referenced: items.filter(item => item.classification === 'featured_referenced').length,
      content_referenced: items.filter(item => item.classification === 'content_referenced').length,
      both_referenced: items.filter(item => item.classification === 'featured_and_content_referenced').length,
    },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseInlineImageGapAnalysis() {
  const posts = filterByScope(await fetchPosts(true));
  const targetPosts = posts.filter(post => {
    const inline = extractInlineImageStats(post.content?.raw || post.content?.rendered || '');
    return inline.totalImages < BLOG_RULES.post.minInlineImages;
  });
  const attachedMedia = await fetchMediaByParentIds(targetPosts.map(post => post.id));
  const mediaByPost = new Map();

  for (const item of attachedMedia) {
    const list = mediaByPost.get(item.post) || [];
    list.push(item);
    mediaByPost.set(item.post, list);
  }

  const items = targetPosts.map(post => {
    const content = post.content?.raw || post.content?.rendered || '';
    const inlineStats = extractInlineImageStats(content);
    const inlineRefs = extractInlineImageRefs(content);
    const inlineIds = new Set(inlineRefs.map(ref => ref.id).filter(Boolean));
    const attached = mediaByPost.get(post.id) || [];
    const reusable = attached.filter(item => item.id !== post.featured_media && !inlineIds.has(item.id));
    const needed = Math.max(BLOG_RULES.post.minInlineImages - inlineStats.totalImages, 0);
    const recommendation = reusable.length >= needed
      ? 'reuse_existing_media'
      : (attached.length > inlineStats.totalImages ? 'partial_reuse_then_generate' : 'generate_new_media');

    return {
      id: post.id,
      slug: post.slug,
      title: cleanTitle(post.title?.raw || post.title?.rendered),
      inline_image_count: inlineStats.totalImages,
      target_inline_image_count: BLOG_RULES.post.minInlineImages,
      missing_inline_images: needed,
      featured_media: post.featured_media || null,
      inline_image_ids: [...inlineIds],
      attached_media_count: attached.length,
      reusable_media_ids: reusable.slice(0, 10).map(item => item.id),
      recommendation,
      issues: [`inline_images_lt_${BLOG_RULES.post.minInlineImages}`],
    };
  });

  return buildReportSkeleton({
    phase: PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: {
      scanned: posts.length,
      flagged: items.length,
      fixed: 0,
      failed: 0,
      reusable_only: items.filter(item => item.recommendation === 'reuse_existing_media').length,
      partial_reuse_then_generate: items.filter(item => item.recommendation === 'partial_reuse_then_generate').length,
      generate_new_media: items.filter(item => item.recommendation === 'generate_new_media').length,
    },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseInlineImageGapPlan(runId) {
  const analysis = await phaseInlineImageGapAnalysis();
  const targetIds = analysis.items.map(item => item.id);
  const posts = await fetchPostsByIds(targetIds, true);
  const postMap = new Map(posts.map(post => [post.id, post]));

  const items = analysis.items.map(item => {
    const post = postMap.get(item.id) || { id: item.id, slug: item.slug, title: item.title };
    const prompts = buildImagePromptBundle(post);
    const imageCount = Math.max(item.missing_inline_images, 0);
    const roles = ['hero', 'detail', 'context'].slice(0, imageCount || 1);
    const imagePlan = roles.map(role => {
      const baseTitle = cleanTitle(post.title?.raw || post.title?.rendered || post.title || post.slug);
      const roleLabel = role === 'hero' ? '主視覺' : role === 'detail' ? '細節' : '情境';
      const alt = `${baseTitle} ${roleLabel}示意圖`.slice(0, 120);
      const caption = `${baseTitle}${roleLabel}示意。`.slice(0, 140);
      return {
        role,
        prompt: prompts[role],
        alt,
        caption,
        figure_html: buildFigureHtmlTemplate(role, alt, caption),
      };
    });

    return {
      ...item,
      image_plan: imagePlan,
    };
  });

  const artifactPath = `/tmp/rtadv-inline-image-gap-plan-${runId}.md`;
  fs.writeFileSync(artifactPath, buildInlineImagePlanScript(items));

  return {
    report: buildReportSkeleton({
      phase: PHASE,
      mode: 'dry-run',
      scope: buildScope(),
      summary: {
        scanned: analysis.summary.scanned,
        flagged: items.length,
        fixed: 0,
        failed: 0,
        posts_needing_generation: items.filter(item => item.recommendation === 'generate_new_media').length,
        posts_partial_reuse_then_generate: items.filter(item => item.recommendation === 'partial_reuse_then_generate').length,
      },
      items,
      ruleVersion: BLOG_RULES.version,
    }),
    artifactPath,
  };
}

async function phaseInlineImageGapFix(runId) {
  const plan = await phaseInlineImageGapPlan(runId);
  const targetIds = plan.report.items.map(item => item.id);
  const posts = await fetchPostsByIds(targetIds, true);
  const postMap = new Map(posts.map(post => [post.id, post]));
  const snapshot = [];
  const items = [];
  let failed = 0;

  for (let index = 0; index < plan.report.items.length; index++) {
    const item = plan.report.items[index];
    const post = postMap.get(item.id);
    if (!post) continue;

    const generatedMedia = [];
    const generatedFigures = [];
    const postTitle = cleanTitle(post.title?.raw || post.title?.rendered || post.slug);

    snapshot.push(buildPostSnapshot(post));
    const entry = { id: post.id, slug: post.slug, generated_media: [] };

    try {
      if (APPLY) await ensureAuth();
      for (const spec of item.image_plan) {
        const basename = `/tmp/rtadv-gap-${post.id}-${spec.role}`;
        let mediaRecord = null;

        if (item.recommendation === 'reuse_existing_media' || (item.recommendation === 'partial_reuse_then_generate' && spec.role === 'hero' && item.reusable_media_ids?.length)) {
          const reused = await fetchMediaByIds([item.reusable_media_ids[0]]);
          mediaRecord = reused[0] || null;
        } else if (APPLY) {
          const imagePath = generateImageAsset(spec.prompt, basename);
          const uploaded = await uploadImageAsset(
            post.id,
            imagePath,
            `${post.slug}-${spec.role}.${path.extname(imagePath).replace('.', '') || 'jpg'}`,
            spec.alt,
            spec.caption,
            `${postTitle} ${spec.role}`,
            `${postTitle} ${spec.role} image for blog inline placement.`
          );
          mediaRecord = {
            id: uploaded.id,
            source_url: uploaded.source_url,
          };
        }

        if (mediaRecord) {
          generatedMedia.push(mediaRecord);
          generatedFigures.push(buildUploadedFigureHtml(mediaRecord, spec.alt, spec.caption));
          entry.generated_media.push({ role: spec.role, id: mediaRecord.id, source_url: mediaRecord.source_url });
        }
      }

      if (APPLY && generatedFigures.length) {
        const nextContent = insertFiguresIntoContent(post.content?.raw || post.content?.rendered || '', generatedFigures);
        await api('POST', `/wp/v2/posts/${post.id}`, { content: nextContent });
      }
    } catch (error) {
      failed++;
      entry.error = error.message;
    }

    items.push(entry);
    if ((index + 1) % 5 === 0) await sleep(500);
    if (failed >= FAIL_THRESHOLD) break;
  }

  const snapshotPath = saveSnapshot(runId, PHASE, snapshot);
  return {
    report: buildReportSkeleton({
      phase: PHASE,
      mode: DRY_RUN ? 'dry-run' : 'apply',
      scope: buildScope(),
      summary: {
        scanned: plan.report.items.length,
        flagged: plan.report.items.length,
        fixed: APPLY ? items.filter(item => !item.error).length : 0,
        failed,
      },
      items,
      ruleVersion: BLOG_RULES.version,
    }),
    snapshotPath,
  };
}

async function phaseMediaAuditForItems(media) {
  const parentIds = [...new Set(media.map(item => item.post).filter(Boolean))];
  const parentTitleMap = new Map();
  if (parentIds.length) {
    for (let i = 0; i < parentIds.length; i += 100) {
      const include = parentIds.slice(i, i + 100).join(',');
      const batch = await api('GET', `/wp/v2/posts?include=${include}&per_page=100&context=edit&_fields=id,title`);
      for (const post of batch) {
        parentTitleMap.set(post.id, cleanTitle(post.title?.raw || post.title?.rendered));
      }
    }
  }

  const items = [];
  for (let index = 0; index < media.length; index++) {
    const item = media[index];
    const issues = [];
    const localSize = item.media_details?.filesize || 0;
    let state = { ok: true, status: null, size: localSize };

    if (DEEP_MEDIA_CHECK) {
      state = await checkSourceUrl(item.source_url);
      if (!state.ok || state.size === 0) issues.push('empty_or_unreachable_file');
      if (state.size > BLOG_RULES.media.maxBytes) issues.push('oversized_media');
    } else {
      if (localSize === 0) issues.push('possible_empty_file');
      if (localSize > BLOG_RULES.media.maxBytes) issues.push('possible_oversized_media');
    }

    if (!item.alt_text) issues.push('missing_alt_text');
    if (!stripHtml(item.title?.raw || item.title?.rendered)) issues.push('missing_title');
    if (!stripHtml(item.caption?.raw || item.caption?.rendered)) issues.push('missing_caption');
    if (!stripHtml(item.description?.raw || item.description?.rendered)) issues.push('missing_description');
    if (!item.post) issues.push('orphaned_media');
    if (issues.length) {
      items.push({
        id: item.id,
        post: item.post,
        slug: item.slug,
        source_url: item.source_url || null,
        parent_title: parentTitleMap.get(item.post) || '',
        size: state.size,
        issues,
      });
    }
    if ((index + 1) % 20 === 0) log(`Media-audit progress: ${index + 1}/${media.length}`);
    if (DEEP_MEDIA_CHECK && (index + 1) % 20 === 0) await sleep(500);
  }

  return buildReportSkeleton({
    phase: PHASE,
    mode: 'dry-run',
    scope: buildScope(),
    summary: { scanned: media.length, flagged: items.length, fixed: 0, failed: 0 },
    items,
    ruleVersion: BLOG_RULES.version,
  });
}

async function phaseMediaMetaFix(runId) {
  const posts = await fetchPosts(false);
  const parentTitleMap = new Map(posts.map(post => [post.id, cleanTitle(post.title?.raw || post.title?.rendered)]));
  const media = filterByScope(await fetchMedia());
  const snapshot = [];
  const items = [];
  let failed = 0;

  for (let index = 0; index < media.length; index++) {
    const item = media[index];
    const current = {
      alt_text: item.alt_text || '',
      title: stripHtml(item.title?.raw || item.title?.rendered),
      caption: stripHtml(item.caption?.raw || item.caption?.rendered),
      description: stripHtml(item.description?.raw || item.description?.rendered),
    };
    if (BLOG_RULES.media.requiredFields.every(field => current[field])) continue;

    const next = buildMediaMetaPatch(item, parentTitleMap);
    const patch = {};
    for (const field of BLOG_RULES.media.requiredFields) {
      if (!current[field]) patch[field] = next[field];
    }

    snapshot.push(buildMediaSnapshot(item));
    const entry = { id: item.id, post: item.post, patch };
    if (APPLY) {
      try {
        await api('POST', `/wp/v2/media/${item.id}`, patch);
      } catch (error) {
        failed++;
        entry.error = error.message;
      }
    }
    items.push(entry);
    if ((index + 1) % 20 === 0) await sleep(500);
    if (failed >= FAIL_THRESHOLD) break;
  }

  const snapshotPath = saveSnapshot(runId, PHASE, snapshot);
  return {
    report: buildReportSkeleton({
      phase: PHASE,
      mode: DRY_RUN ? 'dry-run' : 'apply',
      scope: buildScope(),
      summary: { scanned: media.length, flagged: items.length, fixed: APPLY ? items.length - failed : 0, failed },
      items,
      ruleVersion: BLOG_RULES.version,
    }),
    snapshotPath,
  };
}

async function phaseMediaFileFix(runId) {
  const media = filterByScope(await fetchMedia());
  const mediaMap = new Map(media.map(item => [item.id, item]));
  const audit = await phaseMediaAuditForItems(media);
  const plans = audit.items
    .filter(item => item.issues.includes('oversized_media') || item.issues.includes('empty_or_unreachable_file'))
    .map(item => buildMediaFileFixPlan({ ...mediaMap.get(item.id), ...item }, item.size || 0));
  const planPath = `/tmp/rtadv-media-file-fix-plan-${runId}.sh`;
  fs.writeFileSync(planPath, buildMediaFileFixScript(plans));

  if (!APPLY) {
    return {
      report: buildReportSkeleton({
        phase: PHASE,
        mode: 'dry-run',
        scope: buildScope(),
        summary: {
          scanned: audit.summary.scanned,
          flagged: plans.length,
          fixed: 0,
          failed: 0,
          recompress_jpeg_and_replace: plans.filter(item => item.action === 'recompress_jpeg_and_replace').length,
          convert_png_to_jpeg_and_replace: plans.filter(item => item.action === 'convert_png_to_jpeg_and_replace').length,
          convert_or_regenerate_webp: plans.filter(item => item.action === 'convert_or_regenerate_webp').length,
          manual_review: plans.filter(item => item.action === 'manual_review').length,
        },
        items: plans,
        ruleVersion: BLOG_RULES.version,
      }),
      planPath,
    };
  }

  const items = [];
  let failed = 0;
  for (let index = 0; index < plans.length; index++) {
    const plan = plans[index];
    const entry = { ...plan };
    try {
      if (plan.action === 'restore_from_derivative') {
        const candidates = plan.derivative_candidates || [];
        let restoredSize = 0;
        let restoredCandidate = null;
        for (const candidate of candidates) {
          if (!candidate?.remote_path) continue;
          try {
            ftpDownloadFile(candidate.remote_path, plan.download_path);
            restoredSize = fs.existsSync(plan.download_path) ? fs.statSync(plan.download_path).size : 0;
            if (restoredSize > 0) {
              restoredCandidate = candidate;
              break;
            }
          } catch {}
        }
        if (!restoredCandidate || !restoredSize) throw new Error('No non-empty derivative candidate could be restored');
        try {
          ftpReplaceFile(plan.download_path, plan.remote_path);
          entry.applied = true;
          entry.restore_mode = 'ftp_replace';
          entry.restore_source = restoredCandidate.file;
          entry.new_size = restoredSize;
          entry.over_limit_after = restoredSize > BLOG_RULES.media.maxBytes;
          if (entry.over_limit_after) entry.warning = 'restored_derivative_still_over_limit';
        } catch (ftpError) {
          const original = mediaMap.get(plan.id) || {};
          const basename = String(plan.source_url || '').split('/').pop() || `media-${plan.id}.webp`;
          const replacementName = basename.replace(/(\.[^.]+)$/i, '-restored$1');
          log(`FTP replace failed for media ${plan.id}; falling back to WP replacement upload`);
          const replacement = await uploadMediaBinary(plan.download_path, replacementName);
          log(`Created replacement media ${replacement.id} for broken media ${plan.id}`);
          await api('POST', `/wp/v2/media/${replacement.id}`, {
            alt_text: original.alt_text || '',
            title: stripHtml(original.title?.raw || original.title?.rendered) || cleanTitle(replacementName),
            caption: stripHtml(original.caption?.raw || original.caption?.rendered) || '',
            description: stripHtml(original.description?.raw || original.description?.rendered) || '',
          });
          log(`Updated replacement media metadata ${replacement.id}; updating featured_media references from ${plan.id}`);
          const updatedPosts = await replaceFeaturedMediaReferences(plan.id, replacement.id);
          entry.applied = true;
          entry.restore_mode = 'wp_replacement_media';
          entry.restore_source = restoredCandidate.file;
          entry.replacement_media_id = replacement.id;
          entry.replaced_featured_posts = updatedPosts;
          entry.new_size = restoredSize;
          entry.over_limit_after = restoredSize > BLOG_RULES.media.maxBytes;
          entry.ftp_error = ftpError.message;
          if (entry.over_limit_after) entry.warning = 'restored_derivative_still_over_limit';
        }
        try { fs.rmSync(plan.download_path, { force: true }); } catch {}
      } else {
        if (plan.action !== 'convert_or_regenerate_webp') {
          throw new Error(`Apply currently supports restore_from_derivative and convert_or_regenerate_webp only, got ${plan.action}`);
        }
        await downloadFile(plan.source_url, plan.download_path);
        const result = compressWebpWithPillow(plan.download_path, plan.optimized_path.replace(/\.jpg$/i, '.webp'), BLOG_RULES.media.maxBytes);
        const finalPath = plan.optimized_path.replace(/\.jpg$/i, '.webp');
        ftpReplaceFile(finalPath, plan.remote_path);
        entry.applied = true;
        entry.new_size = result.size;
        entry.quality = result.quality;
        entry.over_limit_after = result.overLimit;
        if (entry.over_limit_after) entry.warning = 'compressed_but_still_over_limit';
        try { fs.rmSync(plan.download_path, { force: true }); } catch {}
        try { fs.rmSync(finalPath, { force: true }); } catch {}
      }
    } catch (error) {
      failed++;
      entry.error = error.message;
    }
    items.push(entry);
    if ((index + 1) % 5 === 0) await sleep(500);
    if (failed >= FAIL_THRESHOLD) break;
  }

  return {
    report: buildReportSkeleton({
      phase: PHASE,
      mode: DRY_RUN ? 'dry-run' : 'apply',
      scope: buildScope(),
      summary: {
        scanned: audit.summary.scanned,
        flagged: items.length,
        fixed: items.filter(item => item.applied && !item.error).length,
        failed,
        recompress_jpeg_and_replace: items.filter(item => item.action === 'recompress_jpeg_and_replace').length,
        convert_png_to_jpeg_and_replace: items.filter(item => item.action === 'convert_png_to_jpeg_and_replace').length,
        convert_or_regenerate_webp: items.filter(item => item.action === 'convert_or_regenerate_webp').length,
        manual_review: items.filter(item => item.action === 'manual_review').length,
      },
      items,
      ruleVersion: BLOG_RULES.version,
    }),
    planPath,
  };
}

async function phaseVerify() {
  const progress = readJson(PROGRESS_FILE, {});
  const targetPhase = VERIFY_PHASE || progress.last_run?.phase;
  if (!targetPhase) throw new Error('No previous phase found for verify');
  if (!['seo-fix', 'media-meta-fix', 'structure-fix', 'inline-image-gap-fix', 'featured-media-migration'].includes(targetPhase)) {
    throw new Error(`Verify is currently implemented for seo-fix, media-meta-fix, structure-fix, inline-image-gap-fix, and featured-media-migration only, got ${targetPhase}`);
  }

  if (targetPhase === 'seo-fix') {
    const targetIds = new Set((progress.last_run?.items || []).map(item => item.id));
    const posts = await fetchSeoAuditResults([...targetIds]);
    const audit = phaseSeoAuditForRows(posts.filter(item => targetIds.has(item.id)));
    audit.phase = 'verify';
    audit.summary = { scanned: targetIds.size, flagged: audit.items.length, fixed: targetIds.size - audit.items.length, failed: 0 };
    return audit;
  }

  if (targetPhase === 'structure-fix') {
    const targetIds = new Set((progress.last_run?.items || []).map(item => item.id));
    const posts = await fetchPostsByIds([...targetIds], true);
    const audit = phasePostAuditForPosts(posts.filter(item => targetIds.has(item.id)));
    audit.phase = 'verify';
    audit.items = audit.items.filter(item =>
      item.issues.includes('missing_breadcrumb') || item.issues.includes('missing_schema')
    );
    audit.summary = {
      scanned: targetIds.size,
      flagged: audit.items.length,
      fixed: targetIds.size - audit.items.length,
      failed: 0,
    };
    return audit;
  }

  if (targetPhase === 'inline-image-gap-fix') {
    const targetIds = new Set((progress.last_run?.items || []).map(item => item.id));
    const posts = await fetchPostsByIds([...targetIds], true);
    const audit = phasePostAuditForPosts(posts.filter(item => targetIds.has(item.id)));
    audit.phase = 'verify';
    audit.items = audit.items.filter(item => item.issues.includes(`inline_images_lt_${BLOG_RULES.post.minInlineImages}`));
    audit.summary = {
      scanned: targetIds.size,
      flagged: audit.items.length,
      fixed: targetIds.size - audit.items.length,
      failed: 0,
    };
    return audit;
  }

  if (targetPhase === 'featured-media-migration') {
    const oldMediaId = progress.last_run?.scope?.old_media_id;
    const newMediaId = progress.last_run?.scope?.new_media_id;
    const targetIds = new Set((progress.last_run?.items || []).map(item => item.id));
    const posts = await fetchPostsByIds([...targetIds], false);
    const flagged = posts
      .filter(post => post.featured_media === oldMediaId || (newMediaId && post.featured_media !== newMediaId))
      .map(post => ({
        id: post.id,
        slug: post.slug,
        old_media_id: oldMediaId,
        current_featured_media: post.featured_media || null,
      }));
    return buildReportSkeleton({
      phase: 'verify',
      mode: 'dry-run',
      scope: progress.last_run?.scope || buildScope(),
      summary: {
        scanned: targetIds.size,
        flagged: flagged.length,
        fixed: targetIds.size - flagged.length,
        failed: 0,
      },
      items: flagged,
      ruleVersion: BLOG_RULES.version,
    });
  }

  const targetIds = new Set((progress.last_run?.items || []).map(item => item.id));
  const media = await fetchMediaByIds([...targetIds]);
  const audit = await phaseMediaAuditForItems(media.filter(item => targetIds.has(item.id)));
  audit.phase = 'verify';
  audit.summary = { scanned: targetIds.size, flagged: audit.items.length, fixed: targetIds.size - audit.items.length, failed: 0 };
  return audit;
}

function buildManifest(runId, startedAt, report, reportPath, snapshotPath = null, artifactPath = null) {
  return {
    run_id: runId,
    phase: PHASE,
    mode: DRY_RUN ? 'dry-run' : 'apply',
    rule_version: BLOG_RULES.version,
    started_at: startedAt,
    finished_at: new Date().toISOString(),
    scope: report.scope,
    summary: report.summary,
    report_path: reportPath,
    snapshot_path: snapshotPath,
    artifact_path: artifactPath,
  };
}

async function run() {
  const runId = createRunId(PHASE);
  const outputPath = args.output || buildReportPath(runId, PHASE);
  const startedAt = new Date().toISOString();
  log(`Starting ${PHASE} (${DRY_RUN ? 'dry-run' : 'apply'})`);
  let report;
  let snapshotPath = null;
  let artifactPath = null;

  if (!IMPLEMENTED_PHASES.has(PHASE)) {
    report = buildReportSkeleton({
      phase: PHASE,
      mode: DRY_RUN ? 'dry-run' : 'apply',
      scope: buildScope(),
      summary: { scanned: 0, flagged: 0, fixed: 0, failed: 0 },
      items: [{ message: `Phase ${PHASE} is reserved but not implemented yet.` }],
      ruleVersion: BLOG_RULES.version,
    });
  } else if (PHASE === 'scan') {
    report = await phaseScan(runId);
  } else if (PHASE === 'scan-posts') {
    report = await phaseScanPosts();
  } else if (PHASE === 'scan-media') {
    report = await phaseScanMedia();
  } else if (PHASE === 'orphaned-media-analysis') {
    report = await phaseOrphanedMediaAnalysis();
  } else if (PHASE === 'inline-image-gap-analysis') {
    report = await phaseInlineImageGapAnalysis();
  } else if (PHASE === 'inline-image-gap-plan') {
    const result = await phaseInlineImageGapPlan(runId);
    report = result.report;
    artifactPath = result.artifactPath;
  } else if (PHASE === 'inline-image-gap-fix') {
    const result = await phaseInlineImageGapFix(runId);
    report = result.report;
    snapshotPath = result.snapshotPath;
  } else if (PHASE === 'seo-audit') {
    report = await phaseSeoAudit();
  } else if (PHASE === 'seo-fix') {
    const result = await phaseSeoFix(runId);
    report = result.report;
    snapshotPath = result.snapshotPath;
  } else if (PHASE === 'post-audit') {
    report = await phasePostAudit();
  } else if (PHASE === 'structure-fix') {
    const result = await phaseStructureFix(runId);
    report = result.report;
    snapshotPath = result.snapshotPath;
  } else if (PHASE === 'media-audit') {
    report = await phaseMediaAudit();
  } else if (PHASE === 'media-meta-fix') {
    const result = await phaseMediaMetaFix(runId);
    report = result.report;
    snapshotPath = result.snapshotPath;
  } else if (PHASE === 'media-file-fix') {
    const result = await phaseMediaFileFix(runId);
    report = result.report;
    artifactPath = result.planPath;
  } else if (PHASE === 'featured-media-migration') {
    report = await phaseFeaturedMediaMigration();
  } else if (PHASE === 'verify') {
    report = await phaseVerify();
  }

  writeJson(outputPath, report);
  const manifest = buildManifest(runId, startedAt, report, outputPath, snapshotPath, artifactPath);
  const manifestPath = saveManifest(manifest);
  saveProgress({
    last_run: {
      phase: PHASE,
      mode: manifest.mode,
      next_index: getResumeOffset() + (COUNT || report.summary.scanned || 0),
      items: report.items,
      manifest_path: manifestPath,
      report_path: outputPath,
      artifact_path: artifactPath,
    },
  });

  log(`Finished ${PHASE}: report=${outputPath}`);
  console.log(JSON.stringify({ ok: true, phase: PHASE, report_path: outputPath, manifest_path: manifestPath, snapshot_path: snapshotPath, artifact_path: artifactPath }, null, 2));
}

run().catch(error => {
  console.error(`✗ ${error.message}`);
  process.exit(1);
});
