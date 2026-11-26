<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The admin button script of a module only acts inside that module's own config form.
 *
 * Other extensions render the same ids (mm-ui-button_debug, mm-ui-result_debug-modal, …) on their
 * own config pages, and a shop may have several of them installed. A script that binds
 * `$(document).on('click', '[id^=mm-ui-button]')` without a scope, or that is loaded on every admin
 * page through requirejs `deps`, also fires for the buttons of the other modules: a second fetch per
 * click, and for an action it does not know a TypeError before `loader('hide')`, so the spinner
 * never goes away on the other module's page. The same goes for the modal
 * lookup in initModal: unscoped it initialises the other module's modal. And since the modal widget
 * moves the element out of the form, the element has to be resolved once, not per statement.
 */
class AdminButtonScriptTest extends TestCase
{
    #[DataProvider('scriptProvider')]
    public function testDelegatedClickHandlersAreScopedToTheModuleForm(string $file, string $source): void
    {
        preg_match_all('/\$\(document\)\s*\.on\(\s*[\'"]click[\'"]\s*,\s*([^,]+),/', $source, $matches);
        $unscoped = [];
        foreach ($matches[1] as $selector) {
            if (str_contains($selector, 'mm-ui-button') && !str_contains($selector, 'form[action*=')) {
                $unscoped[] = trim($selector);
            }
        }

        self::assertSame([], $unscoped, sprintf(
            '%s delegates clicks on the shared button ids without a form scope: %s',
            $file,
            implode(', ', $unscoped)
        ));
    }

    #[DataProvider('scriptProvider')]
    public function testModalLookupsAreScopedToTheModuleForm(string $file, string $source): void
    {
        preg_match_all(
            '/`([^`\n]*#mm-ui-result_[^`\n]*-modal[^`\n]*)`|\'([^\'\n]*#mm-ui-result_[^\'\n]*-modal[^\'\n]*)\'|"([^"\n]*#mm-ui-result_[^"\n]*-modal[^"\n]*)"/',
            $source,
            $matches
        );
        $selectors = array_filter(array_merge($matches[1], $matches[2], $matches[3]));
        $unscoped = array_values(array_filter(
            $selectors,
            static fn (string $selector): bool => !str_contains($selector, 'form[action*=')
                && !str_contains($selector, '${action}')
        ));

        self::assertSame([], $unscoped, sprintf(
            '%s looks up a result modal without a form scope: %s',
            $file,
            implode(', ', $unscoped)
        ));
    }

    #[DataProvider('scriptProvider')]
    public function testTheLoaderIsHiddenInFinally(string $file, string $source): void
    {
        if (!str_contains($source, 'fetch(')) {
            self::markTestSkipped($file . ' does not fetch');
        }

        self::assertStringContainsString(
            '.finally(',
            $source,
            $file . ' hides the loader in then() only; a handler that throws leaves the spinner on screen'
        );
    }

    #[DataProvider('scriptProvider')]
    public function testTheChangelogGoesThroughTheSanitizer(string $file, string $source): void
    {
        if (!str_contains($source, '.changelog')) {
            self::markTestSkipped($file . ' does not render a changelog');
        }

        // The changelog is the only response value that is HTML on purpose. A regex allow-list over the
        // escaped string re-opened any tag whose name matched, attributes included; the DOMParser walk
        // keeps the tags of the changelog format and nothing else.
        self::assertMatchesRegularExpression(
            '/sanitizeChangelog\\(\\s*\\w+\\[\\w+\\]\\.changelog\\s*\\)/',
            $source,
            $file . ' renders the changelog without sanitizeChangelog()'
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scriptProvider(): iterable
    {
        $root = dirname(__DIR__, 3);
        $found = false;
        foreach (glob($root . '/view/adminhtml/web/js/*.js') ?: [] as $path) {
            $source = (string)file_get_contents($path);
            if (!str_contains($source, 'mm-ui-button') && !str_contains($source, 'mm-ui-result_')) {
                continue;
            }
            $found = true;
            yield substr($path, strlen($root) + 1) => [substr($path, strlen($root) + 1), $source];
        }
        if (!$found) {
            yield 'no admin button script' => ['(none)', ''];
        }
    }
}
