# Configuration Guide

Here's where you'll find all the settings for [Reloadify](https://www.magmodules.eu/magento2-reloadify.html). This guide explains what each option does and when you'd want to change it. The module is designed to work out-of-the-box for most stores, so you likely won't need to touch most settings unless you have specific requirements.

**Location:** Stores > Configuration > Reloadify > General

## General

Basic module settings and connection status.

### Enabled

Turns the Reloadify integration on or off. When disabled, all API endpoints return empty responses and no data is shared with Reloadify.

**When to disable:**
- Temporarily pausing the integration for maintenance
- Testing issues by isolating the module
- Staging environments where you don't want data syncing

### Access Token

Shows your integration token after running `bin/magento reloadify:integration`. This is a read-only display field - you can't edit the token here.

**What it's for:** Copy this token into your Reloadify dashboard to authenticate the connection. Reloadify uses this token in API requests to fetch your store data.

**If you need a new token:** Run `bin/magento reloadify:integration --update=1` to regenerate it. Don't forget to update the token in Reloadify afterwards.

---

## Attributes

Map your Magento product attributes to Reloadify's expected fields. This controls how product data appears in Reloadify for segmentation and email personalization.

### EAN

The barcode/GTIN identifier for products.

**Default behavior:** Uses SKU if not configured.

**When to change:** If you have a dedicated EAN/GTIN attribute (like `ean`, `barcode`, or `gtin`), select it here. This helps Reloadify match products across systems and improves product recognition in emails.

**Tip:** If your products don't have EAN codes, leaving this as SKU is perfectly fine.

### Name

The product name sent to Reloadify.

**Default:** Product Name (the standard Magento name attribute)

**When to change:** Only if you have an alternative name attribute specifically for marketing purposes. Most stores should leave this as the default.

### SKU

The product identifier used in Reloadify.

**Default:** SKU

**When to change:** Rarely. Only change this if you use a different attribute as your primary product identifier (unusual).

### Brand

The manufacturer or brand name for products.

**When to configure:** If you want brand data available in Reloadify for segmentation (e.g., "customers who bought Nike products"). Select your brand/manufacturer attribute.

**Common attribute names:** `manufacturer`, `brand`, `merk`

**If you don't have brand data:** Leave empty - it's optional.

### Description

The product description sent to Reloadify for use in email content.

**Default:** Description (standard Magento description)

**When to change:**
- If you have a short description that works better for emails, use `short_description`
- If you have a marketing-specific description attribute, select that instead

**Tip:** Reloadify typically uses this for dynamic product blocks in emails. Shorter, punchy descriptions often work better than full product pages.

### Main Image

Which product image to send as the primary image.

**Options:** Base Image, Small Image, Thumbnail, or specific image roles you've configured.

**Recommendation:** Base Image works for most stores. It's typically the highest quality and used in product listings.

### Extra Image

Additional product image to include.

**When to use:** If you want Reloadify to have access to a secondary image (like a lifestyle shot or alternate angle). Optional for most stores.

### Extra Fields

Add custom attributes to the product data sent to Reloadify.

**How it works:** A dynamic table where you can map additional Magento attributes to custom field names in Reloadify.

**When to use:**
- You want to segment by custom attributes (color, size, material, season)
- Reloadify campaigns need specific product data not covered by standard fields
- You have product flags (new, sale, bestseller) useful for email targeting

**Example setup:**
| Magento Attribute | Reloadify Field |
|-------------------|-----------------|
| `color` | `color` |
| `is_new` | `new_arrival` |
| `season` | `season` |

### Image

Select the image size variant to use.

**Options vary** based on your theme's configured image types.

**Recommendation:** Choose a size appropriate for email use - typically medium resolution (300-600px). Oversized images slow down email loading.

---

## PWA Settings

Configuration for headless/PWA storefronts. Skip this section if you're using a traditional Magento frontend (Luma, Hyvä, etc.).

### Base URL

Determines which URL format to use for cart restore links.

**Options:**
- **Magento** - Uses standard Magento frontend URL (`/reloadify/cart/restore`)
- **PWA** - Uses your custom PWA URL (configure below)

**When to use PWA:** If your frontend is a separate application (React, Vue, etc.) that doesn't use Magento's routing.

### PWA URL

Your PWA's cart restore endpoint.

**Only visible when:** Base URL is set to "PWA"

**How it works:** Reloadify appends the encrypted cart ID as a query parameter:
```
https://your-pwa.com/restore-cart?id={encrypted_quote_id}
```

Your PWA then calls the Reloadify API to restore the cart session.

**Store view configuration:** If you use store codes in URLs, configure different PWA URLs per store view.

---

## Debug & Logging

Tools for troubleshooting and monitoring the integration.

### Debug Mode

When enabled, logs detailed information about API requests and responses.

**Log location:** `var/log/reloadify/debug.log`

**When to enable:**
- Troubleshooting why specific products aren't syncing
- Investigating API response issues
- Support requests (we may ask for debug logs)

**Note:** Keep disabled in production during normal operation - debug logs can grow large.

### Debug Log Button

Downloads the current debug log file for review.

### Error Log Button

Downloads the error log (`var/log/reloadify/error.log`).

**Errors are always logged** regardless of debug mode. Check this first when something isn't working.

### Selftest Button

Runs a diagnostic check on your configuration. Tests include:
- Extension enabled status
- Magento and PHP version compatibility
- Integration token validity

Same as running `bin/magento reloadify:selftest` but accessible from admin.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-reloadify/) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support/) - Get help from our team
