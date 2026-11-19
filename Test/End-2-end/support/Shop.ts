/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { Rest } from './Rest';
import { magento, fixture } from './Container';
import { PREFIX } from './State';

/** Indexers the endpoints read from; cron is off during a run, so they are rebuilt explicitly. */
export const INDEXERS = 'cataloginventory_stock inventory catalog_product_price catalog_category_product';

export function reindex(): void {
  magento(`indexer:reindex ${INDEXERS}`);
}

/** A US address in California, which has a region. */
export function californiaAddress(email: string, firstname = 'Rel', lastname = 'Oadify') {
  return {
    firstname,
    lastname,
    street: ['1 Main Street'],
    city: 'Los Angeles',
    region: 'California',
    region_id: 12,
    region_code: 'CA',
    postcode: '90001',
    country_id: 'US',
    telephone: '5555550100',
    company: 'Reloadify E2E',
    email,
  };
}

/** A Dutch address: the Netherlands does not require a region, so it has none. */
export function dutchAddress(email: string, firstname = 'Rel', lastname = 'Oadify') {
  return {
    firstname,
    lastname,
    street: ['Dam 1'],
    city: 'Amsterdam',
    postcode: '1012 JS',
    country_id: 'NL',
    telephone: '0201234567',
    email,
  };
}

/**
 * Create or update a product by SKU. `POST /V1/products` saves an existing SKU in place, so a rerun
 * on the same shop reuses its fixtures instead of failing on a duplicate.
 */
export async function saveProduct(admin: Rest, product: Record<string, unknown>): Promise<{ id: number; sku: string }> {
  const saved = await admin.ok('POST', 'all/V1/products', { product });
  return { id: Number(saved.id), sku: String(saved.sku) };
}

/** Put a quantity on the default source, so MSI and the legacy stock agree from the start. */
export async function setSourceQuantity(admin: Rest, sku: string, quantity: number): Promise<void> {
  await admin.ok('POST', 'V1/inventory/source-items', {
    sourceItems: [{ sku, source_code: 'default', quantity, status: 1 }],
  });
}

export function simpleProduct(sku: string, name: string, price: number, visibility: number, extra: Record<string, unknown> = {}) {
  return {
    sku,
    name,
    attribute_set_id: 4,
    price,
    status: 1,
    visibility,
    type_id: 'simple',
    weight: 1,
    extension_attributes: {
      website_ids: [1],
      stock_item: { qty: 100, is_in_stock: true },
      ...(extra.extension_attributes as object | undefined),
    },
    custom_attributes: [
      { attribute_code: 'url_key', value: sku },
      { attribute_code: 'tax_class_id', value: '2' },
      ...((extra.custom_attributes as unknown[]) || []),
    ],
  };
}

/** Find a customer by e-mail, or create one with the given data and password. */
export async function ensureCustomer(
  admin: Rest,
  customer: Record<string, unknown>,
  password: string
): Promise<number> {
  const email = String(customer.email);
  const found = await admin.ok(
    'GET',
    `V1/customers/search?searchCriteria[filterGroups][0][filters][0][field]=email` +
      `&searchCriteria[filterGroups][0][filters][0][value]=${encodeURIComponent(email)}`
  );
  if (found.items?.length) {
    return Number(found.items[0].id);
  }
  const created = await admin.ok('POST', 'V1/customers', { customer, password });
  return Number(created.id);
}

export async function customerToken(rest: Rest, email: string, password: string): Promise<string> {
  return rest.as(null).ok('POST', 'V1/integration/customer/token', { username: email, password });
}

function addressInformation(address: Record<string, unknown>) {
  return {
    addressInformation: {
      shipping_address: address,
      billing_address: address,
      shipping_carrier_code: 'flatrate',
      shipping_method_code: 'flatrate',
    },
  };
}

/** A guest cart with items and a shipping address, not ordered. Returns the quote id and mask. */
export async function guestCart(
  rest: Rest,
  items: Array<Record<string, unknown>>,
  email: string
): Promise<{ quoteId: number; mask: string }> {
  const guest = rest.as(null);
  const mask: string = await guest.ok('POST', 'V1/guest-carts');
  for (const item of items) {
    await guest.ok('POST', `V1/guest-carts/${mask}/items`, { cartItem: { ...item, quote_id: mask } });
  }
  await guest.ok('POST', `V1/guest-carts/${mask}/shipping-information`, addressInformation(californiaAddress(email)));
  const cart = await guest.ok('GET', `V1/guest-carts/${mask}`);
  // A guest's e-mail address is only stored on the quote when the order is placed; the storefront
  // checkout sets it earlier, which is the state an abandoned guest cart is in.
  fixture('quote-set', { id: Number(cart.id), data: { customer_email: email } });
  return { quoteId: Number(cart.id), mask };
}

/** Place a guest order with check/money order and flat rate shipping. */
export async function placeGuestOrder(
  rest: Rest,
  items: Array<Record<string, unknown>>,
  email: string
): Promise<{ orderId: number; quoteId: number }> {
  const { quoteId, mask } = await guestCart(rest, items, email);
  const orderId = await rest.as(null).ok('POST', `V1/guest-carts/${mask}/payment-information`, {
    email,
    paymentMethod: { method: 'checkmo' },
    billingAddress: californiaAddress(email),
  });
  return { orderId: Number(orderId), quoteId };
}

/** A logged-in customer's cart with items and a shipping address. Returns the quote id. */
export async function customerCart(
  rest: Rest,
  token: string,
  items: Array<Record<string, unknown>>,
  address: Record<string, unknown>
): Promise<number> {
  const customer = rest.as(token);
  const quoteId = Number(await customer.ok('POST', 'V1/carts/mine'));
  for (const item of items) {
    await customer.ok('POST', 'V1/carts/mine/items', { cartItem: { ...item, quote_id: quoteId } });
  }
  await customer.ok('POST', 'V1/carts/mine/shipping-information', addressInformation(address));
  return quoteId;
}

/** Place a logged-in customer's order with check/money order and flat rate shipping. */
export async function placeCustomerOrder(
  rest: Rest,
  token: string,
  items: Array<Record<string, unknown>>,
  address: Record<string, unknown>
): Promise<{ orderId: number; quoteId: number }> {
  const quoteId = await customerCart(rest, token, items, address);
  const orderId = await rest.as(token).ok('POST', 'V1/carts/mine/payment-information', {
    paymentMethod: { method: 'checkmo' },
    billingAddress: address,
  });
  return { orderId: Number(orderId), quoteId };
}

/** The cart item for one colour of the configurable fixture. */
export function configurableItem(parentSku: string, attributeId: number, valueIndex: number, qty = 1) {
  return {
    sku: parentSku,
    qty,
    product_option: {
      extension_attributes: {
        configurable_item_options: [{ option_id: String(attributeId), option_value: valueIndex }],
      },
    },
  };
}

/** The core answer for a SKU's salable quantity on a stock, as Magento's inventory reports it. */
export async function salableQuantity(admin: Rest, sku: string, stockId = 1): Promise<number> {
  return Number(await admin.ok('GET', `V1/inventory/get-product-salable-quantity/${encodeURIComponent(sku)}/${stockId}`));
}

export const EMAIL = (name: string) => `${PREFIX}-${name}@example.com`;
