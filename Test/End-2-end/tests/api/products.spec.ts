/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { admin, byId, list } from '../../support/Reloadify';
import { fixture } from '../../support/Container';
import { BASE_URL } from '../../support/Rest';
import { state } from '../../support/State';

/**
 * The products route lists the products visible in the catalog for one store view. Each one is
 * compared with what Magento itself says about it: the configurable's children come from
 * /V1/configurable-products/{sku}/children, prices from /V1/products/{sku}.
 */
test.describe('Products', () => {
  const productsPath = () => `V1/reloadify/language/${state().storeId}/products`;

  test('a simple product carries its price, url, sku, visibility, categories and reviews', async ({ request }) => {
    const s = state();
    const product = byId(await list(request, productsPath()), s.simple.id, 'simple product');
    const core = await admin(request).ok('GET', `all/V1/products/${s.simple.sku}`);

    expect(product.product_type).toBe('simple');
    expect(product.sku).toBe(s.simple.sku);
    expect(Number(product.price)).toBe(Number(core.price));
    // No tax rate matches the shop's default destination, so both store view prices are the price.
    expect(Number(product.price_incl)).toBe(Number(core.price));
    expect(Number(product.price_excl)).toBe(Number(core.price));
    expect(product.url).toBe(`${BASE_URL}${s.simple.sku}.html`);
    expect(product.visible).toBe(true);
    expect(product.variant_ids).toEqual([]);
    expect(product.category_ids.map(String)).toContain(String(s.categoryId));
    expect(product.review_ids.map(Number).sort()).toEqual([s.reviews.approved.id, s.reviews.pending.id].sort());
  });

  test("a configurable product's variant_ids are its children in Magento", async ({ request }) => {
    const s = state();
    const product = byId(await list(request, productsPath()), s.configurable.id, 'configurable product');
    const children = (await admin(request).ok(
      'GET',
      `all/V1/configurable-products/${s.configurable.sku}/children`
    )) as Array<{ id: number }>;

    expect(product.product_type).toBe('configurable');
    expect(product.visible).toBe(true);
    expect(product.variant_ids.map(Number).sort()).toEqual(children.map((child) => Number(child.id)).sort());
    expect(product.category_ids.map(String)).toContain(String(s.categoryId));
  });

  test('a bundle lists its part as a variant', async ({ request }) => {
    const s = state();
    const product = byId(await list(request, productsPath()), s.bundle.id, 'bundle product');
    expect(product.product_type).toBe('bundle');
    expect(product.variant_ids.map(Number)).toEqual([s.bundle.part.id]);
  });

  test('products not visible on their own (configurable children) are not listed', async ({ request }) => {
    const ids = (await list(request, productsPath())).map((product) => Number(product.id));
    for (const child of state().configurable.children) {
      expect(ids).not.toContain(child.id);
    }
  });

  test('the single product route answers with that product only', async ({ request }) => {
    const s = state();
    const products = await list(request, `V1/reloadify/language/${s.storeId}/product/${s.simple.id}`);
    expect(products.map((product) => Number(product.id))).toEqual([s.simple.id]);
  });

  test('an unknown product id answers an empty list, not an error', async ({ request }) => {
    const products = await list(request, `V1/reloadify/language/${state().storeId}/product/999999999`);
    expect(products).toEqual([]);
  });
});

/**
 * products-delta is meant to return only the products changed since Reloadify's previous products
 * request. It finds that moment in reloadify_request_log, and nothing writes to that table any
 * more, so the delta filter is never applied. Marked as an expected failure until the intended
 * behaviour is decided; Playwright reports it when it starts passing.
 */
test.describe('Products delta', () => {
  test.fail('products-delta returns fewer products than the full export after a products request', async ({ request }) => {
    const s = state();
    await list(request, `V1/reloadify/language/${s.storeId}/products`);
    const delta = await list(request, `V1/reloadify/language/${s.storeId}/products-delta`);
    const full = await list(request, `V1/reloadify/language/${s.storeId}/products`);
    const logged = fixture<{ count: number }>('request-log-count').count;

    test.info().annotations.push({
      type: 'finding',
      description: `reloadify_request_log rows: ${logged}; products-delta: ${delta.length}; products: ${full.length}`,
    });
    expect(logged, 'requests are written to reloadify_request_log').toBeGreaterThan(0);
    expect(delta.length).toBeLessThan(full.length);
  });
});
