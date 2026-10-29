<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use IslamGaber\ArabicSearch\ArabicNormalizer;
use IslamGaber\ArabicSearch\LikePattern;

final class QueryMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['أحمد إبراهيم', 'إحمد', 'أَحْمَد علي', 'محمود', 'مؤسسة الأمل', '50% off_now!', '500 off now'] as $name) {
            // Insert without the model, so name_search stays empty and only the SQL expression is tested.
            DB::table('customers')->insert(['name' => $name, 'city' => 'الطائف']);
        }

        DB::table('customers')->insert(['name' => 'سارة', 'city' => 'جدة']);
    }

    public function test_where_arabic_like_matches_spelling_variants(): void
    {
        $names = DB::table('customers')->whereArabicLike('name', 'احمد')->orderBy('id')->pluck('name')->all();

        self::assertSame(['أحمد إبراهيم', 'إحمد', 'أَحْمَد علي'], $names);
    }

    public function test_it_works_on_eloquent_builders(): void
    {
        $names = Customer::query()->whereArabicLike('name', 'الامل')->pluck('name')->all();

        self::assertSame(['مؤسسة الأمل'], $names);
    }

    public function test_match_modes(): void
    {
        $startsWith = DB::table('customers')->whereArabicLike('name', 'احمد', LikePattern::STARTS_WITH)->count();
        $exact = DB::table('customers')->whereArabicLike('name', 'إحمد', LikePattern::EXACT)->count();

        self::assertSame(3, $startsWith);
        self::assertSame(1, $exact);
    }

    public function test_like_wildcards_in_the_term_are_literal(): void
    {
        self::assertSame(['50% off_now!'], DB::table('customers')->whereArabicLike('name', '50%')->pluck('name')->all());
        self::assertSame(['50% off_now!'], DB::table('customers')->whereArabicLike('name', 'OFF_NOW')->pluck('name')->all());
    }

    public function test_empty_term_does_not_filter(): void
    {
        self::assertSame(8, DB::table('customers')->whereArabicLike('name', '  ')->count());
        self::assertSame(8, DB::table('customers')->whereArabicSearch(['name', 'city'], null)->count());
    }

    public function test_where_arabic_search_across_columns_is_grouped(): void
    {
        $names = DB::table('customers')
            ->where('city', 'الطائف')
            ->whereArabicSearch(['name', 'city'], 'جده')
            ->pluck('name')
            ->all();

        // "جده" matches city "جدة", but that row is excluded by the first where(): the OR is grouped.
        self::assertSame([], $names);

        self::assertSame(['سارة'], DB::table('customers')->whereArabicSearch(['name', 'city'], 'جده')->pluck('name')->all());
    }

    public function test_or_where_arabic_like(): void
    {
        $count = DB::table('customers')
            ->whereArabicLike('name', 'محمود')
            ->orWhereArabicLike('name', 'ساره')
            ->count();

        self::assertSame(2, $count);
    }

    public function test_str_macro_and_container_binding(): void
    {
        self::assertSame('مدرسه الامل', Str::arabicNormalize('مدرسةُ الأمل'));
        self::assertSame(app(ArabicNormalizer::class), app('arabic-search'));
    }

    public function test_config_options_are_applied(): void
    {
        config()->set('arabic-search.options.unify_taa_marbuta', false);
        app()->forgetInstance(ArabicNormalizer::class);

        self::assertSame('مدرسة', app(ArabicNormalizer::class)->normalize('مدرسة'));
    }
}
