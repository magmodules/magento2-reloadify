# Troubleshooting

Having issues with [Reloadify](https://www.magmodules.eu/magento2-reloadify.html)? This page covers the most common problems and how to fix them. Start with the quick diagnostics to narrow down what's wrong, then find your specific issue below.

## Quick Diagnostics

Run these checks first:

1. **Module enabled?**
   Check: Stores > Configuration > Reloadify > General > Enabled = Yes

2. **Token generated?**
   Check: General > Access Token shows a token value
   If empty: Run `bin/magento reloadify:integration`

3. **Run selftest:**
   ```bash
   bin/magento reloadify:selftest
   ```
   All tests should pass. Note any failures.

4. **Check error logs:**
   ```bash
   tail -100 var/log/reloadify/error.log
   ```

5. **Clear cache:**
   ```bash
   bin/magento cache:flush
   ```

---

## Common Issues

### Issue: No access token showing in admin

**Symptoms:**
- Access Token field is empty in configuration
- Reloadify reports "invalid token" or connection errors

**Solution:**
1. Generate the integration token via CLI:
   ```bash
   bin/magento reloadify:integration
   ```
2. Copy the displayed token to Reloadify
3. Refresh the admin page - token should now display

**If token still doesn't show:**
- Check if the Magento integration was created: System > Integrations
- Look for "Reloadify" integration - should be Active

**Prevention:**
Token is automatically generated during module installation via setup patch. If it didn't run, the CLI command creates it manually.

---

### Issue: Reloadify can't connect / API returns 401

**Symptoms:**
- Reloadify dashboard shows "Connection failed"
- API requests return 401 Unauthorized
- Sync doesn't start

**Solution:**
1. Verify the token in Reloadify matches the token in Magento admin
2. Check if the integration is active: System > Integrations > Reloadify > Status = Active
3. If token mismatch, regenerate:
   ```bash
   bin/magento reloadify:integration --update=1
   ```
4. Update the new token in Reloadify immediately

**If still failing:**
- Check if your server blocks external API requests
- Verify the store URL in Reloadify is correct (including https://)
- Check for Web Application Firewall (WAF) rules blocking requests

**Prevention:**
Don't regenerate tokens unless necessary. If you do, update Reloadify immediately.

---

### Issue: Products not syncing / missing products

**Symptoms:**
- Reloadify shows fewer products than expected
- Specific products don't appear in Reloadify
- Product data is outdated

**Solution:**
1. Enable debug mode: Stores > Configuration > Reloadify > Debug & Logging > Debug Mode = Yes
2. Trigger a sync from Reloadify
3. Check debug logs:
   ```bash
   tail -200 var/log/reloadify/debug.log
   ```
4. Look for product filtering or errors

**Common causes:**
- Product is disabled or not visible
- Product is out of stock (depending on Reloadify settings)
- Product is in a non-synced store view

**Check product visibility:**
- Catalog > Products > [Product] > Visibility = "Catalog, Search" or "Catalog"
- Status = Enabled

**Prevention:**
Reloadify only syncs visible, enabled products by default. This is usually what you want.

---

### Issue: Cart restore links not working

**Symptoms:**
- Clicking abandoned cart email links shows error
- Customer lands on homepage instead of cart
- "Quote does not exist" message

**Solution:**
1. Check if quote still exists in database (quotes expire)
2. Verify the link format matches your setup:
   - Standard Magento: `/reloadify/cart/restore?id={encrypted}`
   - PWA: Your custom URL with `?id={encrypted}`

3. Test with a fresh cart:
   - Add items to cart
   - Get the encrypted cart ID from Reloadify
   - Test the restore link manually

**If quote expired:**
Magento removes old quotes based on your configuration. Check:
Stores > Configuration > Sales > Checkout > Quote Lifetime (days)

**PWA setup issues:**
- Verify PWA URL is configured correctly
- Check that your PWA handles the `id` parameter
- Ensure PWA calls the restore API endpoint

**Prevention:**
Set Quote Lifetime high enough that abandoned cart emails arrive before quotes expire (typically 30+ days).

---

### Issue: Cart already converted to order

**Symptoms:**
- Cart restore shows "An order has already been placed for this quote"
- Customer clicked link after completing purchase

**Solution:**
This is expected behavior - the customer already bought. The module correctly prevents restoring a completed order's cart.

**Prevention:**
Configure Reloadify to stop abandoned cart emails once an order is placed.

---

### Issue: Selftest fails - "Extension Disabled"

**Symptoms:**
- Selftest shows extension disabled
- But Enabled = Yes in configuration

**Solution:**
1. Clear all caches:
   ```bash
   bin/magento cache:flush
   ```
2. Check correct scope - setting might be disabled at website/store level
3. Verify configuration is saved (click Save Config)

---

### Issue: Selftest fails - "Integration Token Invalid"

**Symptoms:**
- Selftest shows token invalid
- Token displays in admin but doesn't work

**Solution:**
1. Regenerate the token:
   ```bash
   bin/magento reloadify:integration --update=1
   ```
2. Update the new token in Reloadify
3. Run selftest again

**If keeps failing:**
- Check System > Integrations for duplicate Reloadify entries
- Delete old integrations, keep only one active

---

### Issue: Large debug log files

**Symptoms:**
- `var/log/reloadify/debug.log` is gigabytes in size
- Disk space running low

**Solution:**
1. Disable debug mode:
   Stores > Configuration > Reloadify > Debug & Logging > Debug Mode = No
2. Clear old logs:
   ```bash
   rm var/log/reloadify/debug.log
   ```

**Prevention:**
Only enable debug mode when actively troubleshooting. Disable when done.

---

## Debug Mode

### Enabling Debug Mode

Navigate to: Stores > Configuration > Reloadify > General > Debug & Logging

Set **Debug Mode** to **Yes** and save.

### Log Locations

| Log | Location | Contains |
|-----|----------|----------|
| Debug | `var/log/reloadify/debug.log` | API requests, responses, processing details |
| Error | `var/log/reloadify/error.log` | Errors and exceptions (always logged) |

### Viewing Logs

Via CLI:
```bash
# Recent debug entries
tail -100 var/log/reloadify/debug.log

# Recent errors
tail -100 var/log/reloadify/error.log

# Follow logs in real-time
tail -f var/log/reloadify/debug.log
```

Via Admin:
Use the "Show Debug Log" and "Show Error Log" buttons in Debug & Logging section.

### What to Look For

- **API request URLs** - verify correct endpoints being called
- **Response codes** - 200 = success, 401 = auth issue, 500 = server error
- **Product/order IDs** - find specific items in the sync
- **Error messages** - direct indication of what's wrong

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-reloadify/) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support/) - Get help from our team
