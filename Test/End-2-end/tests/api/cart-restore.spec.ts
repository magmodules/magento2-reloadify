/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect, APIRequestContext } from '@playwright/test';
import { byId, encryptedIdFrom, list } from '../../support/Reloadify';
import { Rest } from '../../support/Rest';
import { fixture } from '../../support/Container';
import { state } from '../../support/State';

interface QuoteRow {
  entity_id: string;
  is_active: string;
}

const quoteRow = (id: number) => fixture<QuoteRow>('quote-get', { id });
const deactivate = (id: number) => fixture('quote-set', { id, data: { is_active: 0 } });

/** The recovery URL Reloadify puts in the abandoned cart e-mail for this quote. */
async function recoveryUrl(request: APIRequestContext, quoteId: number): Promise<string> {
  const carts = await list(request, `V1/reloadify/language/${state().storeId}/carts`);
  return byId(carts, quoteId, 'cart').recovery_url;
}

/**
 * The encrypted quote id is base64 and can contain "/". The encryption uses a fresh nonce every
 * time, so asking again yields another spelling of the same id; ask until one has a slash.
 */
async function encryptedIdWithSlash(request: APIRequestContext, quoteId: number): Promise<string> {
  for (let attempt = 0; attempt < 30; attempt++) {
    const id = encryptedIdFrom(await recoveryUrl(request, quoteId));
    if (id.includes('/')) {
      return id;
    }
  }
  throw new Error(`No encrypted id with a "/" for quote ${quoteId} in 30 attempts`);
}

/** GET a storefront URL without following the redirect. */
async function visit(request: APIRequestContext, url: string) {
  const response = await request.get(url, { maxRedirects: 0, failOnStatusCode: false });
  return { status: response.status(), location: response.headers()['location'] || '', body: await response.text() };
}

/**
 * The link in the abandoned cart e-mail (reloadify/cart/restore?id=...) and its Web API
 * counterpart for headless storefronts (POST /V1/reloadify/restore-cart/:encryptedId). Both put an
 * abandoned quote back in use; neither may revive a quote that already became an order, and a
 * link that is missing or broken lands on the homepage instead of an error page.
 *
 * A visitor gets the quote itself only when it is theirs. A guest opening a customer's link gets a
 * new guest quote with the same items and the customer's quote is closed: Magento's guest cart
 * endpoints refuse a quote that belongs to a customer, so the cart page would otherwise stay empty.
 */
test.describe('Cart restore link', () => {
  test('an inactive quote becomes active again and the shopper lands on the cart', async ({ request }) => {
    const quoteId = state().carts.guest.id;
    const url = await recoveryUrl(request, quoteId);
    deactivate(quoteId);

    const response = await visit(request, url);

    expect(response.status, response.body.slice(0, 500)).toBe(302);
    expect(response.location).toContain('checkout/cart');
    expect(quoteRow(quoteId).is_active).toBe('1');
  });

  test("a customer's cart opened by a guest lands on the cart and the customer's quote is closed", async ({ request }) => {
    const quoteId = state().carts.customer.id;
    const response = await visit(request, await recoveryUrl(request, quoteId));

    expect(response.status, response.body.slice(0, 500)).toBe(302);
    expect(response.location).toContain('checkout/cart');
    expect(quoteRow(quoteId).is_active).toBe('0');
  });

  test('a quote that became an order stays inactive and the shopper lands on the homepage', async ({ request }) => {
    const quoteId = state().orders.guest.quoteId;
    const response = await visit(request, await recoveryUrl(request, quoteId));

    expect(response.status, response.body.slice(0, 500)).toBe(302);
    expect(response.location).not.toContain('checkout/cart');
    expect(quoteRow(quoteId).is_active).toBe('0');
  });

  test('a link without id redirects to the homepage without an error', async ({ request }) => {
    const response = await visit(request, 'reloadify/cart/restore/');
    expect(response.status, response.body.slice(0, 500)).toBe(302);
    expect(response.location).not.toContain('checkout/cart');
  });

  test('a link with a garbage id redirects to the homepage without an error', async ({ request }) => {
    const response = await visit(request, 'reloadify/cart/restore/?id=not-an-encrypted-id');
    expect(response.status, response.body.slice(0, 500)).toBe(302);
    expect(response.location).not.toContain('checkout/cart');
  });
});

test.describe('Cart restore Web API', () => {
  const restore = (request: APIRequestContext, encryptedId: string) =>
    new Rest(request, null).post(`V1/reloadify/restore-cart/${encodeURIComponent(encryptedId)}`);

  test('an inactive quote becomes active again', async ({ request }) => {
    const quoteId = state().carts.guest.id;
    let encryptedId = encryptedIdFrom(await recoveryUrl(request, quoteId));
    for (let attempt = 0; attempt < 30 && encryptedId.includes('/'); attempt++) {
      encryptedId = encryptedIdFrom(await recoveryUrl(request, quoteId));
    }
    deactivate(quoteId);

    const response = await restore(request, encryptedId);

    expect(response.status, response.text.slice(0, 1000)).toBe(200);
    expect(Number(response.body.id)).toBe(quoteId);
    expect(quoteRow(quoteId).is_active).toBe('1');
  });

  test('an encrypted id that contains "/" is accepted in the path', async ({ request }) => {
    const quoteId = state().carts.guest.id;
    const encryptedId = await encryptedIdWithSlash(request, quoteId);
    deactivate(quoteId);

    const response = await restore(request, encryptedId);

    test.info().annotations.push({ type: 'encrypted id', description: encryptedId });
    expect(response.status, response.text.slice(0, 1000)).toBe(200);
    expect(Number(response.body.id)).toBe(quoteId);
    expect(quoteRow(quoteId).is_active).toBe('1');
  });

  test("a customer's cart restored anonymously comes back as a new guest cart with the same items", async ({ request }) => {
    const s = state();
    const quoteId = s.carts.customer.id;
    const response = await restore(request, encryptedIdFrom(await recoveryUrl(request, quoteId)));

    expect(response.status, response.text.slice(0, 1000)).toBe(200);
    expect(Number(response.body.id)).not.toBe(quoteId);
    expect(response.body.customer_is_guest).toBe(true);
    expect((response.body.items as Array<{ sku: string }>).map((item) => item.sku)).toEqual([s.simple.sku]);
    expect(quoteRow(quoteId).is_active).toBe('0');
  });

  test('a quote that became an order stays inactive', async ({ request }) => {
    const quoteId = state().orders.guest.quoteId;
    const response = await restore(request, encryptedIdFrom(await recoveryUrl(request, quoteId)));

    expect(response.status, response.text.slice(0, 1000)).toBeLessThan(500);
    expect(quoteRow(quoteId).is_active).toBe('0');
  });

  test('a garbage id is refused without a server error', async ({ request }) => {
    const response = await restore(request, 'not-an-encrypted-id');
    expect(response.status, response.text.slice(0, 1000)).toBeGreaterThanOrEqual(400);
    expect(response.status, response.text.slice(0, 1000)).toBeLessThan(500);
  });
});
