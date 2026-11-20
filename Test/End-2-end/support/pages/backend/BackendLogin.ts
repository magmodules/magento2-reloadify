/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { Page } from '@playwright/test';
import { adminUrl } from '../../helpers/AdminUrl';
import { gotoSettled } from '../../helpers/gotoSettled';

export default class BackendLogin {
  async login(page: Page, maxRetries = 3): Promise<void> {
    const username = process.env.MAGENTO_ADMIN_USER || 'exampleuser';
    const password = process.env.MAGENTO_ADMIN_PASS || 'examplepassword123';

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      await gotoSettled(page, adminUrl('/admin'));
      await page.getByLabel('Username').fill(username);
      await page.getByLabel('Password').fill(password);
      await page.getByRole('button', { name: 'Sign in' }).click();

      try {
        await page.waitForURL('**/admin/dashboard/**', { timeout: 30000, waitUntil: 'domcontentloaded' });

        // Dismiss Adobe AdminAnalytics "Allow usage data" modal if present
        const dontAllowBtn = page.locator('.modal-popup button:has-text("Don\'t Allow")');
        if (await dontAllowBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
          await dontAllowBtn.click();
        }

        return;
      } catch {
        if (attempt === maxRetries) {
          throw new Error(`Admin login failed after ${maxRetries} attempts`);
        }
        await page.waitForTimeout(2000);
      }
    }
  }
}
