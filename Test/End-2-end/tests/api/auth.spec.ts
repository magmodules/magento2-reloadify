/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { Rest } from '../../support/Rest';
import { routes } from '../../support/Reloadify';
import { state } from '../../support/State';
import { deleteConfig, setConfig } from '../../support/Container';

const BEARER_SETTING = 'oauth/consumer/enable_integration_as_bearer';

/**
 * Every Reloadify route except restore-cart requires Magmodules_Reloadify::webapi. Without a token,
 * and with the token of an integration that lacks that resource, Magento answers 401.
 *
 * Reloadify connects with the integration's access token as a Bearer token. Since Magento 2.4.4
 * an integration token is only accepted that way when Stores > Configuration > Services > OAuth >
 * "Allow OAuth Access Tokens to be used as standalone Bearer tokens" is on; configure-reloadify.sh
 * switches it on, and the last test here shows what happens on a shop where it is off.
 */
test.describe('Authentication', () => {
  const protectedRoutes = () => routes().filter((route) => route.method === 'GET');

  test('every GET route answers 401 without a token', async ({ request }) => {
    const anonymous = new Rest(request, null);
    const answers: string[] = [];
    for (const route of protectedRoutes()) {
      const response = await anonymous.get(route.path);
      answers.push(`${route.route}: ${response.status}`);
    }
    expect(answers).toEqual(protectedRoutes().map((route) => `${route.route}: 401`));
  });

  test('every GET route answers 401 for an integration without the Reloadify resource', async ({ request }) => {
    const noAccess = new Rest(request, state().tokens.noAccess);
    const answers: string[] = [];
    for (const route of protectedRoutes()) {
      const response = await noAccess.get(route.path);
      answers.push(`${route.route}: ${response.status}`);
    }
    expect(answers).toEqual(protectedRoutes().map((route) => `${route.route}: 401`));
  });

  test('every GET route answers 200 with the token from bin/magento reloadify:integration', async ({ request }) => {
    const reloadify = new Rest(request, state().tokens.reloadify);
    const answers: string[] = [];
    for (const route of protectedRoutes()) {
      const response = await reloadify.get(route.path);
      answers.push(`${route.route}: ${response.status}`);
    }
    expect(answers).toEqual(protectedRoutes().map((route) => `${route.route}: 200`));
  });

  test('restore-cart is the only anonymous route', async ({ request }) => {
    const response = await new Rest(request, null).post('V1/reloadify/restore-cart/not-a-cart');
    expect(response.status, response.text).not.toBe(401);
  });

  test.describe('integration token as Bearer token with the Magento default configuration', () => {
    test.afterEach(() => {
      setConfig(BEARER_SETTING, 1);
    });

    test('is refused with 401 while enable_integration_as_bearer is off', async ({ request }) => {
      deleteConfig(BEARER_SETTING);
      const response = await new Rest(request, state().tokens.reloadify).get('V1/reloadify/settings');
      expect(response.status, response.text).toBe(401);
    });
  });
});
