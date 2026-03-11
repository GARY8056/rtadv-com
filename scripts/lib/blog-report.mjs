import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

export const REPORT_FILE = '/tmp/rtadv-blog-report.json';
export const PROGRESS_FILE = '/tmp/rtadv-blog-progress.json';
export const LOG_FILE = '/tmp/rtadv-blog-scan.log';
export const MANIFEST_DIR = '/tmp/rtadv-blog-manifests';
export const SNAPSHOT_DIR = '/tmp/rtadv-blog-snapshots';

export function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

export function log(message) {
  const line = `[${new Date().toISOString()}] ${message}`;
  console.log(line);
  fs.appendFileSync(LOG_FILE, `${line}\n`);
}

export function writeJson(file, data) {
  ensureDir(path.dirname(file));
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);
}

export function readJson(file, fallback = null) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch {
    return fallback;
  }
}

export function createRunId(phase) {
  return `${new Date().toISOString().replace(/[:.]/g, '-')}_${phase}`;
}

export function buildReportPath(runId, phase) {
  return `/tmp/rtadv-blog-report-${phase}-${runId}.json`;
}

export function hashText(value) {
  return crypto.createHash('sha1').update(String(value || '')).digest('hex');
}

export function saveManifest(manifest) {
  ensureDir(MANIFEST_DIR);
  const file = path.join(MANIFEST_DIR, `${manifest.run_id}.json`);
  writeJson(file, manifest);
  return file;
}

export function saveSnapshot(runId, phase, snapshot) {
  ensureDir(SNAPSHOT_DIR);
  const file = path.join(SNAPSHOT_DIR, `${runId}_${phase}.json`);
  writeJson(file, snapshot);
  return file;
}

export function saveProgress(data) {
  writeJson(PROGRESS_FILE, data);
}

export function buildReportSkeleton({ phase, mode, scope, summary, items, ruleVersion }) {
  return {
    rule_version: ruleVersion,
    phase,
    mode,
    scope,
    summary,
    items,
  };
}
