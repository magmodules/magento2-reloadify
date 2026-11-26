/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { defineConfig, devices } from '@playwright/test';

process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';

/**
 * The baseline project drives the admin in a browser and reads the shop through `docker exec`, so
 * it only runs against a container. On a dev server (MAGENTO_ROOT) the suite is the REST API alone.
 */
const withBaseline = !process.env.MAGENTO_ROOT;
if (withBaseline) {
  process.env.MAGENTO_CONTAINER ||= 'magento-project-community-edition';
}

const json = { extraHTTPHeaders: { Accept: 'application/json' } };
const admin = { ...devices['Desktop Chrome'], storageState: '.auth/backend.json' };

/**
 * Projects:
 *   api      - the Reloadify REST API and the cart restore link, over HTTP only (no browser)
 *   setup    - admin login, writes .auth/backend.json
 *   baseline - every configuration field saved through the admin with every option on and off,
 *              every admin route answering a bare request, every CLI command still running
 *   final    - runs last: the module and Magento logs gained nothing about the module
 *
 * The baseline does not depend on the api project: a failing REST spec must not hide what the admin
 * does. It leaves the module enabled, which is all the REST specs assume about its configuration.
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
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'api', testMatch: /\/api\/.*\.spec\.ts/, use: json },
    ...(withBaseline
      ? [
          { name: 'setup', testMatch: /.*\.setup\.ts/, use: devices['Desktop Chrome'] },
          { name: 'baseline', testMatch: /\/baseline\/.*\.spec\.ts/, use: admin, dependencies: ['setup'] },
        ]
      : []),
    {
      name: 'final',
      testMatch: /\/final\/.*\.spec\.ts/,
      use: json,
      dependencies: withBaseline ? ['api', 'baseline'] : ['api'],
    },
  ],
});
