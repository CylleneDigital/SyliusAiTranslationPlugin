<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\View;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A placeholder difference told by direction, as the engine reports it
 * ({@see \CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderConsistencyChecker::explain()}):
 * what the value lacks, and what it has that the reference does not - a lost placeholder
 * and an added one do not send the reviewer looking for the same thing.
 */
final class PlaceholderDescription
{
    /**
     * @param list<string> $missing
     * @param list<string> $unexpected
     */
    public static function describe(TranslatorInterface $translator, array $missing, array $unexpected): string
    {
        return implode(' · ', array_filter([
            [] !== $missing ? $translator->trans('cyllene_digital_sylius_ai_translation.ui.placeholders_missing', ['list' => implode(', ', $missing)]) : null,
            [] !== $unexpected ? $translator->trans('cyllene_digital_sylius_ai_translation.ui.placeholders_unexpected', ['list' => implode(', ', $unexpected)]) : null,
        ]));
    }
}
