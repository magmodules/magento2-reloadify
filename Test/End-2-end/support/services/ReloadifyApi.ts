/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import BaseApi from './BaseApi';

/**
 * What the baseline specs need from the shop: configuration, the console, cron and the logs, all
 * through the Magento container. The REST specs do not use this; they have support/Rest.ts.
 */
export default class ReloadifyApi extends BaseApi {
  protected modulePrefix(): string {
    return 'magmodules_reloadify';
  }
}
