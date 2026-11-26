/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import fs from 'fs';
import path from 'path';

/** Everything global-setup created, so a spec can find its fixtures without looking them up again. */
export interface E2eState {
  storeId: number;
  websiteId: number;
  tokens: {
    admin: string;
    /** The token `bin/magento reloadify:integration` prints: what a merchant hands to Reloadify. */
    reloadify: string;
    /** A valid integration token without Magmodules_Reloadify::webapi. */
    noAccess: string;
  };
  logBaselines: Record<string, number>;
  categoryId: number;
  simple: Product;
  configurable: Product & { colorAttributeId: number; children: Array<Product & { colorValue: number }> };
  bundle: Product & { part: Product };
  customers: { full: Customer; minimal: Customer };
  reviews: { approved: { id: number; percent: number }; pending: { id: number } };
  guestSubscriber: { id: number; email: string };
  orders: { guest: Order; customer: Order };
  carts: { guest: { id: number; email: string }; customer: { id: number } };
}

export interface Product {
  id: number;
  sku: string;
}

export interface Customer {
  id: number;
  email: string;
  password: string;
}

export interface Order {
  id: number;
  incrementId: string;
  quoteId: number;
  grandTotal: number;
}

const FILE = path.resolve(__dirname, '../.e2e/state.json');

export function writeState(state: E2eState): void {
  fs.mkdirSync(path.dirname(FILE), { recursive: true });
  fs.writeFileSync(FILE, JSON.stringify(state, null, 2));
}

let cached: E2eState | undefined;

export function state(): E2eState {
  if (!cached) {
    if (!fs.existsSync(FILE)) {
      throw new Error(`${FILE} does not exist: global-setup did not run or failed`);
    }
    cached = JSON.parse(fs.readFileSync(FILE, 'utf-8')) as E2eState;
  }
  return cached;
}

/** Fixture names and e-mail addresses all start with this, so they are recognisable in the shop. */
export const PREFIX = 'reloadify-e2e';

/** Reloadify module log files the final project checks. */
export const MODULE_LOGS = ['reloadify-error.log'];

/** Magento's own logs the final project checks for lines about the module. */
export const MAGENTO_LOGS = ['exception.log', 'system.log'];
