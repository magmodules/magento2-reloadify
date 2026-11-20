/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { admin, routes } from '../../support/Reloadify';

/**
 * Magento's REST schema is generated from the service interfaces' docblocks. A docblock type
 * Magento cannot resolve, such as a class name without its namespace, makes the whole schema
 * endpoint answer 500, for every module in the shop. The schema must answer and list every
 * Reloadify route.
 */
test.describe('REST schema', () => {
  test('/rest/all/schema?services=all answers 200 and lists every reloadify route', async ({ request }) => {
    const response = await admin(request).get('all/schema?services=all');
    expect(response.status, response.text.slice(0, 2000)).toBe(200);

    // Swagger writes path parameters as {storeId}; webapi.xml as :store_id. Compare the shape only.
    const shape = (path: string) => path.replace(/\{[^}]+\}|:[a-zA-Z_]+/g, '{}');
    const listed = Object.keys(response.body.paths || {})
      .filter((path) => path.includes('/reloadify/'))
      .map(shape)
      .sort();
    const declared = routes()
      .map((route) => shape(route.route))
      .sort();

    expect(listed).toEqual(declared);
  });
});
