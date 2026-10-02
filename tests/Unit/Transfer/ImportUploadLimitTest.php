<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Transfer;

use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportUploadLimit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ImportUploadLimitTest extends TestCase
{
    /**
     * @return iterable<string, array{int|float, int, float}>
     */
    public static function phpLimits(): iterable
    {
        yield 'PHP accepts less than the cap: its limit' => [2 * 1024 * 1024, 2 * 1024 * 1024, 2.0];
        yield 'below a megabyte' => [512 * 1024, 512 * 1024, 0.5];
        yield 'PHP accepts more: the cap' => [20 * 1024 * 1024, 8 * 1024 * 1024, 8.0];
        yield 'no limit in PHP' => [(float) \PHP_INT_MAX, 8 * 1024 * 1024, 8.0];
        yield 'not a round figure: never announced above what passes' => [1_572_863, 1_572_863, 1.4];
    }

    #[DataProvider('phpLimits')]
    public function testTheLimitIsTheCapOrWhatPhpAcceptsWhenThatIsLess(int|float $phpLimit, int $bytes, float $megabytes): void
    {
        self::assertSame($bytes, ImportUploadLimit::bytes($phpLimit));
        self::assertSame($megabytes, ImportUploadLimit::megabytes($phpLimit));
    }

    public function testOnlyAnEmptiedRequestAnnouncingMoreThanPhpTakesWasDroppedForItsSize(): void
    {
        $megabyte = 1024 * 1024;
        $announced = ['CONTENT_LENGTH' => (string) (3 * $megabyte)];

        self::assertTrue(ImportUploadLimit::wasExceededBy(new Request(server: $announced), $megabyte));
        // PHP kept the form: the request was not dropped, whatever it announces.
        self::assertFalse(ImportUploadLimit::wasExceededBy(new Request(request: ['_token' => 'x'], server: $announced), $megabyte));
        self::assertFalse(ImportUploadLimit::wasExceededBy(new Request(server: ['CONTENT_LENGTH' => '10']), $megabyte));
        // No limit in PHP: nothing can exceed it.
        self::assertFalse(ImportUploadLimit::wasExceededBy(new Request(server: $announced), 0));
        // No Content-Length reaches PHP as an empty string, a forged one as anything.
        self::assertFalse(ImportUploadLimit::wasExceededBy(new Request(server: ['CONTENT_LENGTH' => '']), $megabyte));
        self::assertFalse(ImportUploadLimit::wasExceededBy(new Request(server: ['CONTENT_LENGTH' => 'abc']), $megabyte));
        self::assertFalse(ImportUploadLimit::wasExceededBy(new Request(), $megabyte));
    }
}
