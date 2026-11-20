/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { admin, byId, list } from '../../support/Reloadify';
import { Rest } from '../../support/Rest';
import { state } from '../../support/State';
import { EMAIL, configurableItem, placeGuestOrder, salableQuantity } from '../../support/Shop';

/**
 * Variants are the children of configurable, grouped and bundle products. Each carries the id of
 * its parent and a stock level, which has to equal what Magento's inventory reports as salable for
 * the stock of the store view's website, open-order reservations included.
 */
test.describe('Variants', () => {
  const variantsPath = () => `V1/reloadify/language/${state().storeId}/variants`;

  test('a configurable child carries the configurable as its product_id', async ({ request }) => {
    const s = state();
    const variants = await list(request, variantsPath());
    for (const child of s.configurable.children) {
      const variant = byId(variants, child.id, 'configurable child');
      expect(Number(variant.product_id)).toBe(s.configurable.id);
      expect(variant.article_code).toBe(child.sku);
    }
  });

  test('a bundle part carries the bundle as its product_id', async ({ request }) => {
    const s = state();
    const variant = byId(await list(request, variantsPath()), s.bundle.part.id, 'bundle part');
    expect(Number(variant.product_id)).toBe(s.bundle.id);
  });

  test('a product that is nobody\'s child is not a variant', async ({ request }) => {
    const ids = (await list(request, variantsPath())).map((variant) => Number(variant.id));
    expect(ids).not.toContain(state().simple.id);
  });

  test('stock_level equals the salable quantity Magento reports', async ({ request }) => {
    const s = state();
    const variants = await list(request, variantsPath());
    for (const product of [...s.configurable.children, s.bundle.part]) {
      const variant = byId(variants, product.id, product.sku);
      expect(Number(variant.stock_level), product.sku).toBe(await salableQuantity(admin(request), product.sku));
    }
  });

  test('stock_level drops by the ordered quantity while the order is open', async ({ request }) => {
    const s = state();
    const child = s.configurable.children[1];
    const variantPath = `V1/reloadify/language/${s.storeId}/variant/${child.id}`;
    const before = Number(byId(await list(request, variantPath), child.id, child.sku).stock_level);

    await placeGuestOrder(
      new Rest(request, null),
      [configurableItem(s.configurable.sku, s.configurable.colorAttributeId, child.colorValue, 2)],
      EMAIL('reservation')
    );

    const after = Number(byId(await list(request, variantPath), child.id, child.sku).stock_level);
    expect(after).toBe(before - 2);
    expect(after).toBe(await salableQuantity(admin(request), child.sku));
  });

  test('the single variant route answers with that variant only', async ({ request }) => {
    const s = state();
    const child = s.configurable.children[0];
    const variants = await list(request, `V1/reloadify/language/${s.storeId}/variant/${child.id}`);
    expect(variants.map((variant) => Number(variant.id))).toEqual([child.id]);
  });
});
