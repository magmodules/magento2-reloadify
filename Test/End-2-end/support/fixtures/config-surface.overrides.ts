/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import type { SurfaceOverrides } from 'Helpers/ConfigSurface';

/**
 * Fields the generic "everything on" chooser must not set blind. Every entry carries its reason;
 * this file is reviewed like code, it is not a place to hide a field that fails.
 */
export const CONFIG_SURFACE_OVERRIDES: SurfaceOverrides = {
  'magmodules_reloadify/general/enable': { value: '1', reason: 'the module stays enabled for the rest of the run' },
  'magmodules_reloadify/general/token': { value: null, reason: 'a read-only field that shows the integration token; it stores nothing, and the token itself is covered by the auth spec' },
};
