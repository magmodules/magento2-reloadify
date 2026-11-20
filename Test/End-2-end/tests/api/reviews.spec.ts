/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { byId, list } from '../../support/Reloadify';
import { state } from '../../support/State';

/**
 * Product reviews of a store view, approved and pending, with the score of their rating votes as a
 * percentage and the reviewer's profile when a customer wrote it.
 */
test.describe('Reviews', () => {
  const reviewsPath = () => `V1/reloadify/language/${state().storeId}/reviews`;

  test('an approved customer review carries its score, product and profile', async ({ request }) => {
    const s = state();
    const review = byId(await list(request, reviewsPath()), s.reviews.approved.id, 'approved review');

    expect(review.visible).toBe(true);
    expect(Number(review.score)).toBe(s.reviews.approved.percent);
    expect(Number(review.product_id)).toBe(s.simple.id);
    expect(Number(review.profile.id)).toBe(s.customers.full.id);
    expect(review.profile.email).toBe(s.customers.full.email);
  });

  test('a pending guest review is listed as not visible', async ({ request }) => {
    const s = state();
    const review = byId(await list(request, reviewsPath()), s.reviews.pending.id, 'pending review');
    expect(review.visible).toBe(false);
    expect(Number(review.product_id)).toBe(s.simple.id);
  });

  test('the single review route answers with that review only', async ({ request }) => {
    const s = state();
    const reviews = await list(request, `V1/reloadify/review/${s.reviews.approved.id}`);
    expect(reviews.map((review) => Number(review.id))).toEqual([s.reviews.approved.id]);
  });
});
