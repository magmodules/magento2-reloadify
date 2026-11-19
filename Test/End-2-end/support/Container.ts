/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';

/**
 * Where Magento runs. On a dev server set MAGENTO_ROOT to the Magento directory and every command
 * runs directly on that host. Without it, commands go through `docker exec` into MAGENTO_CONTAINER.
 */
export const LOCAL_ROOT = process.env.MAGENTO_ROOT || '';
export const CONTAINER = process.env.MAGENTO_CONTAINER || 'magento-project-community-edition';

/** Where the fixture helper is copied to, relative to the Magento root. */
const FIXTURE_SCRIPT = 'var/reloadify-e2e-fixtures.php';

/**
 * Quote a command so the shell that runs `docker exec` passes it through untouched: inside double
 * quotes every `$name` in a PHP snippet would be expanded to nothing before the container sees it.
 */
function shellQuote(command: string): string {
  return `'${command.replace(/'/g, `'\\''`)}'`;
}

/** Run a shell command where Magento runs (this host or the container) and return its stdout. */
export function inContainer(command: string, timeout = 600000): string {
  const invocation = LOCAL_ROOT
    ? `bash -c ${shellQuote(command)}`
    : `docker exec ${CONTAINER} bash -c ${shellQuote(command)}`;
  return execSync(invocation, {
    encoding: 'utf-8',
    timeout,
    maxBuffer: 64 * 1024 * 1024,
    stdio: ['ignore', 'pipe', 'pipe'],
  });
}

let root: string | undefined;

/** The Magento root: MAGENTO_ROOT on this host, or /data or /var/www/html in the container. */
export function magentoRoot(): string {
  if (root) {
    return root;
  }
  if (LOCAL_ROOT) {
    root = LOCAL_ROOT.replace(/\/$/, '');
    return root;
  }
  for (const candidate of ['/data', '/var/www/html']) {
    try {
      inContainer(`test -f ${candidate}/bin/magento`, 30000);
      root = candidate;
      return candidate;
    } catch {
      // not this one
    }
  }
  throw new Error('bin/magento not found in the container (looked in /data and /var/www/html)');
}

/** Run bin/magento with the given arguments. */
export function magento(args: string, timeout = 600000): string {
  return inContainer(`cd ${magentoRoot()} && php bin/magento ${args}`, timeout);
}

/** Copy the PHP fixture helper into the Magento root's var/; global-setup does this once per run. */
export function installFixtureScript(): void {
  const source = path.resolve(__dirname, '../fixtures/reloadify-fixtures.php');
  if (LOCAL_ROOT) {
    fs.copyFileSync(source, `${magentoRoot()}/${FIXTURE_SCRIPT}`);
    return;
  }
  execSync(`docker cp ${source} ${CONTAINER}:${magentoRoot()}/${FIXTURE_SCRIPT}`, { stdio: 'pipe' });
}

/**
 * Run one command of the PHP fixture helper. The payload travels base64 encoded so no shell layer
 * can touch it; the helper prints its JSON result on the last line.
 */
export function fixture<T = Record<string, unknown>>(command: string, payload: object = {}): T {
  const b64 = Buffer.from(JSON.stringify(payload)).toString('base64');
  let output: string;
  try {
    output = inContainer(`cd ${magentoRoot()} && php ${FIXTURE_SCRIPT} ${command} ${b64}`, 300000);
  } catch (error) {
    const stderr = (error as { stderr?: string }).stderr || '';
    throw new Error(`Fixture "${command}" failed: ${stderr.trim() || (error as Error).message}`);
  }
  const lines = output.trim().split('\n');
  return JSON.parse(lines[lines.length - 1]) as T;
}

/** Number of lines a log file under var/log has right now (0 when it does not exist). */
export function logLineCount(file: string): number {
  const out = inContainer(`f=${magentoRoot()}/var/log/${file}; [ -f "$f" ] && wc -l < "$f" || echo 0`, 30000);
  return Number(out.trim()) || 0;
}

/** The lines a log file under var/log gained after the given line count. */
export function logLinesSince(file: string, since: number): string[] {
  const out = inContainer(
    `f=${magentoRoot()}/var/log/${file}; [ -f "$f" ] && tail -n +${since + 1} "$f" || true`,
    60000
  );
  return out.split('\n').filter((line) => line.trim() !== '');
}

/** Set a configuration value and clean the config cache. */
export function setConfig(configPath: string, value: string | number): void {
  fixture('config-set', { path: configPath, value: String(value) });
}

/** Remove a configuration value, bringing back the config.xml default, and clean the config cache. */
export function deleteConfig(configPath: string): void {
  fixture('config-delete', { path: configPath });
}
