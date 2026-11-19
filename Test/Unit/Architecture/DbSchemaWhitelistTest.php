<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Reloadify\Test\Unit\Architecture;

use Magento\Framework\DB\ExpressionConverter;
use PHPUnit\Framework\TestCase;

/**
 * db_schema_whitelist.json has to agree with db_schema.xml in both directions, and neither
 * disagreement errors anywhere.
 *
 * Something declared but not whitelisted is something Magento will not drop again from a shop that
 * already has it: that is what the whitelist is for. Something whitelisted but no longer declared
 * is a rename nobody finished: an index renamed in db_schema.xml with the old name left in the
 * whitelist means the new one was never whitelisted at all.
 *
 * Generic: it reads whatever the module declares, so it needs no per-module expectations.
 */
class DbSchemaWhitelistTest extends TestCase
{
    public function testEveryDeclaredColumnIndexAndConstraintIsWhitelisted(): void
    {
        $whitelist = $this->whitelist();
        $missing = [];

        foreach ($this->schema()->xpath('//table') as $table) {
            $name = (string)$table['name'];
            self::assertArrayHasKey($name, $whitelist, "$name is whitelisted");

            foreach (['column', 'index', 'constraint'] as $group) {
                foreach ($table->{$group} ?? [] as $declared) {
                    $id = $group === 'column' ? (string)$declared['name'] : $this->whitelistedName($declared);

                    if (!array_key_exists($id, $whitelist[$name][$group] ?? [])) {
                        $missing[] = sprintf('%s.%s (%s)', $name, $id, $group);
                    }
                }
            }
        }

        self::assertSame(
            [],
            $missing,
            "declared but not whitelisted, so setup:upgrade will not drop these again:\n" . implode("\n", $missing)
        );
    }

    public function testTheWhitelistNamesNothingTheSchemaNoLongerDeclares(): void
    {
        $whitelist = $this->whitelist();

        $declared = [];
        foreach ($this->schema()->xpath('//table') as $table) {
            $name = (string)$table['name'];
            foreach ($table->column ?? [] as $column) {
                $declared[$name]['column'][] = (string)$column['name'];
            }
            foreach ($table->index ?? [] as $index) {
                $declared[$name]['index'][] = (string)$index['referenceId'];
            }
            foreach ($table->constraint ?? [] as $constraint) {
                $declared[$name]['constraint'][] = $this->whitelistedName($constraint);
            }
        }

        $stale = [];
        foreach ($whitelist as $table => $groups) {
            foreach ($groups as $group => $entries) {
                foreach (array_keys($entries) as $id) {
                    if (!in_array((string)$id, $declared[$table][$group] ?? [], true)) {
                        $stale[] = sprintf('%s.%s (%s)', $table, $id, $group);
                    }
                }
            }
        }

        self::assertSame([], $stale, "whitelisted but not declared:\n" . implode("\n", $stale));
    }

    /**
     * @return array<string, array<string, array<string, bool>>>
     */
    /**
     * The name Magento writes into the whitelist for an index or constraint
     *
     * For a foreign key that is not the referenceId but the database name Magento derives from the table and
     * columns, shortened to MySQL's 64 characters (abbreviated, or FK_<md5>), which is what
     * setup:db-declaration:generate-whitelist writes. Everything else is whitelisted under its referenceId.
     */
    private function whitelistedName(\SimpleXMLElement $element): string
    {
        if ((string)$element['xsi:type'] !== 'foreign' && (string)$element->attributes('xsi', true)['type'] !== 'foreign') {
            return (string)$element['referenceId'];
        }

        return strtoupper(ExpressionConverter::shortenEntityName(sprintf(
            '%s_%s_%s_%s',
            (string)$element['table'],
            (string)$element['column'],
            (string)$element['referenceTable'],
            (string)$element['referenceColumn']
        ), 'fk_'));
    }

    private function whitelist(): array
    {
        $file = $this->modulePath('etc/db_schema_whitelist.json');

        if (!is_file($file)) {
            self::markTestSkipped('The module declares no database schema.');
        }

        $whitelist = json_decode((string)file_get_contents($file), true);
        self::assertIsArray($whitelist);

        return $whitelist;
    }

    private function schema(): \SimpleXMLElement
    {
        $file = $this->modulePath('etc/db_schema.xml');

        if (!is_file($file)) {
            self::markTestSkipped('The module declares no database schema.');
        }

        $xml = simplexml_load_file($file);
        self::assertNotFalse($xml);
        $xml->registerXPathNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        return $xml;
    }

    private function modulePath(string $relative): string
    {
        return dirname(__DIR__, 3) . '/' . $relative;
    }
}
