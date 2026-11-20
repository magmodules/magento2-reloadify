/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import ReloadifyApi from 'Services/ReloadifyApi';

/**
 * Everything the baseline specs need to know about this module, and the only file of the baseline
 * layer that is written for it.
 *
 * The specs themselves (admin routes, everything on, everything off) are derived from the module's
 * own XML and controllers, so they keep working when someone adds a route or a configuration
 * field. What they cannot derive is which configuration sections exist, which CLI commands there
 * are, and what "the module does its own work" means. That is what this file answers.
 */

/**
 * The module's configuration sections, in the order the admin shows them.
 *
 * Spelled out as string literals, never as an imported constant: BaselineFixtureTest reads this
 * file as text and compares the list with etc/adminhtml/system.xml, and a constant reads to it as
 * an empty list - so the test passes while comparing nothing. Same for CLI_COMMANDS below.
 */
export const SECTIONS = ['magmodules_reloadify'];

/**
 * The first segment of every configuration path this module owns. Not derived from the section ids
 * above: a section id and the path its fields write are different strings that only happen to
 * coincide here.
 */
export const CONFIG_PREFIXES = ['magmodules_reloadify'];

/** Every console command the module registers. */
export const CLI_COMMANDS = ['reloadify:selftest', 'reloadify:integration'];

/**
 * Arguments a command cannot run without, by command name. Empty: both commands run bare.
 *
 * A command that declares a required argument answers "Not enough arguments" when the baseline
 * runs it bare - Symfony's answer, not the module's.
 */
export const CLI_ARGUMENTS: Record<string, string> = {};

/** A command as the baseline runs it: the name, plus whatever argument it cannot do without. */
export function cliInvocation(command: string): string {
  const argument = CLI_ARGUMENTS[command];

  return argument === undefined ? command : `${command} ${argument}`;
}

/** The module's own error log in var/log; the routes baseline fails on anything written to it. */
export const ERROR_LOG = 'reloadify-error.log';

/**
 * Admin pages that must render a grid in the browser. Empty: the module has no admin page of its
 * own, only the configuration section and the endpoints behind its buttons.
 */
export const ADMIN_PAGES: string[] = [];

/** Bring the module to the state the baseline assumes: enabled. Idempotent; each spec calls it. */
export async function prepareBaseline(api: ReloadifyApi): Promise<void> {
  await api.setMagentoConfig(process.env.BASE_URL || 'https://magento.test/', {
    'magmodules_reloadify/general/enable': '1',
  });
  api.flushAllCaches();
}

/** Nothing to undo: prepareBaseline creates no data. */
export async function cleanupBaseline(api: ReloadifyApi): Promise<void> {
  void api;
}

export interface WorkRun {
  label: string;
  exitCode: number;
  output: string;
  /** Whether the output has to report a count of one or more, rather than any number. */
  mustProduce: boolean;
}

/**
 * The module's own work, run once. Empty: Reloadify builds no feed and runs no import or sync of its
 * own. It answers when Reloadify calls its REST API, which the api project covers, and its two
 * console commands are run by the CLI checks of the baseline with every option on and off.
 */
export function runModuleWork(api: ReloadifyApi): WorkRun[] {
  void api;

  return [];
}

/** The message a command is allowed to refuse with when the module is switched off. */
export const DISABLED_MESSAGE = /Module is not enabled\./;
