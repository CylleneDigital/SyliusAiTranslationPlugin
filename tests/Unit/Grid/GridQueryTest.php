<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Grid;

use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridQuery;
use PHPUnit\Framework\TestCase;

final class GridQueryTest extends TestCase
{
    public function testOnlyTheNamedEntriesOfTheCriteriaAreKept(): void
    {
        self::assertSame([], GridQuery::criteria([]));
        self::assertSame([], GridQuery::criteria(['criteria' => 'x']));
        self::assertSame(
            ['locale' => 'fr_FR', 'search' => ['value' => 'cart']],
            GridQuery::criteria(['criteria' => ['x', 'locale' => 'fr_FR', 'search' => ['value' => 'cart']]]),
        );
        // A select left on its empty choice asks for nothing.
        self::assertSame(['failed' => '0'], GridQuery::criteria(['criteria' => ['catalogue' => '', 'failed' => '0']]));
    }

    public function testAFormSentWithEveryFieldEmptyDoesNotFilter(): void
    {
        self::assertFalse(GridQuery::isFiltering([]));
        self::assertFalse(GridQuery::isFiltering(['criteria' => ['catalogue' => '', 'locale' => '', 'failed' => '', 'search' => ['value' => '']]]));
        self::assertTrue(GridQuery::isFiltering(['criteria' => ['catalogue' => '', 'search' => ['value' => 'cart']]]));
        self::assertTrue(GridQuery::isFiltering(['criteria' => ['failed' => '0']]));
    }

    public function testAListNarrowedByOneFilterAloneIsToldApart(): void
    {
        self::assertTrue(GridQuery::isFilteringOnlyBy(['criteria' => ['locale' => 'fr_FR']], 'locale'));
        self::assertTrue(GridQuery::isFilteringOnlyBy(['criteria' => ['locale' => 'fr_FR', 'catalogue' => '', 'search' => ['value' => '']]], 'locale'));
        self::assertFalse(GridQuery::isFilteringOnlyBy(['criteria' => ['locale' => 'fr_FR', 'search' => ['value' => 'cart']]], 'locale'));
        self::assertFalse(GridQuery::isFilteringOnlyBy(['criteria' => ['locale' => '', 'failed' => '0']], 'locale'));
        self::assertFalse(GridQuery::isFilteringOnlyBy([], 'locale'));
    }

    public function testOnlyTheNamedEntriesOfTheSortingHoldingAStringAreKept(): void
    {
        self::assertSame([], GridQuery::sorting([]));
        self::assertSame([], GridQuery::sorting(['sorting' => 'desc']));
        self::assertSame([], GridQuery::sorting(['sorting' => ['asc']]));
        // The direction is kept as asked: refusing a wrong one is for whoever sorts.
        self::assertSame(
            ['key' => 'DESC', 'confidence' => 'asc'],
            GridQuery::sorting(['sorting' => ['asc', 'key' => 'DESC', 'provider' => ['asc'], 'confidence' => 'asc']]),
        );
    }
}
