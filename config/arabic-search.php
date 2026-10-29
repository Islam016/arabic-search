<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Normalization rules
    |--------------------------------------------------------------------------
    |
    | Every rule applies both to the PHP normalizer and to the SQL expression
    | used by whereArabicLike(). If you change these after filling the
    | normalized columns, run: php artisan arabic-search:backfill "App\Models\YourModel"
    |
    */

    'options' => [
        'strip_diacritics' => true,       // أَحْمَد → احمد
        'strip_tatweel' => true,          // محـــمد → محمد
        'unify_alef' => true,             // أ إ آ ٱ → ا
        'unify_yaa' => true,              // ى ی → ي
        'unify_kaf' => true,              // ک → ك
        'unify_taa_marbuta' => true,      // ة → ه
        'unify_hamza_carriers' => false,  // ؤ ئ → ء
        'normalize_digits' => true,       // ١٢٣ ۱۲۳ → 123
        'lowercase' => true,              // Latin letters
        'collapse_whitespace' => true,
    ],

];
