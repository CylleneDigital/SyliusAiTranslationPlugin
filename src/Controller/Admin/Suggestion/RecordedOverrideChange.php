<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\OverrideChange;

/** What an approval did to the override, as the engine recorded it on the approved row (`metadata.override_change`). */
final class RecordedOverrideChange
{
    public static function of(TranslationSuggestion $suggestion): OverrideChange
    {
        $recorded = ($suggestion->getMetadata() ?? [])['override_change'] ?? null;

        return (\is_string($recorded) ? OverrideChange::tryFrom($recorded) : null) ?? OverrideChange::Write;
    }
}
