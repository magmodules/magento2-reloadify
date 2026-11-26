/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { byId, list } from '../../support/Reloadify';
import { deleteConfig, setConfig } from '../../support/Container';
import { state } from '../../support/State';

const EXCLUDE = 'magmodules_reloadify/privacy/exclude_customer_fields';

/** Every option of Privacy > Exclude Customer Fields, as the profile keys they remove. */
const PROFILE_KEYS = [
  'telephone',
  'street',
  'city',
  'zipcode',
  'province',
  'country_code',
  'company_name',
  'gender',
  'birthdate',
];

/** The order address and guest profile keys the same options remove. */
const ORDER_KEYS = ['telephone', 'street', 'city', 'postcode', 'region', 'region_id', 'country_id', 'company'];

/**
 * Privacy > Exclude Customer Fields keeps personal data the merchant does not want to share out of
 * every payload that carries it: profiles, order addresses and the guest profile of an order.
 */
test.describe('Privacy', () => {
  test.beforeEach(() => {
    deleteConfig(EXCLUDE);
  });

  test.afterEach(() => {
    deleteConfig(EXCLUDE);
  });

  test('without exclusions the fields are present', async ({ request }) => {
    const s = state();
    const profile = byId(
      await list(request, `V1/reloadify/language/${s.storeId}/profiles`),
      s.customers.full.id,
      'full customer'
    );
    for (const key of PROFILE_KEYS) {
      expect(Object.keys(profile), key).toContain(key);
    }
  });

  test('excluded fields are gone from profiles', async ({ request }) => {
    setConfig(EXCLUDE, PROFILE_KEYS.join(','));
    const s = state();
    const profile = byId(
      await list(request, `V1/reloadify/language/${s.storeId}/profiles`),
      s.customers.full.id,
      'full customer'
    );
    for (const key of PROFILE_KEYS) {
      expect(Object.keys(profile), key).not.toContain(key);
    }
    expect(profile.email).toBe(s.customers.full.email);
  });

  test('excluded fields are gone from order addresses and the guest profile', async ({ request }) => {
    setConfig(EXCLUDE, PROFILE_KEYS.join(','));
    const s = state();
    const orders = await list(request, `V1/reloadify/language/${s.storeId}/orders`);
    const guest = byId(orders, s.orders.guest.id, 'guest order');
    const customer = byId(orders, s.orders.customer.id, 'customer order');

    for (const [what, data] of [
      ['guest profile', guest.profile],
      ['guest shipping address', guest.shipping_address],
      ['guest billing address', guest.billing_address],
      ['customer shipping address', customer.shipping_address],
      ['customer billing address', customer.billing_address],
    ] as Array<[string, Record<string, unknown>]>) {
      expect(data, `${what} is present`).toBeTruthy();
      for (const key of ORDER_KEYS) {
        expect(Object.keys(data), `${what}: ${key}`).not.toContain(key);
      }
    }
    expect(guest.profile.email).toBeTruthy();
  });
});
