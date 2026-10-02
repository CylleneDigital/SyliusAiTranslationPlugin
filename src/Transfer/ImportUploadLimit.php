<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Transfer;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * The size an import file may have: the plugin's own cap (a whole file is read in memory to
 * parse it), or what PHP accepts when that is less - the limit the screen announces is then
 * the one the upload really meets.
 */
final class ImportUploadLimit
{
    private const int CAP = 8 * 1024 * 1024;

    /**
     * @param int|float|null $phpLimit what PHP accepts, in bytes - its own settings when null
     *
     * @return positive-int
     */
    public static function bytes(int|float|null $phpLimit = null): int
    {
        return max(1, (int) min(self::CAP, $phpLimit ?? UploadedFile::getMaxFilesize()));
    }

    /**
     * Whether PHP dropped the whole request for its size: over post_max_size it empties the
     * form, file and CSRF token alike, and what is left reads like a forged request.
     *
     * @param int|null $postMaxSize PHP's post_max_size in bytes (0 = none) - its own setting when null
     */
    public static function wasExceededBy(Request $request, ?int $postMaxSize = null): bool
    {
        $postMaxSize ??= ini_parse_quantity((string) \ini_get('post_max_size'));
        // No Content-Length (a chunked upload, some HTTP/2 clients) reaches PHP as an empty
        // string: nothing announced is nothing to compare.
        $announced = $request->server->get('CONTENT_LENGTH');

        return $postMaxSize > 0 &&
            is_numeric($announced) &&
            (int) $announced > $postMaxSize &&
            0 === $request->request->count() &&
            0 === $request->files->count();
    }

    /**
     * For display: megabytes, one decimal at most.
     *
     * @param int|float|null $phpLimit what PHP accepts, in bytes - its own settings when null
     */
    public static function megabytes(int|float|null $phpLimit = null): float
    {
        return floor(self::bytes($phpLimit) / 1024 / 1024 * 10) / 10;
    }
}
