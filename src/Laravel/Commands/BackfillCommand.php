<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use IslamGaber\ArabicSearch\Laravel\HasArabicSearch;

class BackfillCommand extends Command
{
    protected $signature = 'arabic-search:backfill
        {model : Model class that uses HasArabicSearch, e.g. "App\Models\Customer"}
        {--chunk=500 : Rows per batch}';

    protected $description = 'Fill (or rebuild) the normalized Arabic search columns for existing rows';

    public function handle(): int
    {
        $class = (string) $this->argument('model');

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            $this->error("{$class} is not an Eloquent model.");

            return self::FAILURE;
        }

        if (! in_array(HasArabicSearch::class, class_uses_recursive($class), true)) {
            $this->error("{$class} does not use the HasArabicSearch trait.");

            return self::FAILURE;
        }

        /** @var Model&HasArabicSearch $instance */
        $instance = new $class();

        if ($instance->getArabicSearchColumns() === []) {
            $this->error("{$class} has no \$arabicSearchable columns.");

            return self::FAILURE;
        }

        $updated = 0;
        $chunk = max(1, (int) $this->option('chunk'));

        $class::query()->chunkById($chunk, function (Collection $models) use (&$updated): void {
            foreach ($models as $model) {
                /** @var Model&HasArabicSearch $model */
                $model->fillArabicSearchColumns(force: true);

                if ($model->isDirty()) {
                    $model->timestamps = false;
                    $model->saveQuietly();
                    $updated++;
                }
            }
        });

        $this->info("Updated {$updated} row(s) of {$class}.");

        return self::SUCCESS;
    }
}
