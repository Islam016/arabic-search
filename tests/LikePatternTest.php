<?php

declare(strict_types=1);

namespace IslamGaber\ArabicSearch\Tests;

use IslamGaber\ArabicSearch\LikePattern;
use PHPUnit\Framework\TestCase;

final class LikePatternTest extends TestCase
{
    public function test_wildcards_in_user_input_are_escaped(): void
    {
        self::assertSame('50!% off!_now!!', LikePattern::escape('50% off_now!'));
    }

    public function test_modes(): void
    {
        self::assertSame('%احمد%', LikePattern::build('احمد'));
        self::assertSame('احمد%', LikePattern::build('احمد', LikePattern::STARTS_WITH));
        self::assertSame('%احمد', LikePattern::build('احمد', LikePattern::ENDS_WITH));
        self::assertSame('احمد', LikePattern::build('احمد', LikePattern::EXACT));
    }

    public function test_unknown_mode_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LikePattern::build('x', 'fuzzy');
    }
}
