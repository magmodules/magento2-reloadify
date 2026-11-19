/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { byId, list } from '../../support/Reloadify';
import { state } from '../../support/State';

/**
 * Categories of a store view with the products assigned to them and their parent category.
 */
test.describe('Categories', () => {
  test('the fixture category lists its products and its parent', async ({ request }) => {
    const s = state();
    const category = byId(
      await list(request, `V1/reloadify/language/${s.storeId}/categories`),
      s.categoryId,
      'fixture category'
    );

    expect(category.name).toBe('Reloadify E2E');
    expect(Number(category.parent_category_id)).toBe(2);
    expect(category.product_ids.map(Number).sort()).toEqual(
      expect.arrayContaining([s.simple.id, s.configurable.id, s.bundle.id])
    );
  });

  test('the single category route answers with that category only', async ({ request }) => {
    const s = state();
    const categories = await list(request, `V1/reloadify/language/${s.storeId}/category/${s.categoryId}`);
    expect(categories.map((category) => Number(category.id))).toEqual([s.categoryId]);
  });
});
