# Reloadify for Magento 2

Connect your Magento 2 store to the [Reloadify](https://www.magmodules.eu/magento2-reloadify.html) marketing automation platform. This extension syncs your customer data, products, orders, and abandoned carts automatically, so Reloadify can power personalized email campaigns and triggered flows based on real purchase behavior.

## Features

- Automatic sync of products, categories, and variants
- Customer profiles and newsletter subscriber sync
- Order history and abandoned cart data
- Cart restore links for abandoned cart emails
- PWA/headless frontend support
- Flexible product attribute mapping
- Delta sync for efficient product updates
- Debug logging for troubleshooting

## Requirements

- Magento 2.3.x or higher
- PHP 7.4 or higher

## Installation

```bash
composer require magmodules/magento2-reloadify
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Quick Start

1. Enable the module at **Stores > Configuration > Reloadify > General**
2. Generate an integration token via CLI: `bin/magento reloadify:integration`
3. Copy the access token to your Reloadify account
4. Configure attribute mapping if needed
5. Run selftest to verify: `bin/magento reloadify:selftest`

## Documentation

**English:**

- [Getting Started](docs/QUICKSTART.md) - Get up and running in 5 minutes
- [Configuration Guide](docs/CONFIGURATION.md) - Complete configuration reference
- [Best Practices](docs/BEST_PRACTICES.md) - Recommended setups and examples
- [Troubleshooting](docs/TROUBLESHOOTING.md) - Common issues and solutions
- [CLI Commands](docs/CLI_COMMANDS.md) - Command line tools

**Nederlands:**

- [Aan de slag](docs/QUICKSTART_NL.md) - In 5 minuten aan de slag
- [Configuratie](docs/CONFIGURATION_NL.md) - Volledige configuratie referentie
- [Best Practices](docs/BEST_PRACTICES_NL.md) - Aanbevolen instellingen en voorbeelden
- [Probleemoplossing](docs/TROUBLESHOOTING_NL.md) - Veelvoorkomende problemen en oplossingen
- [CLI Commando's](docs/CLI_COMMANDS_NL.md) - Command line tools

## How it Works

The extension creates a secure REST API that Reloadify uses to fetch your store data:

```
GET /V1/reloadify/products      → Product catalog
GET /V1/reloadify/orders        → Order history
GET /V1/reloadify/carts         → Abandoned carts
GET /V1/reloadify/profiles      → Customer profiles
GET /V1/reloadify/subscribers   → Newsletter subscribers
```

All API calls require the integration token for authentication. Data syncs run via Reloadify's servers, not via Magento cron, so there's no performance impact on your store.

## Support

- **Product Page:** [Reloadify for Magento 2](https://www.magmodules.eu/magento2-reloadify.html)
- **Documentation:** [Magmodules Help Center](https://www.magmodules.eu/help/magento2-reloadify/)
- **Support:** [Contact Magmodules Support](https://www.magmodules.eu/support/)

## License

See COPYING.txt

## Copyright

Copyright © Magmodules.eu. All rights reserved.
