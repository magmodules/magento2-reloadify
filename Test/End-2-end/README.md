# Reloadify E2E suite

Calls the Reloadify REST API and the cart restore link against a running Magento shop, over HTTP
only (no browser), and compares the answers with Magento's own REST API. The test list is in
`E2E-TESTS.md` in the module root.

## What it changes on the shop

Run it on a shop where test data is acceptable. Global setup creates, all prefixed `reloadify-e2e`:
a category, a simple, a configurable with two children, a bundle with one part, two customers,
two reviews, a guest newsletter subscriber, orders (one invoiced, shipped and refunded), carts and
a second integration without the Reloadify resource. Specs change Reloadify configuration and
reset it afterwards, and the setup runs `indexer:reindex` for the stock, price and category indexers.

## Prepare the shop (once)

From the Magento root, with the module installed and `setup:upgrade` done:

    bash <module>/Test/End-2-end/scripts/configure-shop.sh

This enables the module, allows integration tokens as Bearer tokens, disables two-factor
authentication, enables flat rate, check/money order and guest checkout, and keeps stock managed.

## Run

Node 20 or newer. From `Test/End-2-end`:

    npm ci
    BASE_URL=https://shop.example/ \
    MAGENTO_ROOT=/var/www/shop \
    MAGENTO_ADMIN_USER=admin MAGENTO_ADMIN_PASS=secret \
    npx playwright test

| Variable | Meaning | Default |
|---|---|---|
| `BASE_URL` | Storefront base URL, with trailing slash | `https://magento.test/` |
| `MAGENTO_ROOT` | Magento directory on this host; commands run directly there | (unset) |
| `MAGENTO_CONTAINER` | Without `MAGENTO_ROOT`: the Docker container commands run in | `magento-project-community-edition` |
| `MAGENTO_ADMIN_USER` / `MAGENTO_ADMIN_PASS` | Admin user for the admin token | `exampleuser` / `examplepassword123` |

The user running the suite needs to run `bin/magento` and write to the Magento `var/` directory.

## In CI

`.github/workflows/e2e-test.yml` runs the suite on Magento 2.4.7-p3 and 2.4.9 in a Docker shop with
sample data. It starts on a manual dispatch, when a pull request gets the `run_e2e_tests` label,
and on pull requests from `release/` branches.

## Rules

- One worker and no retries: a test that only passes the second time is a defect.
- A failing test means look at the module first; do not adjust an assertion to what an endpoint
  happens to return.
- `products-delta returns fewer products than the full export` is marked `test.fail()`: it
  documents an open question about the delta export and is reported once it starts passing.

After adding or renaming a test, regenerate the list: `npm run docs`.
