<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Transfer;

use CylleneDigital\AiTranslationBundle\Transfer\OverrideExporter;

/** The download media type of each transfer format ({@see OverrideExporter::FORMATS}). */
final class TransferMediaType
{
    private const array BY_FORMAT = [
        OverrideExporter::FORMAT_XLIFF => 'application/xliff+xml',
        OverrideExporter::FORMAT_CSV => 'text/csv',
    ];

    public static function of(string $format): string
    {
        return self::BY_FORMAT[$format] ?? 'application/octet-stream';
    }
}
