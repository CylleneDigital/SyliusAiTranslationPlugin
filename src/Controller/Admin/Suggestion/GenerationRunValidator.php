<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Provider\TranslationProviderRegistry;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\GenerationRefusal;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\GenerationRun;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;

/**
 * The estimate and the run re-check the form's own coordinates on the server: a
 * hand-crafted request must not pick a locale the host (or the selected channel) does not
 * expose — the run would see EVERY key as missing and spend money filling a language
 * nobody renders — nor a catalogue whose files are gone, nor a provider the configuration
 * does not declare.
 */
final readonly class GenerationRunValidator
{
    public function __construct(
        private ChannelScopeResolver $scopeResolver,
        private TranslationManagerInterface $translationManager,
        private TranslationProviderRegistry $providerRegistry,
    ) {
    }

    /** @return GenerationRefusal|null the first broken coordinate, null when valid */
    public function validate(GenerationRun $run, string $scope): ?GenerationRefusal
    {
        if ('' === $run->sourceLocale || '' === $run->targetLocale || $run->sourceLocale === $run->targetLocale) {
            return GenerationRefusal::InvalidLocales;
        }

        $locales = $this->scopeResolver->getLocales($scope) ?? $this->translationManager->getAvailableLocales();

        if (!\in_array($run->sourceLocale, $locales, true) || !\in_array($run->targetLocale, $locales, true)) {
            return GenerationRefusal::UnknownLocale;
        }

        if ('' !== $run->catalogue && null === $this->translationManager->getCatalogue($run->catalogue)) {
            return GenerationRefusal::UnknownCatalogue;
        }

        if ('' !== $run->provider && !$this->providerRegistry->has($run->provider)) {
            return GenerationRefusal::UnknownProvider;
        }

        return null;
    }
}
