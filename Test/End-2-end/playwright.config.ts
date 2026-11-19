/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { defineConfig } from '@playwright/test';

process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

/**
 * Projects:
 *   api   - the Reloadify REST API and the cart restore link, over HTTP only (no browser)
 *   final - runs after api: the module and Magento logs gained nothing about the module
 *
 * No retries and one worker: every spec resets what it changes, so a test that only passes the
 * second time is a defect in the module or in the spec, not noise.
 */
export default defineConfig({
  globalSetup: require.resolve('./global-setup.ts'),
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  timeout: 300000,
  reporter: [['list'], ['html', { open: 'never' }], ['json', { outputFile: 'test-results.json' }]],
  use: {
    baseURL: (process.env.BASE_URL || 'https://magento.test/').replace(/\/?$/, '/'),
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: { Accept: 'application/json' },
  },
  projects: [
    { name: 'api', testMatch: /\/api\/.*\.spec\.ts/ },
    { name: 'final', testMatch: /\/final\/.*\.spec\.ts/, dependencies: ['api'] },
  ],
});
