<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\OverrideRow;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;

/**
 * The channel a posted row acts on, once the row is checked: a key, a catalogue, a locale and a
 * known channel (or global) - and, to write, a locale that channel exposes. An unknown channel
 * is refused rather than silently downgraded to a global write.
 */
final readonly class RowScopeResolver
{
    public function __construct(
        private ChannelScopeResolver $scopeResolver,
        private TranslationManagerInterface $translationManager,
    ) {
    }

    /** @return string|null the channel code ('' = global), null when the row cannot be written */
    public function resolve(OverrideRow $row): ?string
    {
        $scope = $this->resolveForRemoval($row);

        if (null === $scope) {
            return null;
        }

        // The locales of the channel, as the generation checks them: an override in a locale
        // the channel does not expose would be written where no screen shows it.
        $locales = $this->scopeResolver->getLocales($scope) ?? $this->translationManager->getAvailableLocales();

        return \in_array($row->locale, $locales, true) ? $scope : null;
    }

    /**
     * The same without the locale: an override left in a locale its channel no longer exposes
     * must still be removable.
     *
     * @return string|null the channel code ('' = global), null when the row names no entry
     */
    public function resolveForRemoval(OverrideRow $row): ?string
    {
        $scope = $this->scopeResolver->resolveOrNull($row->scope);

        return null !== $scope && '' !== $row->key && '' !== $row->catalogue && '' !== $row->locale ? $scope : null;
    }
}
