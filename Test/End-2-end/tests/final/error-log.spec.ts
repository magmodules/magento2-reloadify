/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { logLinesSince } from '../../support/Container';
import { MAGENTO_LOGS, MODULE_LOGS, state } from '../../support/State';

/** Lines a spec provokes on purpose. Everything else the module logs during a run is a defect. */
const EXPECTED: RegExp[] = [];

/** A line in Magento's own logs that points at this module. */
const ABOUT_THE_MODULE = /reloadify/i;

/** What a problem looks like in Magento's logs. */
const PROBLEM = /\.(ERROR|CRITICAL|WARNING|ALERT|EMERGENCY): |Exception|Deprecated|Warning:|Notice:|Fatal/i;

/**
 * Runs after every API spec: the module's error log gained nothing during the run, and Magento's
 * exception.log and system.log gained no problem that mentions the module.
 */
test.describe('Logs', () => {
  for (const file of MODULE_LOGS) {
    test(`${file} gained nothing during the run`, () => {
      const lines = logLinesSince(file, state().logBaselines[file] ?? 0).filter(
        (line) => !EXPECTED.some((pattern) => pattern.test(line))
      );
      expect(lines, lines.join('\n')).toEqual([]);
    });
  }

  for (const file of MAGENTO_LOGS) {
    test(`${file} gained no problem that mentions the module`, () => {
      const lines = logLinesSince(file, state().logBaselines[file] ?? 0).filter(
        (line) => ABOUT_THE_MODULE.test(line) && PROBLEM.test(line) && !EXPECTED.some((pattern) => pattern.test(line))
      );
      expect(lines, lines.slice(0, 30).join('\n')).toEqual([]);
    });
  }
});
