/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';
import { shellQuote } from './Shell';

/** A `core_config_data` scope. `default` is the global fallback every other scope inherits from. */
export type ConfigScope = 'default' | 'websites' | 'stores';

/**
 * Database helper that wraps Docker exec + PDO boilerplate.
 * Eliminates repeated env.php → PDO connection setup across modules.
 */
export class MagentoDb {
  constructor(private container: string) {}

  /**
   * Run arbitrary PHP with $pdo pre-initialized as a PDO connection.
   * The PHP code should use $pdo and echo its result.
   */
  query(phpBody: string): string {
    const fullPhp = `
      foreach (['/var/www/html/app/etc/env.php', '/data/app/etc/env.php'] as $p) { if (file_exists($p)) { $env = include $p; break; } }
      $db = $env['db']['connection']['default'];
      $pdo = new PDO("mysql:host={$db['host']};dbname={$db['dbname']}", $db['username'], $db['password']);
      ${phpBody}
    `;

    // Single-quoted, not `bash -lc`: the PHP is full of `$pdo`, `$env` and `$db`, and every layer
    // of shell in between would expand them into nothing. `shellQuote` is the only quoting here.
    return execSync(`docker exec ${this.container} php -r ${shellQuote(fullPhp)}`, {
      stdio: 'pipe',
      timeout: 30000,
      // The default 1 MB buffer turns a large result into a bare "spawnSync /bin/sh ENOBUFS",
      // which says nothing about the query that produced it.
      maxBuffer: 64 * 1024 * 1024,
    }).toString();
  }

  /**
   * Set a config value in core_config_data (or delete the row if value is empty, which brings back
   * the config.xml default — it does not store an admin "No").
   */
  setConfig(path: string, value: string): void {
    this.setScopedConfig(path, value, 'default', 0);
  }

  /**
   * The stored value of each of these paths in one scope, as the rows read right now.
   *
   * A path with no row is absent from the result rather than present as an empty string: at store
   * scope that is the difference between "Use Default" and a stored empty value.
   */
  readConfig(paths: string[], scope: ConfigScope = 'default', scopeId = 0): Record<string, string> {
    if (paths.length === 0) {
      return {};
    }
    const b64 = Buffer.from(JSON.stringify(paths)).toString('base64');
    const out = this.query(`
      $paths = json_decode(base64_decode('${b64}'), true);
      $in = implode(',', array_map([$pdo, 'quote'], $paths));
      $stmt = $pdo->prepare("SELECT path, value FROM core_config_data WHERE scope = ? AND scope_id = ? AND path IN ($in)");
      $stmt->execute(['${scope}', ${scopeId}]);
      echo json_encode($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
    `).trim();
    const start = out.indexOf('{');

    return start >= 0 ? (JSON.parse(out.slice(start)) as Record<string, string>) : {};
  }

  /**
   * Set a config value in a specific scope.
   *
   * Worth reaching for deliberately: a value that exists only at website or store-view scope is
   * invisible to anything reading it from the admin, where the resolved store is the admin store
   * (id 0) and therefore falls back to the `default` row. Several modules have shipped an admin
   * controller that refused to run for exactly that reason while the storefront worked fine.
   */
  setScopedConfig(path: string, value: string, scope: ConfigScope, scopeId: number): void {
    if (value === '') {
      const b64Path = Buffer.from(path).toString('base64');
      this.query(`
        $stmt = $pdo->prepare("DELETE FROM core_config_data WHERE path = ? AND scope = ? AND scope_id = ?");
        $stmt->execute([base64_decode('${b64Path}'), '${scope}', ${scopeId}]);
        echo 'deleted';
      `);
      return;
    }

    const b64Path = Buffer.from(path).toString('base64');
    const b64Value = Buffer.from(value).toString('base64');
    this.query(`
      $path = base64_decode('${b64Path}');
      $value = base64_decode('${b64Value}');
      $stmt = $pdo->prepare("INSERT INTO core_config_data (scope, scope_id, path, value) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE value = ?");
      $stmt->execute(['${scope}', ${scopeId}, $path, $value, $value]);
      echo 'ok';
    `);
  }

  /**
   * Read a config value back out of a specific scope. Returns null when no row exists there —
   * which is not the same as the effective value, since Magento would fall back to `default`.
   */
  getScopedConfig(path: string, scope: ConfigScope = 'default', scopeId = 0): string | null {
    const b64Path = Buffer.from(path).toString('base64');
    const result = this.query(`
      $stmt = $pdo->prepare("SELECT value FROM core_config_data WHERE path = ? AND scope = ? AND scope_id = ?");
      $stmt->execute([base64_decode('${b64Path}'), '${scope}', ${scopeId}]);
      $row = $stmt->fetchColumn();
      echo $row === false ? '__NULL__' : $row;
    `).trim();

    return result === '__NULL__' ? null : result;
  }

  /**
   * Delete all config rows matching a LIKE pattern, in every scope, reverting to config.xml
   * defaults. Use between tests that write scoped config: a leftover website-scope row silently
   * changes the next test's answer.
   */
  resetConfig(pathPattern: string): void {
    const b64Pattern = Buffer.from(pathPattern).toString('base64');
    this.query(`
      $pattern = base64_decode('${b64Pattern}');
      $deleted = $pdo->exec("DELETE FROM core_config_data WHERE path LIKE " . $pdo->quote($pattern));
      echo "reset:$deleted";
    `);
  }
}
