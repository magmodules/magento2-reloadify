/**
 * Build an absolute admin URL from a relative admin path.
 *
 * Admin URL patterns:
 * - Custom module routes: /admin/{frontName}/{controller}/{action}
 *   Example: adminUrl('/admin/reloadify/selftest/index')
 *
 * - Core Magento routes: /admin/admin/{route}/{action}
 *   Example: adminConfigUrl('magmodules_reloadify')
 *
 * Check etc/adminhtml/routes.xml for the module's frontName.
 */
export function adminUrl(path: string): string {
  const baseURL = process.env.BASE_URL || 'https://magento-248.test/';
  return new URL(path, baseURL).toString();
}

/**
 * Build an admin system config URL for a given section.
 *
 * @example adminConfigUrl('magmodules_reloadify')
 */
export function adminConfigUrl(section: string): string {
  return adminUrl(`/admin/admin/system_config/edit/section/${section}`);
}
