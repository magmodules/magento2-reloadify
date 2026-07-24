# Quick Start Guide

This is the quick start guide for [Reloadify](https://www.magmodules.eu/magento2-reloadify.html). We'll get you connected to the Reloadify platform in about 5 minutes. This covers enabling the module, generating your access token, and verifying the connection works. Once done, Reloadify can start syncing your store data automatically.

## Prerequisites

- Module installed and cache cleared
- SSH access to run CLI commands
- A Reloadify account (sign up at reloadify.com)

## Step 1: Enable the Module

Navigate to: **Stores > Configuration > Reloadify > General**

Set **Enabled** to **Yes** and save.

## Step 2: Generate Integration Token

Run this command via SSH:

```bash
bin/magento reloadify:integration
```

This creates a Magento integration and outputs an access token. Copy this token - you'll need it for Reloadify.

**Tip:** The token is also visible in the admin panel under **General > Access Token** after generation.

## Step 3: Connect Reloadify

1. Log into your Reloadify dashboard
2. Go to your store connection settings
3. Paste the access token from Step 2
4. Save the connection

Reloadify will now use this token to authenticate API requests to your store.

## Step 4: Configure Attribute Mapping (Optional)

If your product data uses custom attributes, configure the mapping:

Navigate to: **Stores > Configuration > Reloadify > General > Attributes**

Map your attributes:
- **EAN** → Your barcode/GTIN attribute (or SKU)
- **Brand** → Your manufacturer/brand attribute
- **Description** → Usually the default description works

Most stores can skip this - the defaults work for standard Magento setups.

## Step 5: Verify Connection

Run the selftest to confirm everything works:

```bash
bin/magento reloadify:selftest
```

You should see all tests pass. If any fail, check the Troubleshooting guide.

You can also run the selftest from admin: **Stores > Configuration > Reloadify > General > Debug & Logging > Run Selftest**

## What Happens Next

Once connected, Reloadify automatically syncs:
- Products and variants
- Categories
- Customer profiles
- Newsletter subscribers
- Orders
- Abandoned carts

The sync runs from Reloadify's servers via the API - no cron jobs or performance impact on your store.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-reloadify/) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support/) - Get help from our team
