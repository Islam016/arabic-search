# Changelog

## 1.0.0 - 2026-10-29

- `ArabicNormalizer`: alef, yaa, kaf and taa marbuta unification, harakat and tatweel removal, Arabic-Indic and Persian digits, optional hamza carriers.
- SQL expressions for MySQL/MariaDB (nested `REPLACE`), PostgreSQL (`TRANSLATE`) and SQLite (registered PHP function).
- Laravel: `whereArabicLike`, `orWhereArabicLike` and `whereArabicSearch` query macros, `HasArabicSearch` trait with stored normalized columns, `arabicSearchColumn()` schema macro, `arabic-search:backfill` command and `Str::arabicNormalize()`.
