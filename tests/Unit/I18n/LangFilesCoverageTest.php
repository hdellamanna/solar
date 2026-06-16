<?php

namespace Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FASE 7 — i18n tri-língue. Locks in the cross-locale key
 * parity of every lang file under `lang/{pt-BR,es,en}/*.php`.
 *
 * The design doc's "Lang files must be key-for-key identical
 * across locales" invariant is the single most important
 * invariant the i18n system relies on: the `__()` lookup
 * succeeds in any of the 3 locales for every key, the
 * TutorialController's lang-file reader does not blow up when
 * it walks the chapter tree, and the localized mail templates
 * never hit a missing key.
 *
 * This test walks all top-level lang files
 * (`app`, `enums`, `validation`, `auth`, `mail`, `tutorial`)
 * and asserts:
 *
 *   - Every key in `lang/pt-BR/<file>.php` exists in
 *     `lang/es/<file>.php` and `lang/en/<file>.php`.
 *   - Every key in `lang/es/<file>.php` exists in the other
 *     two (no locale adds a key not present in the other two).
 *   - Symmetric for `lang/en/<file>.php`.
 *
 * Nested arrays are walked recursively and the key path is
 * dot-joined (`enums.transaction.income`) — the test is
 * structure-aware, not just top-level.
 *
 * A diff is printed in the failure message so a future
 * contributor adding a new key can see exactly which locale
 * is missing the addition.
 */
class LangFilesCoverageTest extends TestCase
{
    /**
     * The set of top-level lang file names the design doc
     * locks in. A new lang file added in a future FASE
     * must be appended to this list to keep the parity
     * invariant explicit.
     *
     * @return array<int, array{0: string}>
     */
    public static function langFileProvider(): array
    {
        $names = ['app', 'enums', 'validation', 'auth', 'mail', 'tutorial'];
        $cases = [];
        foreach ($names as $name) {
            $cases[] = [$name];
        }
        return $cases;
    }

    /**
     * @dataProvider langFileProvider
     */
    #[DataProvider('langFileProvider')]
    public function test_lang_files_have_identical_key_sets_across_locales(string $name): void
    {
        $ptKeys = $this->collectKeys(lang_path("pt-BR/{$name}.php"));
        $esKeys = $this->collectKeys(lang_path("es/{$name}.php"));
        $enKeys = $this->collectKeys(lang_path("en/{$name}.php"));

        $ptHas = array_unique($ptKeys); sort($ptHas);
        $esHas = array_unique($esKeys); sort($esHas);
        $enHas = array_unique($enKeys); sort($enHas);

        // Missing-in-locale diagnostics: name each key that
        // is present in one locale but absent in another.
        $ptNotInEs = array_values(array_diff($ptHas, $esHas));
        $ptNotInEn = array_values(array_diff($ptHas, $enHas));
        $esNotInPt = array_values(array_diff($esHas, $ptHas));
        $esNotInEn = array_values(array_diff($esHas, $enHas));
        $enNotInPt = array_values(array_diff($enHas, $ptHas));
        $enNotInEs = array_values(array_diff($enHas, $esHas));

        $this->assertSame(
            [],
            $ptNotInEs,
            "lang/{$name}.php: keys present in pt-BR but missing in es — " . $this->formatDiff($ptNotInEs)
        );
        $this->assertSame(
            [],
            $ptNotInEn,
            "lang/{$name}.php: keys present in pt-BR but missing in en — " . $this->formatDiff($ptNotInEn)
        );
        $this->assertSame(
            [],
            $esNotInPt,
            "lang/{$name}.php: keys present in es but missing in pt-BR — " . $this->formatDiff($esNotInPt)
        );
        $this->assertSame(
            [],
            $esNotInEn,
            "lang/{$name}.php: keys present in es but missing in en — " . $this->formatDiff($esNotInEn)
        );
        $this->assertSame(
            [],
            $enNotInPt,
            "lang/{$name}.php: keys present in en but missing in pt-BR — " . $this->formatDiff($enNotInPt)
        );
        $this->assertSame(
            [],
            $enNotInEs,
            "lang/{$name}.php: keys present in en but missing in es — " . $this->formatDiff($enNotInEs)
        );
    }

    /**
     * Read a lang PHP file and return the list of all
     * dot-joined leaf keys (nested arrays are walked
     * recursively).
     *
     * @return array<int, string>
     */
    private function collectKeys(string $path): array
    {
        $this->assertFileExists($path, "lang file missing: {$path}");

        $arr = require $path;
        $this->assertIsArray(
            $arr,
            "lang file at {$path} must return an array; got " . gettype($arr)
        );

        return $this->walk($arr, '');
    }

    /**
     * Recursive walker that joins nested array keys with
     * `.` and emits the leaf keys (the ones whose values
     * are strings).
     *
     * @return array<int, string>
     */
    private function walk(array $arr, string $prefix): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                $out = array_merge($out, $this->walk($v, $key));
            } else {
                $out[] = $key;
            }
        }
        return $out;
    }

    /**
     * @param array<int, string> $keys
     */
    private function formatDiff(array $keys): string
    {
        if (empty($keys)) {
            return '(no diff)';
        }
        return implode(', ', array_slice($keys, 0, 20))
            . (count($keys) > 20 ? ' … (' . count($keys) . ' total)' : '');
    }
}
