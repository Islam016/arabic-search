<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests\Laravel;

use Illuminate\Database\Eloquent\Model;
use IslamGaber\ArabicSearch\Laravel\HasArabicSearch;

class Customer extends Model
{
    use HasArabicSearch;

    protected $guarded = [];

    /** @var array<string, string> */
    protected array $arabicSearchable = [
        'name' => 'name_search',
    ];
}
