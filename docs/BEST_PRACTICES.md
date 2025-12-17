# Best Practices

These are the recommended ways to configure [Reloadify](https://www.magmodules.eu/magento2-reloadify.html) based on what we've seen work well. The module is straightforward, but a few tweaks can improve your marketing automation results. We've also included common mistakes to avoid.

## General Guidelines

### Do's

✅ Run `reloadify:selftest` after initial setup to verify the connection

✅ Use a dedicated EAN/barcode attribute if you have one - improves product matching

✅ Map your brand attribute - enables brand-based segmentation in Reloadify

✅ Keep debug mode off in production to avoid large log files

✅ Test cart restore links before launching abandoned cart campaigns

### Don'ts

❌ Don't share your access token publicly - it grants API access to your store data

❌ Don't disable the module while Reloadify campaigns are active - breaks email links

❌ Don't map the same Magento attribute to multiple Reloadify fields

❌ Don't use full-size product images - medium resolution (300-600px) loads faster in emails

---

## Common Scenarios

### Scenario 1: Standard Store Setup

**Use case:** Single-language store with standard Magento attributes

**Configuration:**
General:
- Enabled: Yes
- Generate token via CLI

Attributes:
- Leave all defaults (SKU, Product Name, Description)
- Map Brand if you have a manufacturer attribute

PWA Settings:
- Base URL: Magento (default)

**Result:** Reloadify syncs your full catalog, customer data, and orders using standard Magento data.

---

### Scenario 2: Multi-Brand Store

**Use case:** Store with multiple brands, want to segment emails by brand

**Configuration:**
Attributes:
- Brand: Select your manufacturer/brand attribute
- Extra Fields: Add any brand-related attributes like `brand_collection` or `brand_tier`

**Result:** Reloadify can create segments like "Nike customers" or "Premium brand buyers" for targeted campaigns.

---

### Scenario 3: PWA/Headless Frontend

**Use case:** React/Vue frontend, Magento as headless backend

**Configuration:**
PWA Settings:
- Base URL: PWA
- PWA URL: `https://your-pwa.com/cart/restore`

**Your PWA needs to:**
1. Accept the `?id={encrypted_quote_id}` parameter
2. Call the Reloadify cart restore API endpoint
3. Rebuild the cart session in your frontend

**Result:** Abandoned cart emails link to your PWA instead of Magento frontend.

---

### Scenario 4: Rich Product Data for Segmentation

**Use case:** Want Reloadify to segment by product attributes (color, size, season)

**Configuration:**
Attributes > Extra Fields:
| Magento Attribute | Reloadify Field |
|-------------------|-----------------|
| `color` | `color` |
| `size` | `size` |
| `season` | `season` |
| `is_sale` | `on_sale` |

**Result:** Reloadify campaigns can target "customers who bought red products" or "winter collection buyers."

---

## Performance Tips

### Image Optimization

Select appropriately sized images in the configuration:
- Too large = slow email loading, potential delivery issues
- Too small = poor quality in high-DPI email clients
- Sweet spot: 400-600px width works for most email templates

### API Request Handling

Reloadify fetches data from your store via API. The module is optimized for this, but:
- Large catalogs (50k+ products) may take longer to sync initially
- Delta sync (`/products-delta`) only fetches recently changed products
- Syncs run from Reloadify's servers, not via Magento cron

### Log Management

If you enable debug mode for troubleshooting:
- Debug logs grow quickly with API traffic
- Disable after troubleshooting is complete
- Logs are in `var/log/reloadify/`

---

## Common Mistakes

### Mistake: Not mapping the EAN attribute

**Why it matters:** EAN/barcode helps Reloadify match products accurately, especially if you sell on multiple channels.

**Correct approach:** If you have a barcode attribute (`ean`, `gtin`, `barcode`), map it in Attributes > EAN. If you don't have barcodes, SKU works fine as fallback.

---

### Mistake: Using debug mode in production permanently

**Why it's wrong:** Debug logs every API request. With regular Reloadify syncs, this creates large log files that waste disk space.

**Correct approach:** Enable debug mode only when troubleshooting specific issues. Disable after resolving.

---

### Mistake: Regenerating token without updating Reloadify

**Why it's wrong:** If you run `reloadify:integration --update=1`, the old token becomes invalid. Reloadify can no longer sync.

**Correct approach:** After regenerating a token, immediately update it in your Reloadify dashboard. Plan token rotations during low-traffic periods.

---

### Mistake: Mapping Description to a very long attribute

**Why it matters:** Product descriptions in emails should be punchy. A 2000-word product description looks terrible in an email card.

**Correct approach:** If your main description is very detailed, consider using `short_description` or creating a marketing-specific attribute.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-reloadify/) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support/) - Get help from our team
