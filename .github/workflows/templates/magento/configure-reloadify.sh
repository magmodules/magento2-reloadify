#!/bin/bash
#
# Copyright Magmodules.eu. All rights reserved.
# See COPYING.txt for license details.
#
# Prepares a Magento shop for the Reloadify E2E suite. Run it from the Magento root, on the host or
# inside the container: bash /path/to/configure-reloadify.sh
# In a container it finds the Magento root itself when started from somewhere else.

set -e

if [ ! -f bin/magento ]; then
    for root in /data /var/www/html; do
        if [ -f "$root/bin/magento" ]; then
            cd "$root"
            break
        fi
    done
fi

if [ ! -f bin/magento ]; then
    echo "Run this from the Magento root (bin/magento not found in $(pwd))" >&2
    exit 1
fi

# The module, and its integration (created by the data patch on setup:upgrade)
bin/magento config:set magmodules_reloadify/general/enable 1

# Reloadify connects with the integration's access token as a Bearer token. Since Magento 2.4.4
# that is refused unless this is on (Stores > Configuration > Services > OAuth > Consumer Settings).
bin/magento config:set oauth/consumer/enable_integration_as_bearer 1

# The admin token request is refused while two-factor authentication is enabled
if grep -q Magento_TwoFactorAuth app/etc/config.php; then
    bin/magento module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth -f 2>/dev/null || true
fi

# Orders through the REST API: flat rate shipping, check/money order, guest checkout
bin/magento config:set carriers/flatrate/active 1
bin/magento config:set payment/checkmo/active 1
bin/magento config:set checkout/options/guest_checkout 1

# Stock is managed: the variants spec checks that an open order lowers the salable quantity
bin/magento config:set cataloginventory/item_options/manage_stock 1

# No mail transport needed for order, invoice and shipment e-mails
bin/magento config:set system/smtp/disable 1

bin/magento cache:flush
