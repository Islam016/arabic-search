<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests;

use IslamGaber\ArabicSearch\ArabicNormalizer;
use IslamGaber\ArabicSearch\LikePattern;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the generated SQL on real databases and checks it matches the PHP normalizer.
 *
 * SQLite always runs. MySQL and PostgreSQL run when these variables are set (as in CI):
 *   ARABIC_SEARCH_MYSQL_DSN, ARABIC_SEARCH_MYSQL_USER, ARABIC_SEARCH_MYSQL_PASSWORD
 *   ARABIC_SEARCH_PGSQL_DSN, ARABIC_SEARCH_PGSQL_USER, ARABIC_SEARCH_PGSQL_PASSWORD
 */
final class SqlExpressionTest extends TestCase
{
    private const SAMPLES = [
        'أحمد', 'إسلام', 'آمنة', 'أَحْمَدُ', 'محمّدٌ', 'هٰذا', 'محـــمد', 'مصطفى',
        'علی', 'کتاب', 'مدرسة', 'فاتورة ١٢٣٤', '۲۰۲۶', 'Laravel مطور', '50% off_now!',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function drivers(): iterable
    {
        yield 'sqlite' => ['sqlite'];
        yield 'mysql' => ['mysql'];
        yield 'pgsql' => ['pgsql'];
    }

    #[DataProvider('drivers')]
    public function test_sql_normalization_matches_php(string $driver): void
    {
        $pdo = $this->connect($driver);
        $normalizer = ArabicNormalizer::make();

        $this->createTable($pdo, $driver);

        $insert = $pdo->prepare('INSERT INTO arabic_search_samples (name) VALUES (?)');

        foreach (self::SAMPLES as $sample) {
            $insert->execute([$sample]);
        }

        $rows = $pdo->query(
            'SELECT name, '.$normalizer->sqlExpression('name', $driver).' AS normalized FROM arabic_search_samples ORDER BY id',
        )->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(count(self::SAMPLES), $rows);

        foreach ($rows as $row) {
            self::assertSame($normalizer->normalize($row['name']), $row['normalized'], "Mismatch for {$row['name']}");
        }
    }

    #[DataProvider('drivers')]
    public function test_like_search_finds_spelling_variants(string $driver): void
    {
        $pdo = $this->connect($driver);
        $normalizer = ArabicNormalizer::make();

        $this->createTable($pdo, $driver);

        $insert = $pdo->prepare('INSERT INTO arabic_search_samples (name) VALUES (?)');

        foreach (['أحمد إبراهيم', 'إحمد', 'أَحْمَد علي', 'محمود', '50% off_now!', '500 off now'] as $name) {
            $insert->execute([$name]);
        }

        $search = function (string $term) use ($pdo, $normalizer, $driver): array {
            $statement = $pdo->prepare(
                'SELECT name FROM arabic_search_samples WHERE '.$normalizer->sqlExpression('name', $driver)
                .' LIKE ?'.LikePattern::escapeClause().' ORDER BY id',
            );
            $statement->execute([LikePattern::build($normalizer->normalize($term))]);

            return $statement->fetchAll(PDO::FETCH_COLUMN);
        };

        self::assertSame(['أحمد إبراهيم', 'إحمد', 'أَحْمَد علي'], $search('احمد'));
        self::assertSame(['أحمد إبراهيم'], $search('ابراهيم'));
        self::assertSame(['50% off_now!'], $search('50%'));
        self::assertSame(['50% off_now!'], $search('OFF_NOW'));
    }

    private function connect(string $driver): PDO
    {
        if ($driver === 'sqlite') {
            if (! extension_loaded('pdo_sqlite')) {
                self::markTestSkipped('pdo_sqlite is not installed.');
            }

            $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            ArabicNormalizer::make()->registerSqliteFunction($pdo);

            return $pdo;
        }

        $prefix = 'ARABIC_SEARCH_'.strtoupper($driver).'_';
        $dsn = getenv($prefix.'DSN');

        if (! $dsn) {
            self::markTestSkipped("Set {$prefix}DSN to run the {$driver} tests.");
        }

        $pdo = new PDO($dsn, getenv($prefix.'USER') ?: null, getenv($prefix.'PASSWORD') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        if ($driver === 'mysql') {
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        }

        return $pdo;
    }

    private function createTable(PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE IF EXISTS arabic_search_samples');

        $pdo->exec(match ($driver) {
            'mysql' => 'CREATE TABLE arabic_search_samples (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL)'
                .' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
            'pgsql' => 'CREATE TABLE arabic_search_samples (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
            default => 'CREATE TABLE arabic_search_samples (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)',
        });
    }
}
