/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { createHash } from 'crypto';
import { LOCAL_ROOT, magento, fixture, installFixtureScript, logLineCount, setConfig } from './support/Container';
import { Rest, BASE_URL } from './support/Rest';
import { E2eState, MAGENTO_LOGS, MODULE_LOGS, PREFIX, writeState } from './support/State';
import {
  EMAIL,
  californiaAddress,
  configurableItem,
  customerCart,
  customerToken,
  dutchAddress,
  ensureCustomer,
  guestCart,
  placeCustomerOrder,
  placeGuestOrder,
  reindex,
  saveProduct,
  setSourceQuantity,
  simpleProduct,
} from './support/Shop';

const STORE_ID = 1;
const WEBSITE_ID = 1;
/**
 * Login of the two test customers. Derived from the shop URL rather than written down, so a rerun on
 * the same shop logs in to the customers it created before; the suffix meets Magento's password rules.
 */
const PASSWORD = createHash('sha256').update(`${PREFIX}:${BASE_URL}`).digest('hex').slice(0, 16) + 'Aa1!';

/**
 * Builds every fixture the specs use, through Magento's own REST API wherever it offers one, and
 * writes their ids to .e2e/state.json. Runs once, before the first spec.
 */
export default async function globalSetup(): Promise<void> {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
  console.log(`E2E against ${BASE_URL}`);

  installFixtureScript();
  if (!LOCAL_ROOT) {
    // The baseline project drives the admin in a browser: a secret key in every admin URL and a
    // captcha on the login form are in its way. Not touched on a dev server, where it does not run.
    setConfig('admin/security/use_form_key', 0);
    setConfig('admin/captcha/enable', 0);
  }
  const logBaselines: Record<string, number> = {};
  for (const file of [...MODULE_LOGS, ...MAGENTO_LOGS]) {
    logBaselines[file] = logLineCount(file);
  }

  const rest = await Rest.create();
  const adminToken: string = await rest.ok('POST', 'V1/integration/admin/token', {
    username: process.env.MAGENTO_ADMIN_USER || 'exampleuser',
    password: process.env.MAGENTO_ADMIN_PASS || 'examplepassword123',
  });
  const admin = rest.as(adminToken);

  // The token a merchant copies into Reloadify, printed by the module's own command.
  const commandOutput = magento('reloadify:integration');
  const match = commandOutput.match(/Integration token:\s*(\S+)/);
  if (!match) {
    throw new Error(`bin/magento reloadify:integration printed no token:\n${commandOutput}`);
  }
  const noAccess = fixture<{ token: string }>('integration-without-access', { name: `${PREFIX} without access` });

  const categoryId = await createCategory(admin);
  const simple = await saveProduct(
    admin,
    simpleProduct(`${PREFIX}-simple`, 'Reloadify E2E Simple', 20, 4, {
      extension_attributes: { category_links: [{ category_id: String(categoryId), position: 0 }] },
    })
  );
  await setSourceQuantity(admin, simple.sku, 100);
  const configurable = await createConfigurable(admin, categoryId);
  const bundle = await createBundle(admin, categoryId);
  reindex();

  const customers = await createCustomers(admin);
  const reviews = {
    approved: fixture<{ reviewId: number; percent: number }>('review', {
      productId: simple.id,
      customerId: customers.full.id,
      storeId: STORE_ID,
      statusId: 1,
      title: `${PREFIX} approved review`,
      detail: 'Approved review created by the E2E suite.',
      nickname: 'Rel',
      ratingValue: 4,
    }),
    pending: fixture<{ reviewId: number; percent: number }>('review', {
      productId: simple.id,
      customerId: null,
      storeId: STORE_ID,
      statusId: 2,
      title: `${PREFIX} pending review`,
      detail: 'Pending review created by the E2E suite.',
      nickname: 'Guest',
      ratingValue: 2,
    }),
  };
  const guestSubscriberEmail = EMAIL('guest-subscriber');
  const guestSubscriber = fixture<{ subscriberId: number }>('guest-subscriber', {
    email: guestSubscriberEmail,
    storeId: STORE_ID,
  });

  // A guest order that stays open, and a customer order that is invoiced, shipped and refunded.
  const guestOrder = await placeGuestOrder(rest, [{ sku: simple.sku, qty: 1 }], EMAIL('guest-order'));
  const fullToken = await customerToken(rest, customers.full.email, PASSWORD);
  const customerOrder = await placeCustomerOrder(
    rest,
    fullToken,
    [configurableItem(configurable.sku, configurable.colorAttributeId, configurable.children[0].colorValue)],
    californiaAddress(customers.full.email)
  );
  await admin.ok('POST', `V1/order/${customerOrder.orderId}/invoice`, { capture: true });
  await admin.ok('POST', `V1/order/${customerOrder.orderId}/ship`, {});
  await admin.ok('POST', `V1/order/${customerOrder.orderId}/refund`, {});

  // Two carts that are not ordered: a guest's and a logged-in customer's.
  const guestCartEmail = EMAIL('guest-cart');
  const guest = await guestCart(rest, [{ sku: simple.sku, qty: 2 }], guestCartEmail);
  const minimalToken = await customerToken(rest, customers.minimal.email, PASSWORD);
  const customerCartId = await customerCart(
    rest,
    minimalToken,
    [{ sku: simple.sku, qty: 1 }],
    dutchAddress(customers.minimal.email)
  );

  const state: E2eState = {
    storeId: STORE_ID,
    websiteId: WEBSITE_ID,
    tokens: { admin: adminToken, reloadify: match[1], noAccess: noAccess.token },
    logBaselines,
    categoryId,
    simple,
    configurable,
    bundle,
    customers,
    reviews: {
      approved: { id: reviews.approved.reviewId, percent: reviews.approved.percent },
      pending: { id: reviews.pending.reviewId },
    },
    guestSubscriber: { id: guestSubscriber.subscriberId, email: guestSubscriberEmail },
    orders: {
      guest: await orderFacts(admin, guestOrder),
      customer: await orderFacts(admin, customerOrder),
    },
    carts: { guest: { id: guest.quoteId, email: guestCartEmail }, customer: { id: customerCartId } },
  };
  writeState(state);
  console.log('Fixtures ready.');
}

