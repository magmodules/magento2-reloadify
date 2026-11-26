/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { byId, list } from '../../support/Reloadify';
import { state } from '../../support/State';

/**
 * Profiles are the customer accounts of the store view's website, with their default billing
 * address. A customer without gender, or with an address in a country without regions, is
 * answered like any other, not dropped or turned into an error.
 */
test.describe('Profiles', () => {
  const profilesPath = () => `V1/reloadify/language/${state().storeId}/profiles`;

  test('a customer with gender and a Californian address', async ({ request }) => {
    const s = state();
    const profile = byId(await list(request, profilesPath()), s.customers.full.id, 'full customer');

    expect(profile.email).toBe(s.customers.full.email);
    expect(profile.has_an_account).toBe(true);
    expect(profile.gender).toBe('Male');
    expect(profile.birthdate).toBe('1990-01-02');
    expect(profile.subscribed_to_newsletter).toBe(true);
    expect(profile.province).toBe('California');
    expect(profile.street).toBe('1 Main Street');
    expect(profile.city).toBe('Los Angeles');
    expect(profile.zipcode).toBe('90001');
    expect(profile.country_code).toBe('US');
    expect(profile.company_name).toBe('Reloadify E2E');
  });

  test('a customer without gender and an address without region', async ({ request }) => {
    const s = state();
    const profile = byId(await list(request, profilesPath()), s.customers.minimal.id, 'minimal customer');

    expect(profile.email).toBe(s.customers.minimal.email);
    expect(profile.gender).toBe('Not Specified');
    expect(profile.province).toBeNull();
    expect(profile.street).toBe('Dam 1');
    expect(profile.country_code).toBe('NL');
    expect(profile.subscribed_to_newsletter).toBe(false);
  });

  test('the single profile route answers with that customer only', async ({ request }) => {
    const s = state();
    const profiles = await list(request, `V1/reloadify/profile/${s.customers.minimal.id}`);
    expect(profiles.map((profile) => Number(profile.id))).toEqual([s.customers.minimal.id]);
  });
});
