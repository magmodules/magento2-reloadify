/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { list } from '../../support/Reloadify';
import { state } from '../../support/State';

/**
 * Newsletter subscribers of a store view: customers who subscribed and guests who only left an
 * e-mail address.
 */
test.describe('Subscribers', () => {
  test('a subscribed customer carries its profile id', async ({ request }) => {
    const s = state();
    const subscribers = await list(request, `V1/reloadify/language/${s.storeId}/subscribers`);
    const subscriber = subscribers.find((item) => item.email === s.customers.full.email);

    expect(subscriber, `${s.customers.full.email} is listed`).toBeTruthy();
    expect(Number(subscriber.profile_id)).toBe(s.customers.full.id);
    expect(Number(subscriber.subscribed_to_newsletter)).toBe(1);
  });

  test('a guest subscriber has no profile id', async ({ request }) => {
    const s = state();
    const subscribers = await list(request, `V1/reloadify/language/${s.storeId}/subscribers`);
    const subscriber = subscribers.find((item) => Number(item.id) === s.guestSubscriber.id);

    expect(subscriber, `subscriber ${s.guestSubscriber.id} is listed`).toBeTruthy();
    expect(subscriber.email).toBe(s.guestSubscriber.email);
    expect(subscriber.profile_id).toBeNull();
    expect(Number(subscriber.subscribed_to_newsletter)).toBe(1);
  });

  test('a customer who did not subscribe is not listed', async ({ request }) => {
    const s = state();
    const emails = (await list(request, `V1/reloadify/language/${s.storeId}/subscribers`)).map((item) => item.email);
    expect(emails).not.toContain(s.customers.minimal.email);
  });
});
