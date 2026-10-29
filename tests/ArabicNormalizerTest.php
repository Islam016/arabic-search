<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests;

use IslamGaber\ArabicSearch\ArabicNormalizer;
use IslamGaber\ArabicSearch\NormalizerOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArabicNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function variants(): iterable
    {
        yield 'hamza above alef' => ['أحمد', 'احمد'];
        yield 'hamza below alef' => ['إسلام', 'اسلام'];
        yield 'madda alef' => ['آمنة', 'امنه'];
        yield 'alef wasla' => ['ٱلرحمن', 'الرحمن'];
        yield 'diacritics' => ['أَحْمَدُ', 'احمد'];
        yield 'shadda and tanween' => ['محمّدٌ', 'محمد'];
        yield 'dagger alef' => ['هٰذا', 'هذا'];
        yield 'tatweel' => ['محـــمد', 'محمد'];
        yield 'alef maqsura' => ['مصطفى', 'مصطفي'];
        yield 'persian yeh' => ['علی', 'علي'];
        yield 'persian kaf' => ['کتاب', 'كتاب'];
        yield 'taa marbuta' => ['مدرسة', 'مدرسه'];
        yield 'arabic-indic digits' => ['فاتورة ١٢٣٤', 'فاتوره 1234'];
        yield 'persian digits' => ['۲۰۲۶', '2026'];
        yield 'latin lowercase' => ['Laravel مطور', 'laravel مطور'];
        yield 'whitespace' => ["  شركة   واجز \n للمقاولات ", 'شركه واجز للمقاولات'];
        yield 'already normal' => ['احمد', 'احمد'];
    }

    #[DataProvider('variants')]
    public function test_it_normalizes_spelling_variants(string $input, string $expected): void
    {
        self::assertSame($expected, ArabicNormalizer::make()->normalize($input));
    }

    public function test_empty_and_null_become_empty_string(): void
    {
        $normalizer = ArabicNormalizer::make();

        self::assertSame('', $normalizer->normalize(null));
        self::assertSame('', $normalizer->normalize(''));
        self::assertSame('', $normalizer->normalize("  \t "));
    }

    public function test_hamza_carriers_are_kept_by_default_and_unified_on_request(): void
    {
        self::assertNotSame(
            ArabicNormalizer::make()->normalize('مسؤول'),
            ArabicNormalizer::make()->normalize('مسئول'),
        );

        $normalizer = ArabicNormalizer::make(new NormalizerOptions(unifyHamzaCarriers: true));

        self::assertSame($normalizer->normalize('مسؤول'), $normalizer->normalize('مسئول'));
    }

    public function test_rules_can_be_switched_off(): void
    {
        $normalizer = ArabicNormalizer::make(new NormalizerOptions(unifyTaaMarbuta: false, normalizeDigits: false));

        self::assertSame('مدرسة ١', $normalizer->normalize('مدرسة ١'));
    }

    public function test_options_from_config_array(): void
    {
        $options = NormalizerOptions::fromArray(['unify_alef' => false, 'unify_hamza_carriers' => true]);

        self::assertFalse($options->unifyAlef);
        self::assertTrue($options->unifyHamzaCarriers);
        self::assertTrue($options->stripDiacritics);
    }

    public function test_equals_and_contains(): void
    {
        $normalizer = ArabicNormalizer::make();

        self::assertTrue($normalizer->equals('أحمد إبراهيم', 'احمد ابراهيم'));
        self::assertTrue($normalizer->contains('مدرسة الأمل الأهلية', 'الامل'));
        self::assertFalse($normalizer->contains('مدرسة الأمل', ''));
        self::assertFalse($normalizer->contains('مدرسة الأمل', 'النور'));
    }

    public function test_words(): void
    {
        self::assertSame(
            ['شركه', 'واجز', 'للمقاولات', '2026'],
            ArabicNormalizer::make()->words('شركة واجز، للمقاولات - ٢٠٢٦'),
        );
        self::assertSame([], ArabicNormalizer::make()->words(null));
    }

    public function test_sql_expression_wraps_the_column(): void
    {
        $sql = ArabicNormalizer::make()->sqlExpression('"name"');

        self::assertStringStartsWith('LOWER(', $sql);
        self::assertStringContainsString('"name"', $sql);
        self::assertStringContainsString("'أ', 'ا'", $sql);
        self::assertStringNotContainsString("'ؤ'", $sql);
    }
}
