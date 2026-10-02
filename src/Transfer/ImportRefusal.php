<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Transfer;

/** Why an uploaded file was not staged, each with its message of the flashes domain. */
enum ImportRefusal: string
{
    case UnsupportedFormat = 'cyllene_digital_sylius_ai_translation.ui.import_unsupported_format';
    case InvalidFile = 'cyllene_digital_sylius_ai_translation.ui.import_invalid_file';
    case StagingFailed = 'cyllene_digital_sylius_ai_translation.ui.import_staging_failed';
    case NothingToImport = 'cyllene_digital_sylius_ai_translation.ui.import_empty';
}
