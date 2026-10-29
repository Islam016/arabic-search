<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests\Laravel;

use Illuminate\Support\Facades\DB;
use IslamGaber\ArabicSearch\LikePattern;

final class HasArabicSearchTest extends TestCase
{
    public function test_normalized_column_is_filled_on_create_and_update(): void
    {
        $customer = Customer::create(['name' => 'مؤسسةُ أحمد للتجارة']);

        self::assertSame('مؤسسه احمد للتجاره', $customer->name_search);

        $customer->update(['name' => 'شركة إسلام']);

        self::assertSame('شركه اسلام', $customer->fresh()->name_search);
    }

    public function test_scope_searches_the_stored_column(): void
    {
        Customer::create(['name' => 'أحمد إبراهيم']);
        Customer::create(['name' => 'إحمد']);
        Customer::create(['name' => 'محمود']);

        self::assertSame(['أحمد إبراهيم', 'إحمد'], Customer::arabicSearch('احمد')->orderBy('id')->pluck('name')->all());
        self::assertSame(['إحمد'], Customer::arabicSearch('احمد', null, LikePattern::EXACT)->pluck('name')->all());
        self::assertSame(3, Customer::arabicSearch('')->count());
    }

    public function test_scope_falls_back_to_sql_for_columns_without_stored_copy(): void
    {
        Customer::create(['name' => 'سارة', 'city' => 'جدة']);
        Customer::create(['name' => 'منى', 'city' => 'الطائف']);

        self::assertSame(['سارة'], Customer::arabicSearch('جده', ['city'])->pluck('name')->all());
    }

    public function test_scope_is_grouped_with_other_conditions(): void
    {
        Customer::create(['name' => 'أحمد', 'city' => 'جدة']);
        Customer::create(['name' => 'أحمد', 'city' => 'الطائف']);

        self::assertSame(1, Customer::where('city', 'الطائف')->arabicSearch('احمد', ['name', 'city'])->count());
    }

    public function test_backfill_command_fills_existing_rows(): void
    {
        DB::table('customers')->insert(['name' => 'أحمد']);
        DB::table('customers')->insert(['name' => 'مدرسة الأمل']);

        $this->artisan('arabic-search:backfill', ['model' => Customer::class])
            ->expectsOutputToContain('Updated 2 row(s)')
            ->assertSuccessful();

        self::assertSame(['احمد', 'مدرسه الامل'], DB::table('customers')->orderBy('id')->pluck('name_search')->all());
    }

    public function test_backfill_command_rejects_models_without_the_trait(): void
    {
        $this->artisan('arabic-search:backfill', ['model' => \stdClass::class])->assertFailed();
    }
}
