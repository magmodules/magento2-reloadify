/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { test, expect } from '@playwright/test';
import { admin, list, reloadify } from '../../support/Reloadify';
import { inContainer, magento, magentoRoot } from '../../support/Container';
import { state } from '../../support/State';

/** The extension version from the installed composer.json, and Magento's version from bin/magento. */
function installedVersions(): { extension: string; magento: string | undefined } {
  const composer = JSON.parse(
    inContainer(
      `cd ${magentoRoot()} && php -r 'require "app/autoload.php"; echo file_get_contents((new \\Magento\\Framework\\Component\\ComponentRegistrar())->getPath("module", "Magmodules_Reloadify") . "/composer.json");'`
    )
  );
  return {
    extension: composer.version ?? '0.0.0',
    magento: magento('--version').match(/(\d+\.\d+\.\d+(-p\d+)?)/)?.[1],
  };
}

/**
 * The settings route tells Reloadify whether the module is on and which versions it talks to, and
 * the version route gives the same versions without the module switch; the languages are the store
 * views, compared with Magento's own /V1/store/storeViews.
 */
test.describe('Settings and languages', () => {
  test('settings report the module enabled with the installed extension and Magento versions', async ({ request }) => {
    const [settings] = await list(request, 'V1/reloadify/settings');
    const versions = installedVersions();

    expect(settings.enabled).toBe(true);
    expect(settings.extension_version).toBe(`v${versions.extension}`);
    expect(settings.magento_version).toBe(versions.magento);
  });

  test('the version route reports the installed extension and Magento versions', async ({ request }) => {
    const response = await reloadify(request).get('V1/reloadify/version');
    const versions = installedVersions();

    expect(response.status, response.text).toBe(200);
    expect(response.body).toEqual({ extension_version: versions.extension, magento_version: versions.magento });
  });

  test('languages are the store views Magento reports, without the admin store', async ({ request }) => {
    const languages = await list(request, 'V1/reloadify/languages');
    const storeViews = (await admin(request).ok('GET', 'V1/store/storeViews')) as Array<Record<string, unknown>>;

    const shape = (store: Record<string, unknown>) => ({
      id: Number(store.id),
      code: store.code,
      name: store.name,
      website_id: Number(store.website_id),
    });
    expect(languages.map(shape).sort((a, b) => a.id - b.id)).toEqual(
      storeViews
        .filter((store) => Number(store.id) !== 0)
        .map(shape)
        .sort((a, b) => a.id - b.id)
    );
  });

  test('a single language answers with that store view only', async ({ request }) => {
    const languages = await list(request, `V1/reloadify/language/${state().storeId}`);
    expect(languages.map((language) => Number(language.id))).toEqual([state().storeId]);
    expect(languages[0].active).toBe(true);
    expect(String(languages[0].url)).toMatch(/^https?:\/\//);
  });
});
