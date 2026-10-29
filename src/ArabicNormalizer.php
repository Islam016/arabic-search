<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch;

/**
 * Normalizes Arabic text so that spelling variants compare as equal.
 *
 * "أحمد", "إحمد", "احمد" and "أَحْمَد" all become "احمد";
 * "مدرسة" and "مدرسه" both become "مدرسه"; "١٢٣" becomes "123".
 *
 * The same rules can be produced as a SQL expression (see sqlExpression()),
 * so a database column can be compared against a normalized search term.
 */
final class ArabicNormalizer
{
    /**
     * Harakat and other combining marks removed when stripping diacritics.
     *
     * U+0610–U+061A: Quranic annotation signs
     * U+064B–U+065F: tanween, fatha, damma, kasra, shadda, sukun, maddah/hamza above/below, ...
     * U+0670:        superscript (dagger) alef
     */
    private const DIACRITICS_PATTERN = '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u';

    /** The most common diacritics, used for the SQL expression (each one costs a REPLACE()). */
    private const SQL_DIACRITICS = [
        "\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}",
        "\u{0650}", "\u{0651}", "\u{0652}", "\u{0670}",
    ];

    private const TATWEEL = "\u{0640}";

    private const ALEF = [
        "\u{0623}" => "\u{0627}", // أ
        "\u{0625}" => "\u{0627}", // إ
        "\u{0622}" => "\u{0627}", // آ
        "\u{0671}" => "\u{0627}", // ٱ (alef wasla)
    ];

    private const YAA = [
        "\u{0649}" => "\u{064A}", // ى alef maqsura → ي
        "\u{06CC}" => "\u{064A}", // ی Persian yeh → ي
    ];

    private const KAF = [
        "\u{06A9}" => "\u{0643}", // ک Persian keheh → ك
    ];

    private const TAA_MARBUTA = [
        "\u{0629}" => "\u{0647}", // ة → ه
    ];

    private const HAMZA_CARRIERS = [
        "\u{0624}" => "\u{0621}", // ؤ → ء
        "\u{0626}" => "\u{0621}", // ئ → ء
    ];

    private const DIGITS = [
        // Arabic-Indic
        "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
        "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
        // Extended Arabic-Indic (Persian / Urdu)
        "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
        "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
    ];

    public function __construct(private readonly NormalizerOptions $options = new NormalizerOptions())
    {
    }

    public static function make(?NormalizerOptions $options = null): self
    {
        return new self($options ?? new NormalizerOptions());
    }

    public function options(): NormalizerOptions
    {
        return $this->options;
    }

    public function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if ($this->options->stripDiacritics) {
            $text = (string) preg_replace(self::DIACRITICS_PATTERN, '', $text);
        }

        $text = strtr($text, $this->characterMap());

        if ($this->options->lowercase) {
            $text = mb_strtolower($text, 'UTF-8');
        }

        if ($this->options->collapseWhitespace) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        }

        return $text;
    }

    /**
     * Normalized form of every word in the text (handy for building keyword indexes).
     *
     * @return list<string>
     */
    public function words(?string $text): array
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [],
            static fn (string $word): bool => $word !== '',
        ));
    }

    public function equals(?string $a, ?string $b): bool
    {
        return $this->normalize($a) === $this->normalize($b);
    }

    public function contains(?string $haystack, ?string $needle): bool
    {
        $needle = $this->normalize($needle);

        return $needle !== '' && str_contains($this->normalize($haystack), $needle);
    }

    /**
     * SQL expression that applies the same character rules to an (already quoted) column.
     *
     * - pgsql:  LOWER(TRANSLATE(...)), a single function call.
     * - sqlite: arabic_normalize(...), a PHP function registered on the connection with
     *           registerSqliteFunction() (SQLite cannot parse ~40 nested REPLACE() calls).
     * - mysql, mariadb, sqlsrv and anything else: LOWER(REPLACE(REPLACE(...))).
     *
     * Whitespace collapsing and the rare Quranic marks are not reproduced in SQL (except on
     * SQLite, which runs the PHP normalizer). For large tables prefer a stored normalized
     * column (see the HasArabicSearch trait) so the query can use an index.
     */
    public function sqlExpression(string $quotedColumn, string $driver = 'mysql'): string
    {
        if ($driver === 'sqlite') {
            return self::SQLITE_FUNCTION.'('.$quotedColumn.')';
        }

        $replacements = $this->sqlReplacements();

        if ($driver === 'pgsql') {
            $from = '';
            $to = '';
            $deleted = '';

            foreach ($replacements as $search => $replace) {
                if ($replace === '') {
                    $deleted .= $search;

                    continue;
                }

                $from .= $search;
                $to .= $replace;
            }

            // Characters at the end of "from" with no counterpart in "to" are removed.
            $expression = sprintf("TRANSLATE(%s, '%s', '%s')", $quotedColumn, $from.$deleted, $to);
        } else {
            $expression = $quotedColumn;

            foreach ($replacements as $search => $replace) {
                $expression = sprintf("REPLACE(%s, '%s', '%s')", $expression, $search, $replace);
            }
        }

        if ($this->options->lowercase) {
            $expression = sprintf('LOWER(%s)', $expression);
        }

        return $expression;
    }

    /** Name of the SQLite function created by registerSqliteFunction(). */
    public const SQLITE_FUNCTION = 'arabic_normalize';

    /**
     * Register arabic_normalize(text) on a SQLite connection so sqlExpression(..., 'sqlite') works.
     */
    public function registerSqliteFunction(\PDO $pdo): void
    {
        $callback = function (mixed $value): ?string {
            return $value === null ? null : $this->normalize((string) $value);
        };

        if (method_exists($pdo, 'createFunction')) {
            // PHP 8.4+: Pdo\Sqlite
            $pdo->createFunction(self::SQLITE_FUNCTION, $callback, 1, \PDO::SQLITE_DETERMINISTIC);

            return;
        }

        $pdo->sqliteCreateFunction(self::SQLITE_FUNCTION, $callback, 1, \PDO::SQLITE_DETERMINISTIC);
    }

    /**
     * @return array<string, string>
     */
    private function sqlReplacements(): array
    {
        $replacements = [];

        if ($this->options->stripDiacritics) {
            foreach (self::SQL_DIACRITICS as $mark) {
                $replacements[$mark] = '';
            }
        }

        return $replacements + $this->characterMap();
    }

    /**
     * @return array<string, string>
     */
    private function characterMap(): array
    {
        $map = [];

        if ($this->options->stripTatweel) {
            $map[self::TATWEEL] = '';
        }

        if ($this->options->unifyAlef) {
            $map += self::ALEF;
        }

        if ($this->options->unifyYaa) {
            $map += self::YAA;
        }

        if ($this->options->unifyKaf) {
            $map += self::KAF;
        }

        if ($this->options->unifyTaaMarbuta) {
            $map += self::TAA_MARBUTA;
        }

        if ($this->options->unifyHamzaCarriers) {
            $map += self::HAMZA_CARRIERS;
        }

        if ($this->options->normalizeDigits) {
            $map += self::DIGITS;
        }

        return $map;
    }
}
