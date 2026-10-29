<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Laravel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use IslamGaber\ArabicSearch\ArabicNormalizer;
use IslamGaber\ArabicSearch\LikePattern;

/**
 * Keeps a normalized copy of Arabic columns and searches it.
 *
 * class Customer extends Model
 * {
 *     use HasArabicSearch;
 *
 *     // source column => column that stores the normalized copy
 *     protected array $arabicSearchable = [
 *         'name' => 'name_search',
 *         'company' => 'company_search',
 *     ];
 * }
 *
 * Customer::arabicSearch('مؤسسة احمد')->get();
 *
 * Searching the stored column avoids per-row REPLACE() calls, and with
 * the "starts_with" mode the database can use the column's index.
 *
 * @mixin Model
 */
trait HasArabicSearch
{
    public static function bootHasArabicSearch(): void
    {
        static::saving(static function (Model $model): void {
            /** @var Model&HasArabicSearch $model */
            $model->fillArabicSearchColumns();
        });
    }

    /**
     * @return array<string, string> source column => normalized column
     */
    public function getArabicSearchColumns(): array
    {
        return property_exists($this, 'arabicSearchable') ? (array) $this->arabicSearchable : [];
    }

    /**
     * Fill the normalized columns. Only changed sources are recomputed unless $force is true.
     */
    public function fillArabicSearchColumns(bool $force = false): static
    {
        $normalizer = app(ArabicNormalizer::class);

        foreach ($this->getArabicSearchColumns() as $source => $target) {
            $needsUpdate = $force
                || ! $this->exists
                || $this->isDirty($source)
                || $this->getAttribute($target) === null;

            if (! $needsUpdate) {
                continue;
            }

            $value = $this->getAttribute($source);

            $this->setAttribute($target, $value === null ? null : $normalizer->normalize((string) $value));
        }

        return $this;
    }

    /**
     * @param list<string>|null $columns source columns to search (defaults to all configured ones)
     */
    public function scopeArabicSearch(
        Builder $query,
        ?string $term,
        ?array $columns = null,
        string $mode = LikePattern::CONTAINS,
    ): Builder {
        $normalizer = app(ArabicNormalizer::class);
        $needle = $normalizer->normalize($term);

        if ($needle === '') {
            return $query;
        }

        $map = $this->getArabicSearchColumns();
        $sources = $columns ?? array_keys($map);

        if ($sources === []) {
            return $query;
        }

        $pattern = LikePattern::build($needle, $mode);

        return $query->where(function (Builder $group) use ($sources, $map, $pattern, $term, $mode): void {
            foreach ($sources as $source) {
                $target = $map[$source] ?? null;

                if ($target === null) {
                    // Not stored: fall back to normalizing the column inside SQL.
                    $group->orWhereArabicLike($group->qualifyColumn($source), $term, $mode);

                    continue;
                }

                $wrapped = $group->getQuery()->getGrammar()->wrap($group->qualifyColumn($target));

                $group->orWhereRaw($wrapped.' LIKE ?'.LikePattern::escapeClause(), [$pattern]);
            }
        });
    }
}
