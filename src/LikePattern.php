<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch;

/**
 * Builds LIKE patterns that treat user input literally.
 *
 * Uses "!" as the escape character because it behaves the same in MySQL, PostgreSQL,
 * SQLite and SQL Server (a backslash does not).
 */
final class LikePattern
{
    public const ESCAPE_CHAR = '!';

    public const CONTAINS = 'contains';
    public const STARTS_WITH = 'starts_with';
    public const ENDS_WITH = 'ends_with';
    public const EXACT = 'exact';

    public static function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE_CHAR, '%', '_'],
            [self::ESCAPE_CHAR.self::ESCAPE_CHAR, self::ESCAPE_CHAR.'%', self::ESCAPE_CHAR.'_'],
            $value,
        );
    }

    public static function build(string $value, string $mode = self::CONTAINS): string
    {
        $escaped = self::escape($value);

        return match ($mode) {
            self::CONTAINS => '%'.$escaped.'%',
            self::STARTS_WITH => $escaped.'%',
            self::ENDS_WITH => '%'.$escaped,
            self::EXACT => $escaped,
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown match mode "%s". Use contains, starts_with, ends_with or exact.',
                $mode,
            )),
        };
    }

    /** The " ESCAPE '!'" clause to append after "LIKE ?". */
    public static function escapeClause(): string
    {
        return " ESCAPE '".self::ESCAPE_CHAR."'";
    }
}
