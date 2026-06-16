<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Schema drift guard.
 *
 * Asserts that every column written through Peanut_Festival_Database::insert()
 * / ::update() to a pf_* custom table actually exists in that table's schema.
 *
 * The authoritative schema is the union of:
 *   - the CREATE TABLE statements in includes/class-activator.php, and
 *   - the columns added by migrations in includes/class-migrations.php
 *     (ALTER TABLE ... ADD COLUMN, plus any CREATE TABLE in a migration).
 *
 * This catches the class of bug where application code persists a column that
 * was never declared in the schema (e.g. Shows::complete() writing
 * `completed_at` to pf_shows before that column existed), which silently
 * fails on real MySQL with "Unknown column".
 */

use PHPUnit\Framework\TestCase;

class SchemaDriftTest extends TestCase {

    /** @var string */
    private static string $includes;

    public static function setUpBeforeClass(): void {
        self::$includes = dirname(__DIR__, 2) . '/includes';
    }

    /**
     * Build table => [columns] from activator + migrations.
     *
     * @return array<string, array<int, string>>
     */
    private function build_schema(): array {
        $schema = [];

        // --- Activator CREATE TABLE blocks --------------------------------
        $activator = file_get_contents(self::$includes . '/class-activator.php');

        // Map $table_var => pf_table  (e.g. $table_shows => shows)
        $var_to_table = [];
        if (preg_match_all(
            '/\$(\w+)\s*=\s*\$wpdb->prefix\s*\.\s*\'pf_(\w+)\'/',
            $activator,
            $m,
            PREG_SET_ORDER
        )) {
            foreach ($m as $row) {
                $var_to_table[$row[1]] = $row[2];
            }
        }

        // Each CREATE TABLE $table_var ( ... );
        if (preg_match_all(
            '/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?\$(\w+)\s*\((.*?)\)\s*\$charset_collate/s',
            $activator,
            $blocks,
            PREG_SET_ORDER
        )) {
            foreach ($blocks as $block) {
                $var = $block[1];
                if (!isset($var_to_table[$var])) {
                    continue;
                }
                $table = $var_to_table[$var];
                $schema[$table] = array_merge(
                    $schema[$table] ?? [],
                    $this->extract_columns($block[2])
                );
            }
        }

        // --- Migration-added columns / tables -----------------------------
        $migrations = file_get_contents(self::$includes . '/class-migrations.php');

        // ALTER TABLE $table ADD COLUMN col ...   where $table is assigned just above
        // The migration file REUSES variable names (e.g. $table is reassigned in
        // each migration method), so we must resolve every $var reference to the
        // NEAREST PRECEDING assignment, not a single flat map.
        $assignments = []; // [ ['var'=>x, 'table'=>y, 'pos'=>n], ... ] ordered by pos
        if (preg_match_all(
            '/\$(\w+)\s*=\s*\$wpdb->prefix\s*\.\s*\'pf_(\w+)\'/',
            $migrations,
            $mv,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            foreach ($mv as $row) {
                $assignments[] = [
                    'var' => $row[1][0],
                    'table' => $row[2][0],
                    'pos' => $row[0][1],
                ];
            }
        }

        $resolve = function (string $var, int $pos) use ($assignments): ?string {
            $best = null;
            foreach ($assignments as $a) {
                if ($a['var'] === $var && $a['pos'] < $pos) {
                    if ($best === null || $a['pos'] > $best['pos']) {
                        $best = $a;
                    }
                }
            }
            return $best['table'] ?? null;
        };

        if (preg_match_all(
            '/ALTER TABLE\s+\$(\w+)\s+ADD COLUMN\s+`?(\w+)`?/i',
            $migrations,
            $alters,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            foreach ($alters as $alter) {
                $table = $resolve($alter[1][0], $alter[0][1]);
                if ($table === null) {
                    continue;
                }
                $schema[$table][] = $alter[2][0];
            }
        }

        // CREATE TABLE IF NOT EXISTS $var ( ... ) inside migrations
        if (preg_match_all(
            '/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?\$(\w+)\s*\((.*?)\)\s*\$charset_collate/s',
            $migrations,
            $mblocks,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            foreach ($mblocks as $block) {
                $table = $resolve($block[1][0], $block[0][1]);
                if ($table === null) {
                    continue;
                }
                $schema[$table] = array_merge(
                    $schema[$table] ?? [],
                    $this->extract_columns($block[2][0])
                );
            }
        }

        // Dedupe
        foreach ($schema as $t => $cols) {
            $schema[$t] = array_values(array_unique($cols));
        }

        return $schema;
    }

    /**
     * Extract column names from the body of a CREATE TABLE statement.
     *
     * @param string $body
     * @return array<int, string>
     */
    private function extract_columns(string $body): array {
        $columns = [];
        foreach (preg_split('/,\s*\n/', $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Skip key / constraint definitions.
            if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|INDEX|CONSTRAINT|FULLTEXT)/i', $line)) {
                continue;
            }
            if (preg_match('/^`?(\w+)`?\s+/', $line, $cm)) {
                $columns[] = $cm[1];
            }
        }
        return $columns;
    }

    /**
     * Build "ClassName::method" => table for thin passthrough writers whose
     * data array flows straight into Database::insert/update.
     *
     * e.g. Shows::update($id, $data) -> Database::update('shows', $data, ...)
     *
     * @return array<string, array{table:string, op:string}>
     */
    private function build_passthrough_map(): array {
        $map = [];
        foreach (glob(self::$includes . '/class-*.php') as $file) {
            if (basename($file) === 'class-database.php') {
                continue;
            }
            $src = file_get_contents($file);
            if (!preg_match('/class\s+(\w+)/', $src, $cm)) {
                continue;
            }
            $class = $cm[1];

            // Find method bodies of static create()/update() and see whether the
            // method's $data parameter is passed straight to Database::insert/update.
            if (preg_match_all(
                '/function\s+(create|update)\s*\([^)]*\)[^{]*\{(.*?)\n    \}/s',
                $src,
                $methods,
                PREG_SET_ORDER
            )) {
                foreach ($methods as $method) {
                    $name = $method[1];
                    $body = $method[2];
                    if (preg_match(
                        '/Peanut_Festival_Database::(insert|update)\(\s*\'(\w+)\'\s*,\s*\$data\b/',
                        $body,
                        $pm
                    )) {
                        $map["$class::$name"] = ['table' => $pm[2], 'op' => $pm[1]];
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Collect [table, column, location] for every write whose data array is an
     * inline literal — both direct Database::insert/update('t', [...]) calls and
     * passthrough writers (self::update($id, [...]) etc.).
     *
     * @param array<string, array{table:string, op:string}> $passthrough
     * @return array<int, array{table:string, column:string, where:string}>
     */
    private function collect_written_columns(array $passthrough): array {
        $written = [];

        foreach (glob(self::$includes . '/class-*.php') as $file) {
            if (basename($file) === 'class-database.php') {
                continue;
            }
            $src = file_get_contents($file);
            $base = basename($file);
            $currentClass = preg_match('/class\s+(\w+)/', $src, $cm) ? $cm[1] : '';

            // 1) Direct Database::insert/update('table', [ ... ])
            if (preg_match_all(
                '/Peanut_Festival_Database::(?:insert|update)\(\s*\'(\w+)\'\s*,\s*(\[)/',
                $src,
                $direct,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            )) {
                foreach ($direct as $d) {
                    $table = $d[1][0];
                    $arrayStart = $d[2][1];
                    $literal = $this->slice_array_literal($src, $arrayStart);
                    foreach ($this->extract_array_keys($literal) as $col) {
                        $written[] = ['table' => $table, 'column' => $col, 'where' => $base];
                    }
                }
            }

            // 2) Passthrough writers: self::update(...,[...]) / Class::create([...])
            foreach ($passthrough as $fqmethod => $info) {
                [$pclass, $pmethod] = explode('::', $fqmethod);
                // A call resolves to this passthrough only when:
                //   - it's self::method() inside the SAME class that defines it, or
                //   - it's an explicit OtherClass::method() naming that class.
                if ($pclass === $currentClass) {
                    $caller = '(?:self|' . preg_quote($pclass, '/') . ')';
                } else {
                    $caller = preg_quote($pclass, '/');
                }
                $pattern = '/' . $caller . '::'
                    . preg_quote($pmethod, '/')
                    . '\s*\(/';
                if (!preg_match_all($pattern, $src, $calls, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($calls[0] as $call) {
                    $openParen = $call[1] + strlen($call[0]) - 1;
                    // Find the first inline array literal argument within this call.
                    $bracket = strpos($src, '[', $openParen);
                    $closeParen = $this->match_delimiter($src, $openParen, '(', ')');
                    if ($bracket === false || $closeParen === false || $bracket > $closeParen) {
                        continue;
                    }
                    $literal = $this->slice_array_literal($src, $bracket);
                    foreach ($this->extract_array_keys($literal) as $col) {
                        $written[] = [
                            'table' => $info['table'],
                            'column' => $col,
                            'where' => $base,
                        ];
                    }
                }
            }
        }

        return $written;
    }

    /**
     * Given an offset pointing at '[', return the full bracketed literal.
     */
    private function slice_array_literal(string $src, int $open): string {
        $close = $this->match_delimiter($src, $open, '[', ']');
        if ($close === false) {
            return '';
        }
        return substr($src, $open, $close - $open + 1);
    }

    /**
     * Find the matching close delimiter for the opener at $open.
     */
    private function match_delimiter(string $src, int $open, string $o, string $c) {
        $depth = 0;
        $len = strlen($src);
        for ($i = $open; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === $o) {
                $depth++;
            } elseif ($ch === $c) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return false;
    }

    /**
     * Extract top-level 'key' => string keys from an array literal.
     *
     * @return array<int, string>
     */
    private function extract_array_keys(string $literal): array {
        $keys = [];
        if (preg_match_all('/[\'"](\w+)[\'"]\s*=>/', $literal, $m)) {
            $keys = $m[1];
        }
        return array_values(array_unique($keys));
    }

    public function test_every_written_column_exists_in_schema(): void {
        $schema = $this->build_schema();
        $passthrough = $this->build_passthrough_map();
        $written = $this->collect_written_columns($passthrough);

        $this->assertNotEmpty($schema, 'Schema parse produced no tables');
        $this->assertNotEmpty($written, 'No written columns detected');

        $drift = [];
        foreach ($written as $w) {
            $table = $w['table'];
            // Only guard tables we actually have a schema for.
            if (!isset($schema[$table])) {
                continue;
            }
            if (!in_array($w['column'], $schema[$table], true)) {
                $drift[] = sprintf(
                    "pf_%s.%s written in %s but not in schema",
                    $table,
                    $w['column'],
                    $w['where']
                );
            }
        }

        $this->assertSame(
            [],
            $drift,
            "Schema drift detected:\n" . implode("\n", $drift)
        );
    }

    public function test_shows_table_has_completed_at_column(): void {
        // Regression guard for Shows::complete() writing completed_at.
        $schema = $this->build_schema();
        $this->assertArrayHasKey('shows', $schema);
        $this->assertContains(
            'completed_at',
            $schema['shows'],
            'pf_shows must declare completed_at (written by Shows::complete())'
        );
    }
}
