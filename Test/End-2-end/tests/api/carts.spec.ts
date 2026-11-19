/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { byId, list } from '../../support/Reloadify';
import { BASE_URL } from '../../support/Rest';
import { state } from '../../support/State';

/**
 * Carts of a store view, for abandoned cart e-mails: the shopper, the products and the recovery URL
 * that puts the cart back in the shopper's session.
 */
test.describe('Carts', () => {
  const cartsPath = () => `V1/reloadify/language/${state().storeId}/carts`;

  test("a guest cart carries the guest's e-mail and name, its products and a recovery url", async ({ request }) => {
    const s = state();
    const cart = byId(await list(request, cartsPath()), s.carts.guest.id, 'guest cart');

    expect(cart.profile.id).toBeNull();
    expect(cart.profile.email).toBe(s.carts.guest.email);
    expect(cart.profile.first_name).toBe('Rel');
    expect(cart.profile.last_name).toBe('Oadify');
    expect(cart.product_ids.map((line: any) => [String(line.id), Number(line.quantity)])).toEqual([
      [String(s.simple.id), 2],
    ]);
    expect(cart.recovery_url).toMatch(new RegExp(`^${BASE_URL.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}reloadify/cart/restore/\\?id=.+`));
  });

  test("a customer cart carries the customer's profile", async ({ request }) => {
    const s = state();
    const cart = byId(await list(request, cartsPath()), s.carts.customer.id, 'customer cart');

    expect(String(cart.profile.id)).toBe(String(s.customers.minimal.id));
    expect(cart.profile.email).toBe(s.customers.minimal.email);
    expect(cart.profile.last_name).toBe('Minimal');
    expect(cart.product_ids.map((line: any) => String(line.id))).toEqual([String(s.simple.id)]);
  });

  test('the single cart route answers with that cart only', async ({ request }) => {
    const s = state();
    const carts = await list(request, `V1/reloadify/cart/${s.carts.guest.id}`);
    expect(carts.map((cart) => Number(cart.id))).toEqual([s.carts.guest.id]);
  });
});
