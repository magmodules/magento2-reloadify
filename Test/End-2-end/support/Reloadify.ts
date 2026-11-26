/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { APIRequestContext } from '@playwright/test';
import { Rest } from './Rest';
import { E2eState, state } from './State';

/** A Reloadify route as etc/webapi.xml declares it, and how to fill it in from the fixtures. */
export interface Route {
  method: 'GET' | 'POST';
  route: string;
  list: boolean;
  path: (s: E2eState) => string;
}

/**
 * Every route etc/webapi.xml declares. Static, so specs can build their tests from it before
 * global-setup has run; `path` fills in the parameters once the fixtures exist.
 */
export const ROUTES: Route[] = [
  { method: 'GET', route: '/V1/reloadify/settings', list: false, path: () => 'V1/reloadify/settings' },
  { method: 'GET', route: '/V1/reloadify/version', list: false, path: () => 'V1/reloadify/version' },
  { method: 'GET', route: '/V1/reloadify/languages', list: false, path: () => 'V1/reloadify/languages' },
  { method: 'GET', route: '/V1/reloadify/language/:entity_id', list: false, path: (s) => `V1/reloadify/language/${s.storeId}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/profiles', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/profiles` },
  { method: 'GET', route: '/V1/reloadify/profile/:entity_id', list: false, path: (s) => `V1/reloadify/profile/${s.customers.full.id}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/subscribers', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/subscribers` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/products', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/products` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/product/:entity_id', list: false, path: (s) => `V1/reloadify/language/${s.storeId}/product/${s.simple.id}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/products-delta', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/products-delta` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/variants', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/variants` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/variant/:entity_id', list: false, path: (s) => `V1/reloadify/language/${s.storeId}/variant/${s.configurable.children[0].id}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/reviews', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/reviews` },
  { method: 'GET', route: '/V1/reloadify/review/:entity_id', list: false, path: (s) => `V1/reloadify/review/${s.reviews.approved.id}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/categories', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/categories` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/category/:entity_id', list: false, path: (s) => `V1/reloadify/language/${s.storeId}/category/${s.categoryId}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/orders', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/orders` },
  { method: 'GET', route: '/V1/reloadify/order/:entity_id', list: false, path: (s) => `V1/reloadify/order/${s.orders.customer.id}` },
  { method: 'GET', route: '/V1/reloadify/language/:store_id/carts', list: true, path: (s) => `V1/reloadify/language/${s.storeId}/carts` },
  { method: 'GET', route: '/V1/reloadify/cart/:entity_id', list: false, path: (s) => `V1/reloadify/cart/${s.carts.customer.id}` },
  { method: 'POST', route: '/V1/reloadify/restore-cart/:encryptedId', list: false, path: () => 'V1/reloadify/restore-cart/x' },
];

/** The routes with their parameters filled in from the fixtures (call inside a test only). */
export function routes(): Array<Omit<Route, 'path'> & { path: string }> {
  const s = state();
  return ROUTES.map((route) => ({ ...route, path: route.path(s) }));
}

/** A REST client authenticated the way Reloadify connects: the integration's access token. */
export function reloadify(request: APIRequestContext): Rest {
  return new Rest(request, state().tokens.reloadify);
}

/** A REST client with Magento's admin token, for the core endpoints the answers are compared with. */
export function admin(request: APIRequestContext): Rest {
  return new Rest(request, state().tokens.admin);
}

/** GET a Reloadify route and require a JSON array back. */
export async function list(request: APIRequestContext, path: string): Promise<any[]> {
  const response = await reloadify(request).get(path);
  if (response.status !== 200 || !Array.isArray(response.body)) {
    throw new Error(`GET ${path} answered ${response.status}: ${response.text.slice(0, 2000)}`);
  }
  return response.body;
}

/** The one item with this id from a Reloadify list, or a failure naming what was there instead. */
export function byId<T extends { id: unknown }>(items: T[], id: number | string, what: string): T {
  const found = items.find((item) => String(item.id) === String(id));
  if (!found) {
    throw new Error(`${what} ${id} is not in the answer; ids there: ${items.map((i) => i.id).join(', ') || '(none)'}`);
  }
  return found;
}

/** The recovery URL's encrypted quote id, decoded from the query string. */
export function encryptedIdFrom(recoveryUrl: string): string {
  const value = new URL(recoveryUrl).searchParams.get('id');
  if (!value) {
    throw new Error(`No id in recovery_url ${recoveryUrl}`);
  }
  return value;
}
