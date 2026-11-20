/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { ROUTES, list } from '../../support/Reloadify';
import { query } from '../../support/Rest';
import { state } from '../../support/State';

/**
 * Reloadify reads every list in pages (searchCriteria[pageSize] and [currentPage]) and narrows some
 * of them with a `filter` parameter: URL-encoded JSON of field => Magento condition, where the
 * fields are the payload keys each endpoint maps to a column.
 */
test.describe('Paging', () => {
  for (const route of ROUTES.filter((item) => item.list)) {
    test(`${route.route} pages with searchCriteria`, async ({ request }) => {
      const path = route.path(state());
      const all = await list(request, path);
      expect(all.length, 'the fixtures give this list at least two items').toBeGreaterThanOrEqual(2);

      const first = await list(request, path + query({ pageSize: 1, currentPage: 1 }));
      const second = await list(request, path + query({ pageSize: 1, currentPage: 2 }));

      expect(first).toHaveLength(1);
      expect(second).toHaveLength(1);
      expect(String(second[0].id)).not.toBe(String(first[0].id));
    });
  }
});

test.describe('Filter', () => {
  const store = () => state().storeId;

  test('products filter on id', async ({ request }) => {
    const id = state().configurable.id;
    const products = await list(request, `V1/reloadify/language/${store()}/products${query({ filter: { id: { eq: id } } })}`);
    expect(products.map((product) => Number(product.id))).toEqual([id]);
  });

  test('orders filter on number', async ({ request }) => {
    const order = state().orders.customer;
    const orders = await list(
      request,
      `V1/reloadify/language/${store()}/orders${query({ filter: { number: { eq: order.incrementId } } })}`
    );
    expect(orders.map((item) => Number(item.id))).toEqual([order.id]);
  });

  test('carts filter on profile_id', async ({ request }) => {
    const s = state();
    const carts = await list(
      request,
      `V1/reloadify/language/${store()}/carts${query({ filter: { profile_id: { eq: s.customers.minimal.id } } })}`
    );
    expect(carts.map((cart) => Number(cart.id))).toContain(s.carts.customer.id);
    expect(carts.every((cart) => String(cart.profile.id) === String(s.customers.minimal.id))).toBe(true);
  });

  test('profiles filter on email', async ({ request }) => {
    const customer = state().customers.full;
    const profiles = await list(
      request,
      `V1/reloadify/language/${store()}/profiles${query({ filter: { email: customer.email } })}`
    );
    expect(profiles.map((profile) => Number(profile.id))).toEqual([customer.id]);
  });
});
