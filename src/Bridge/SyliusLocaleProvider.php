<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Bridge;

use CylleneDigital\AiTranslationBundle\Catalogue\LocaleProviderInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * Replaces the engine's file-scan locale source with the translation locales defined in
 * the Sylius admin (aliased over the engine's default in config/services/bridge.php).
 */
final readonly class SyliusLocaleProvider implements LocaleProviderInterface
{
    public function __construct(
        private TranslationLocaleProviderInterface $localeProvider,
    ) {
    }

    #[\Override]
    public function getAvailableLocales(): array
    {
        // array_values(): Sylius 2.2 documents array<string>, the engine interface list<string>.
        return array_values($this->localeProvider->getDefinedLocalesCodes());
    }
}
