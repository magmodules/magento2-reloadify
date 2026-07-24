# CLI Commands

Command line tools for managing the [Reloadify](https://www.magmodules.eu/magento2-reloadify.html) integration. These commands are useful for initial setup and troubleshooting.

## Available Commands

| Command | Description |
|---------|-------------|
| `reloadify:integration` | Create or update the integration token |
| `reloadify:selftest` | Run diagnostic tests on the module |

---

## reloadify:integration

Creates the Magento integration and generates an access token for Reloadify.

```bash
bin/magento reloadify:integration
```

**Output:**
```
Integration token: abc123xyz...
```

Copy this token to your Reloadify dashboard to authenticate the connection.

### Options

| Option | Description |
|--------|-------------|
| `--update=1` | Regenerate the token (invalidates old token) |

### Regenerating a Token

If you need a new token (security rotation, compromised token):

```bash
bin/magento reloadify:integration --update=1
```

**Important:** After regenerating, immediately update the token in Reloadify. The old token stops working immediately.

### When to Use

- **Initial setup** - Generate token after installing the module
- **Token rotation** - Periodically regenerate for security
- **After restore** - If database was restored without the integration

---

## reloadify:selftest

Runs diagnostic checks to verify the module is configured correctly.

```bash
bin/magento reloadify:selftest
```

**Output:**
```
Extension Status: success - Enabled
Extension Version: success - 1.15.0
Magento Version: success - 2.4.6
PHP Version: success - 8.2.0
```

### What It Tests

| Test | Checks |
|------|--------|
| Extension Status | Module is enabled in configuration |
| Extension Version | Current installed version |
| Magento Version | Magento version compatibility |
| PHP Version | PHP version compatibility |

### Interpreting Results

- **success** - Test passed, no action needed
- **failed** - Issue detected, check the message for details

### When to Use

- **After installation** - Verify everything is set up correctly
- **Troubleshooting** - First step when something isn't working
- **After upgrades** - Confirm compatibility after Magento or module updates

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-reloadify/) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support/) - Get help from our team
