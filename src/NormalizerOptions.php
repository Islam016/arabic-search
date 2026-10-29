<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch;

/**
 * Which normalization rules to apply. Every rule can be switched off.
 */
final class NormalizerOptions
{
    public function __construct(
        /** Remove harakat (fatha, damma, kasra, shadda, sukun, tanween, ...). */
        public readonly bool $stripDiacritics = true,
        /** Remove tatweel / kashida: "محـــمد" → "محمد". */
        public readonly bool $stripTatweel = true,
        /** أ إ آ ٱ → ا */
        public readonly bool $unifyAlef = true,
        /** ى (and Persian ی) → ي */
        public readonly bool $unifyYaa = true,
        /** Persian ک → ك */
        public readonly bool $unifyKaf = true,
        /** ة → ه, so "مدرسة" matches "مدرسه". */
        public readonly bool $unifyTaaMarbuta = true,
        /** ؤ ئ → ء, so "مسؤول" matches "مسئول". Off by default: it changes how words read. */
        public readonly bool $unifyHamzaCarriers = false,
        /** ٠١٢٣٤٥٦٧٨٩ and ۰۱۲۳۴۵۶۷۸۹ → 0123456789 */
        public readonly bool $normalizeDigits = true,
        /** Lowercase Latin letters, so mixed Arabic/English text matches case-insensitively. */
        public readonly bool $lowercase = true,
        /** Trim and collapse runs of whitespace into one space. */
        public readonly bool $collapseWhitespace = true,
    ) {
    }

    /**
     * @param array<string, bool> $options e.g. ['unify_hamza_carriers' => true]
     */
    public static function fromArray(array $options): self
    {
        $flag = static fn (string $key, bool $default): bool => (bool) ($options[$key] ?? $default);

        return new self(
            stripDiacritics: $flag('strip_diacritics', true),
            stripTatweel: $flag('strip_tatweel', true),
            unifyAlef: $flag('unify_alef', true),
            unifyYaa: $flag('unify_yaa', true),
            unifyKaf: $flag('unify_kaf', true),
            unifyTaaMarbuta: $flag('unify_taa_marbuta', true),
            unifyHamzaCarriers: $flag('unify_hamza_carriers', false),
            normalizeDigits: $flag('normalize_digits', true),
            lowercase: $flag('lowercase', true),
            collapseWhitespace: $flag('collapse_whitespace', true),
        );
    }
}