async function createCategory(admin: Rest): Promise<number> {
  const name = 'Reloadify E2E';
  const found = await admin.ok(
    'GET',
    `V1/categories/list?searchCriteria[filterGroups][0][filters][0][field]=name` +
      `&searchCriteria[filterGroups][0][filters][0][value]=${encodeURIComponent(name)}`
  );
  if (found.items?.length) {
    return Number(found.items[0].id);
  }
  const created = await admin.ok('POST', 'V1/categories', {
    category: { name, parent_id: 2, is_active: true, include_in_menu: true },
  });
  return Number(created.id);
}

/** A configurable product on the `color` attribute with two children, one per colour. */
async function createConfigurable(admin: Rest, categoryId: number): Promise<E2eState['configurable']> {
  const color = await admin.ok('GET', 'all/V1/products/attributes/color');
  const colorAttributeId = Number(color.attribute_id);
  const values = (color.options as Array<{ value: string; label: string }>)
    .filter((option) => option.value !== '' && option.value !== undefined)
    .slice(0, 2)
    .map((option) => Number(option.value));
  if (values.length < 2) {
    throw new Error('The color attribute needs at least two options for the configurable fixture');
  }
  await ensureAttributeInDefaultSet(admin, 'color');

  const parentSku = `${PREFIX}-configurable`;
  const children: Array<{ id: number; sku: string; colorValue: number }> = [];
  for (const [index, value] of values.entries()) {
    const child = await saveProduct(
      admin,
      simpleProduct(`${PREFIX}-configurable-child-${index + 1}`, `Reloadify E2E Child ${index + 1}`, 30, 1, {
        custom_attributes: [{ attribute_code: 'color', value: String(value) }],
      })
    );
    await setSourceQuantity(admin, child.sku, 50);
    children.push({ ...child, colorValue: value });
  }

  const parent = await saveProduct(admin, {
    sku: parentSku,
    name: 'Reloadify E2E Configurable',
    attribute_set_id: 4,
    status: 1,
    visibility: 4,
    type_id: 'configurable',
    extension_attributes: {
      website_ids: [1],
      category_links: [{ category_id: String(categoryId), position: 1 }],
      stock_item: { is_in_stock: true },
    },
    custom_attributes: [
      { attribute_code: 'url_key', value: parentSku },
      { attribute_code: 'tax_class_id', value: '2' },
    ],
  });

  const options = await admin.ok('GET', `all/V1/configurable-products/${parentSku}/options/all`);
  if (!options.length) {
    await admin.ok('POST', `all/V1/configurable-products/${parentSku}/options`, {
      option: {
        attribute_id: String(colorAttributeId),
        label: 'Color',
        position: 0,
        is_use_default: true,
        values: values.map((value) => ({ value_index: value })),
      },
    });
  }
  const linked = (await admin.ok('GET', `all/V1/configurable-products/${parentSku}/children`)) as Array<{ sku: string }>;
  for (const child of children) {
    if (!linked.some((product) => product.sku === child.sku)) {
      await admin.ok('POST', `all/V1/configurable-products/${parentSku}/child`, { childSku: child.sku });
    }
  }

  return { ...parent, colorAttributeId, children };
}

