# Changelog

## [1.17.2]

**Features:**
* Added store view specific prices incl./excl. tax (`price_incl` / `price_excl`) to the products and variants endpoints, calculated with the tax rate of each store view.

**Bugfixes:**
* Fixed MSI stock collection for custom stocks ("Unknown column inventory_stock_N.product_id" error and default stock qty reported for custom stocks).
* Fixed rendered price extra fields (price, min_price, max_price) breaking the products endpoint.
* Variants endpoint now converts `special_price` per store view.

**Compatibility:**
* Magento 2.3.x & Magento 2.4.x

## [1.17.1]

**Bugfixes:**
* Fix variants endpoint returning 500 on attribute and stock data

**Improvements:**
* Added documentation (EN/NL)
* Added PHP 8.5 + Magento 2.4.9 to PHPStan CI matrix

**Compatibility:**
* Magento 2.3.x & Magento 2.4.x

## [1.17.0]

**Features:**
* Add refund data, coupon code and discount amount to order payload.

**Improvements:**
* Refactor CLI commands: protected execute, isEnabled guard, Throwable catch.
* Read version and support link from composer.json instead of config.xml.
* Update CI workflows: checkout v4, path filters, updated matrix, remove legacy setup-di-compile.

**Compatibility:**
* Magento 2.3.x & Magento 2.4.x

## [1.16.0]

**Features:**
* Added an option to limit customer data sharing.

**Bugfixes:**
* Fixed missing product images for variants.

**Compatibility:**
* Magento 2.3.x & Magento 2.4.x

## [1.15.0]

**Features:**
* Added support for PWA
* Added profile field "has an account"
* Added option to select main image
* Added Image Variant Mapping in the Settings Panel
* Added compatibility for PHP 8.4

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.14.0]

**Changes**
* Add address data to order endpoint
* Improved custom attributes on profiles endpoint

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.13.0]

**Changes**
* Add custom attributes to profiles endpoint

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.12.0]

**Changes**
* Added special_price to variant endpoint
* Changed customer_group key on profile endpoint
* Normalize the image path by ensuring it starts with a forward slash
* Add address data for guest orders on order endpoint

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.11.0]

**Changes**
* Added option to add extra fields to products endpoint output
* Added more fields to carts endpoint output
* Added customer_group to profiles endpoint output

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.10.2]

**Changes**
* Fixed issue with product attribute type on products endpoint

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.10.1]

**Changes**
* Fixed issue with grouped product on orders-endpoint

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.10.0]

**Changes**
* Improved variants output on WebApI
* Create db_schema_whitelist.json

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.9.0]

**Changes**
* Catch exceptions on profile export
* Add customer data to order profile
* Updated the Logo

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.8.0]

**Changes**
* Made description dynamic
* Use full size image for export
* Changed variants export logic

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.7.1]

**Changes**
* Add store_id to Profile webapi output

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.7.0]

**Changes**
* Create Subscribers Endpoint
* Removed unused prototype require for buttons js

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.6.0]

**Changes**
* Added newsletter subscribers to profiles request
* Added customer data as fallback if no default billing is set
* Added '/settings' WebApi endpoint

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.8]

**Changes**
* Added company_name to profiles data

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.7]

**Changes**
* Fixed PHP82 support
* Get Products of correct Store for category webapi
 * Update GitHub workflows to support testing on Magento 2.4.6

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.6]

**Changes**
* Fix search criteria #16
Fixed issue with products-delta endpoint on first connection #18

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.5]

**Changes**
* Fix search criteria #15
* Fix webapi annotations #16

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.4]

**Changes**
* Replace collection with repository #13

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.3]

**Changes**
* Make sure guest email is saved to quote

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.2]

**Changes**
* Add email to profile for guest orders and quest carts

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.1]

**Changes**
* Added default values for attribute config selection
* Load access token in config from integration table
* Update GitHub workflow runners

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.5.0]

**Changes**
* Add brand attribute option to config

**Fixes**
* Use the ProductCollectionProcessor instead of the regular one #6
* Fix dropdown text attributes output in product/variant webapi
* Removed updated_at from review webapi data output #7

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.4.0]

**Changes**
* Implemented SearchCriteria to various endpoints
* Fix gender in customer profile api

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.3.1]

**Changes**
* Make Logger component compatible with Monolog 2.0

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.3.0]

**Changes**
* Dropped hard dependency on Magento_Inventory
* Updated GitHub action flows
* Added visibility declaration on all constants
* Replaced deprecated _redirect method

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.2.0]

**Changes**
* Added support for cart recovery

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.1.0]

**Changes**
* Add Integration CLI command and updated ACL for webapi's
* Fixed support and version urls in module

**Compatibility**
* Magento: 2.3.x & Magento 2.4.x

## [1.0.0]

