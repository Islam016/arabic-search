# Arabic Search for PHP & Laravel

[![tests](https://github.com/Islam016/arabic-search/actions/workflows/tests.yml/badge.svg)](https://github.com/Islam016/arabic-search/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-12%2B-FF2D20)
![License](https://img.shields.io/badge/license-MIT-green)

Search Arabic text the way users actually type it.

A user searches for **احمد**, but the database has **أحمد**, **إحمد** or **أَحْمَد**. They type **مدرسه**, but the record says **مدرسة**. They paste an invoice number in Arabic-Indic digits, **١٢٣٤**, but you stored **1234**. A plain `LIKE` misses all of these, and that is a real problem in almost every Arabic app: HR systems, e-commerce, CRMs, government portals.

This package normalizes Arabic text **in PHP and inside SQL**, so these spelling variants match:

| Rule | Example |
|---|---|
| Alef forms `أ إ آ ٱ` → `ا` | أحمد · إحمد → **احمد** |
| Harakat & dagger alef removed | أَحْمَدُ · هٰذا → **احمد** · **هذا** |
| Tatweel removed | محـــمد → **محمد** |
| `ى` and Persian `ی` → `ي` | مصطفى → **مصطفي** |
| `ة` → `ه` | مدرسة → **مدرسه** |
| Persian `ک` → `ك` | کتاب → **كتاب** |
| Arabic-Indic & Persian digits → `0-9` | ١٢٣٤ · ۲۰۲۶ → **1234** · **2026** |
| Latin lowercased, whitespace collapsed | `Laravel  مطور` → **laravel مطور** |
| *(optional)* `ؤ ئ` → `ء` | مسؤول · مسئول → **مسءول** |

Works with **MySQL / MariaDB, PostgreSQL and SQLite**. Every rule is tested against all three on each push.

## Installation

```bash
composer require islam016/arabic-search
```

Laravel discovers the service provider automatically. To change the rules, publish the config:

```bash
php artisan vendor:publish --tag=arabic-search-config
```

## Plain PHP (any framework)

```php
use IslamGaber\ArabicSearch\ArabicNormalizer;

$arabic = ArabicNormalizer::make();

$arabic->normalize('مؤسسةُ أحمد للتجارة');   // "مؤسسه احمد للتجاره"
$arabic->equals('أحمد إبراهيم', 'احمد ابراهيم'); // true
$arabic->contains('مدرسة الأمل الأهلية', 'الامل'); // true
$arabic->words('شركة واجز، للمقاولات - ٢٠٢٦');   // ["شركه", "واجز", "للمقاولات", "2026"]
```

Turn rules on or off:

```php
use IslamGaber\ArabicSearch\NormalizerOptions;

$arabic = ArabicNormalizer::make(new NormalizerOptions(
    unifyTaaMarbuta: false,
    unifyHamzaCarriers: true,
));
```

## Laravel

### 1. Quick search with no schema changes

Query builder macros normalize **both** the column (in SQL) and the search term (in PHP):

```php
// Finds أحمد, إحمد, أَحْمَد علي ...
User::whereArabicLike('name', $request->q)->get();

// Search several columns, grouped in parentheses so it never breaks your other conditions
Product::where('active', true)
    ->whereArabicSearch(['name', 'description'], $request->q)
    ->paginate();

// Match modes: contains (default), starts_with, ends_with, exact
use IslamGaber\ArabicSearch\LikePattern;

City::whereArabicLike('name', 'جده', LikePattern::EXACT)->first(); // finds "جدة"

DB::table('customers')->orWhereArabicLike('company', 'الامل');
```

`%` and `_` typed by the user are treated as literal characters, not wildcards.

### 2. Fast search on large tables (indexed)

On big tables, normalizing the column for every row is slow. Store a normalized copy instead, and the database can use an index.

**Migration:**

```php
Schema::table('customers', function (Blueprint $table) {
    $table->arabicSearchColumn('name');     // adds an indexed, nullable "name_search" column
    $table->arabicSearchColumn('company');  // "company_search"
});
```

**Model:**

```php
use IslamGaber\ArabicSearch\Laravel\HasArabicSearch;

class Customer extends Model
{
    use HasArabicSearch;

    // source column => normalized column
    protected array $arabicSearchable = [
        'name' => 'name_search',
        'company' => 'company_search',
    ];
}
```

The normalized columns are filled automatically whenever a model is saved. Search them with the scope:

```php
Customer::arabicSearch('احمد')->get();                         // all configured columns
Customer::arabicSearch('احمد', ['name'])->get();               // only some columns
Customer::arabicSearch('احمد', null, LikePattern::STARTS_WITH) // can use the index
    ->limit(10)
    ->get();
```

Columns that are not in `$arabicSearchable` fall back to `whereArabicLike()` automatically.

**Existing rows:** fill the new columns once (it also rebuilds them if you change the rules):

```bash
php artisan arabic-search:backfill "App\Models\Customer"
```

### Helpers

```php
Str::arabicNormalize('مدرسةُ الأمل');            // "مدرسه الامل"
app(ArabicNormalizer::class)->normalize($text);  // configured instance
```

## How it works

| Database | SQL used for the column |
|---|---|
| MySQL / MariaDB / SQL Server | `LOWER(REPLACE(REPLACE(...)))` |
| PostgreSQL | `LOWER(TRANSLATE(column, 'أإآ...', 'ااا...'))`, a single call |
| SQLite | `arabic_normalize(column)`, a PHP function registered on the connection |

You can use the same expression outside Laravel:

```php
$sql = $arabic->sqlExpression('`name`', 'mysql');
$stmt = $pdo->prepare("SELECT * FROM users WHERE {$sql} LIKE ? ESCAPE '!'");
$stmt->execute([LikePattern::build($arabic->normalize($term))]);
```

In SQL, whitespace collapsing and the rare Quranic annotation marks are not applied (SQLite applies everything, because it runs the PHP code). For exact parity and best performance, use the stored-column approach.

## Testing

```bash
composer test
```

The CI runs the suite on PHP 8.2, 8.3 and 8.4 with SQLite, and on real MySQL 8.4 and PostgreSQL 17 servers.

## License

MIT © [Islam Gaber](https://github.com/Islam016)