/** Add an attribute to the Default attribute set when it is not in there yet. */
async function ensureAttributeInDefaultSet(admin: Rest, code: string): Promise<void> {
  const attributes = (await admin.ok('GET', 'all/V1/products/attribute-sets/4/attributes')) as Array<{ attribute_code: string }>;
  if (attributes.some((attribute) => attribute.attribute_code === code)) {
    return;
  }
  const groups = await admin.ok(
    'GET',
    'all/V1/products/attribute-sets/groups/list?searchCriteria[filterGroups][0][filters][0][field]=attribute_set_id' +
      '&searchCriteria[filterGroups][0][filters][0][value]=4'
  );
  await admin.ok('POST', 'all/V1/products/attribute-sets/attributes', {
    attributeSetId: 4,
    attributeGroupId: Number(groups.items[0].attribute_group_id),
    attributeCode: code,
    sortOrder: 99,
  });
  // Magento keeps the attributes of a set in its EAV cache and the REST call above does not clean
  // it: without this a product saved right after silently loses its value for the new attribute.
  magento('cache:clean eav');
}

/** A dynamic-price bundle with one required option holding one simple part. */
async function createBundle(admin: Rest, categoryId: number): Promise<E2eState['bundle']> {
  const part = await saveProduct(admin, simpleProduct(`${PREFIX}-bundle-part`, 'Reloadify E2E Bundle Part', 15, 1));
  await setSourceQuantity(admin, part.sku, 40);
  const sku = `${PREFIX}-bundle`;
  const bundle = await saveProduct(admin, {
    sku,
    name: 'Reloadify E2E Bundle',
    attribute_set_id: 4,
    status: 1,
    visibility: 4,
    type_id: 'bundle',
    extension_attributes: {
      website_ids: [1],
      category_links: [{ category_id: String(categoryId), position: 2 }],
      stock_item: { is_in_stock: true },
      bundle_product_options: [
        {
          title: 'Part',
          required: true,
          type: 'select',
          position: 1,
          sku,
          product_links: [
            {
              sku: part.sku,
              qty: 1,
              position: 1,
              is_default: true,
              price: 0,
              price_type: 0,
              can_change_quantity: 0,
            },
          ],
        },
      ],
    },
    custom_attributes: [
      { attribute_code: 'url_key', value: sku },
      { attribute_code: 'price_type', value: '0' },
      { attribute_code: 'sku_type', value: '0' },
      { attribute_code: 'weight_type', value: '0' },
      { attribute_code: 'price_view', value: '0' },
      { attribute_code: 'shipment_type', value: '0' },
    ],
  });
  return { ...bundle, part };
}

/**
 * Two customers: one with gender, date of birth, a newsletter subscription and a Californian
 * address (which has a region), and one with none of those and a Dutch address without a region.
 */
async function createCustomers(admin: Rest): Promise<E2eState['customers']> {
  const fullEmail = EMAIL('full');
  const ca = californiaAddress(fullEmail, 'Rel', 'Full');
  const full = await ensureCustomer(
    admin,
    {
      email: fullEmail,
      firstname: 'Rel',
      lastname: 'Full',
      gender: 1,
      dob: '1990-01-02',
      website_id: WEBSITE_ID,
      store_id: STORE_ID,
      group_id: 1,
      addresses: [
        {
          firstname: ca.firstname,
          lastname: ca.lastname,
          street: ca.street,
          city: ca.city,
          postcode: ca.postcode,
          country_id: ca.country_id,
          telephone: ca.telephone,
          company: ca.company,
          region_id: ca.region_id,
          region: { region_code: ca.region_code, region: ca.region, region_id: ca.region_id },
          default_billing: true,
          default_shipping: true,
        },
      ],
      extension_attributes: { is_subscribed: true },
    },
    PASSWORD
  );

  const minimalEmail = EMAIL('minimal');
  const nl = dutchAddress(minimalEmail, 'Rel', 'Minimal');
  const minimal = await ensureCustomer(
    admin,
    {
      email: minimalEmail,
      firstname: 'Rel',
      lastname: 'Minimal',
      website_id: WEBSITE_ID,
      store_id: STORE_ID,
      group_id: 1,
      addresses: [
        {
          firstname: nl.firstname,
          lastname: nl.lastname,
          street: nl.street,
          city: nl.city,
          postcode: nl.postcode,
          country_id: nl.country_id,
          telephone: nl.telephone,
          default_billing: true,
          default_shipping: true,
        },
      ],
    },
    PASSWORD
  );

  return {
    full: { id: full, email: fullEmail, password: PASSWORD },
    minimal: { id: minimal, email: minimalEmail, password: PASSWORD },
  };
}

async function orderFacts(admin: Rest, placed: { orderId: number; quoteId: number }) {
  const order = await admin.ok('GET', `V1/orders/${placed.orderId}`);
  return {
    id: placed.orderId,
    incrementId: String(order.increment_id),
    quoteId: placed.quoteId,
    grandTotal: Number(order.grand_total),
  };
}
