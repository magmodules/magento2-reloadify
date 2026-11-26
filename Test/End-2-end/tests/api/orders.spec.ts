/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { admin, byId, list } from '../../support/Reloadify';
import { state } from '../../support/State';

/**
 * Orders of a store view: one guest order that is still open, and one customer order for a
 * configurable product that was invoiced, shipped and refunded. Totals and numbers are compared
 * with Magento's own /V1/orders/{id}.
 */
test.describe('Orders', () => {
  const ordersPath = () => `V1/reloadify/language/${state().storeId}/orders`;

  test('an open guest order', async ({ request }) => {
    const s = state();
    const order = byId(await list(request, ordersPath()), s.orders.guest.id, 'guest order');
    const core = await admin(request).ok('GET', `V1/orders/${s.orders.guest.id}`);

    expect(order.number).toBe(core.increment_id);
    expect(Number(order.price)).toBe(Number(core.grand_total));
    expect(order.has_an_account).toBe(false);
    expect(order.profile.id).toBeNull();
    expect(order.profile.email).toBe(core.customer_email);
    expect(order.paid).toBe(false);
    expect(order.deliver_date).toBe('');
    expect(order.total_refunded).toBe(0);
    expect(order.refunded_at).toBeNull();
    expect(Number(order.shopping_cart_id)).toBe(s.orders.guest.quoteId);
    expect(order.products.map((line: any) => [String(line.id), line.product_type])).toEqual([
      [String(s.simple.id), 'simple'],
    ]);
  });

  test('a customer order for a configurable: one line with the variant, paid, delivered and refunded', async ({ request }) => {
    const s = state();
    const order = byId(await list(request, ordersPath()), s.orders.customer.id, 'customer order');
    const core = await admin(request).ok('GET', `V1/orders/${s.orders.customer.id}`);
    const shipments = await admin(request).ok(
      'GET',
      `V1/shipments?searchCriteria[filterGroups][0][filters][0][field]=order_id` +
        `&searchCriteria[filterGroups][0][filters][0][value]=${s.orders.customer.id}`
    );
    const creditmemos = await admin(request).ok(
      'GET',
      `V1/creditmemos?searchCriteria[filterGroups][0][filters][0][field]=order_id` +
        `&searchCriteria[filterGroups][0][filters][0][value]=${s.orders.customer.id}`
    );

    expect(order.number).toBe(core.increment_id);
    expect(Number(order.price)).toBe(Number(core.grand_total));
    expect(order.has_an_account).toBe(true);
    expect(String(order.profile.id)).toBe(String(s.customers.full.id));
    expect(order.profile.email).toBe(s.customers.full.email);
    expect(order.paid).toBe(true);
    expect(order.deliver_date).toBe(shipments.items[0].created_at);
    expect(order.total_refunded).toBe(Number(core.total_refunded));
    expect(order.total_refunded).toBeGreaterThan(0);
    expect(order.refunded_at).toBe(creditmemos.items[0].created_at);

    expect(order.products).toHaveLength(1);
    const [line] = order.products;
    expect(String(line.id)).toBe(String(s.configurable.id));
    expect(line.product_type).toBe('configurable');
    expect(String(line.variant_id)).toBe(String(s.configurable.children[0].id));
  });

  test('the single order route answers with that order only', async ({ request }) => {
    const s = state();
    const orders = await list(request, `V1/reloadify/order/${s.orders.customer.id}`);
    expect(orders.map((order) => Number(order.id))).toEqual([s.orders.customer.id]);
  });
});
