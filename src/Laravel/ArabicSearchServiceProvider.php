<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Laravel;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use IslamGaber\ArabicSearch\ArabicNormalizer;
use IslamGaber\ArabicSearch\LikePattern;
use IslamGaber\ArabicSearch\Laravel\Commands\BackfillCommand;
use IslamGaber\ArabicSearch\NormalizerOptions;

class ArabicSearchServiceProvider extends ServiceProvider
{
    /** @var \WeakMap<\PDO, true>|null */
    private static ?\WeakMap $sqliteConnections = null;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/arabic-search.php', 'arabic-search');

        $this->app->singleton(ArabicNormalizer::class, static function ($app): ArabicNormalizer {
            return new ArabicNormalizer(
                NormalizerOptions::fromArray((array) $app['config']->get('arabic-search.options', [])),
            );
        });

        $this->app->alias(ArabicNormalizer::class, 'arabic-search');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/arabic-search.php' => config_path('arabic-search.php'),
            ], 'arabic-search-config');

            $this->commands([BackfillCommand::class]);
        }

        $this->registerQueryMacros();
        $this->registerSchemaMacros();

        Str::macro('arabicNormalize', static fn (?string $text): string => app(ArabicNormalizer::class)->normalize($text));
    }

    private function registerQueryMacros(): void
    {
        /*
         * where(normalized column) LIKE (normalized term)
         * Works on query builders and Eloquent builders:
         *   User::whereArabicLike('name', 'احمد')->get();  // finds أحمد, إحمد, أَحْمَد ...
         */
        QueryBuilder::macro('whereArabicLike', function (
            string $column,
            ?string $term,
            string $mode = LikePattern::CONTAINS,
            string $boolean = 'and',
        ) {
            /** @var QueryBuilder $this */
            $normalizer = app(ArabicNormalizer::class);
            $needle = $normalizer->normalize($term);

            if ($needle === '') {
                return $this;
            }

            $driver = $this->getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                ArabicSearchServiceProvider::ensureSqliteFunction($this->getConnection()->getPdo(), $normalizer);
            }

            $sql = $normalizer->sqlExpression($this->getGrammar()->wrap($column), $driver)
                .' LIKE ?'.LikePattern::escapeClause();

            return $this->whereRaw($sql, [LikePattern::build($needle, $mode)], $boolean);
        });

        QueryBuilder::macro('orWhereArabicLike', function (
            string $column,
            ?string $term,
            string $mode = LikePattern::CONTAINS,
        ) {
            /** @var QueryBuilder $this */
            return $this->whereArabicLike($column, $term, $mode, 'or');
        });

        /*
         * Match the term in any of the given columns:
         *   Product::whereArabicSearch(['name', 'description'], $request->q)->paginate();
         */
        QueryBuilder::macro('whereArabicSearch', function (
            array $columns,
            ?string $term,
            string $mode = LikePattern::CONTAINS,
            string $boolean = 'and',
        ) {
            /** @var QueryBuilder $this */
            if (app(ArabicNormalizer::class)->normalize($term) === '' || $columns === []) {
                return $this;
            }

            return $this->where(static function (QueryBuilder $query) use ($columns, $term, $mode): void {
                foreach ($columns as $column) {
                    $query->orWhereArabicLike($column, $term, $mode);
                }
            }, null, null, $boolean);
        });
    }

    /**
     * Register arabic_normalize() once per SQLite PDO connection.
     */
    public static function ensureSqliteFunction(\PDO $pdo, ArabicNormalizer $normalizer): void
    {
        self::$sqliteConnections ??= new \WeakMap();

        if (isset(self::$sqliteConnections[$pdo])) {
            return;
        }

        $normalizer->registerSqliteFunction($pdo);
        self::$sqliteConnections[$pdo] = true;
    }

    private function registerSchemaMacros(): void
    {
        /*
         * Adds an indexed, nullable column that stores the normalized copy of another column:
         *   $table->arabicSearchColumn('name');            // name_search
         *   $table->arabicSearchColumn('title', 'title_n'); // custom name
         */
        Blueprint::macro('arabicSearchColumn', function (string $column, ?string $target = null, int $length = 255) {
            /** @var Blueprint $this */
            return $this->string($target ?? $column.'_search', $length)->nullable()->index();
        });
    }
}
