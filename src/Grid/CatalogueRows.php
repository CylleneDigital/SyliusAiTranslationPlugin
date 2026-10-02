<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use CylleneDigital\AiTranslationBundle\Catalogue\TranslationCatalogue;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\View\TranslationViewFactory;

/**
 * The catalogue rows a set of grid criteria selects — the single source of truth shared
 * by the grid's data provider and the "export this view" route, so the export downloads
 * exactly what the table displays. Resolves the browsed locale (validated, defaulting
 * to the channel's default one) and hands every row its locale/scope so the edit modal is
 * self-contained.
 */
final readonly class CatalogueRows
{
    public const string CRITERIA_TRUE = 'true';

    public function __construct(
        private TranslationViewFactory $viewFactory,
        private TranslationManagerInterface $translationManager,
        private ChannelScopeResolver $scopeResolver,
    ) {
    }

    /**
     * The locale a criteria set browses — validated; by default the channel's default locale, else the
     * coverage default one, else the first locale the view offers.
     *
     * @param array<string, mixed> $criteria
     */
    public function resolveLocale(array $criteria, string $scope): string
    {
        $locales = $this->scopeResolver->getLocales($scope) ?? $this->translationManager->getAvailableLocales();
        $requested = \is_string($criteria['locale'] ?? null) ? $criteria['locale'] : '';

        if (\in_array($requested, $locales, true)) {
            return $requested;
        }

        // Arriving without a language: the channel's default locale (else the coverage
        // default locale) — you land on the translations that channel actually shows, and the
        // reference column only appears once you switch to another language.
        $default = $this->scopeResolver->getReferenceLocale($scope);

        return \in_array($default, $locales, true) ? $default : ($locales[0] ?? $this->scopeResolver->getDefaultLocale());
    }

    /**
     * @param array<string, mixed> $criteria the grid's `criteria[...]` request parameter
     * @param array<array-key, mixed> $sorting the grid's `sorting[...]` one: only the key is sortable
     *
     * @return list<array{key: string, catalogue: string, locale: string, scope: string, original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null, reference: ?string, referenceLocale: ?string, missing: bool}>
     */
    public function get(array $criteria, string $scope, array $sorting = []): array
    {
        $locale = $this->resolveLocale($criteria, $scope);
        $identifier = \is_string($criteria['catalogue'] ?? null) ? $criteria['catalogue'] : '';
        $searchCriteria = \is_array($criteria['search'] ?? null) ? $criteria['search'] : [];
        $search = \is_string($searchCriteria['value'] ?? null) ? trim($searchCriteria['value']) : '';
        $modified = self::CRITERIA_TRUE === ($criteria['modified'] ?? null);
        $missing = self::CRITERIA_TRUE === ($criteria['missing'] ?? null);

        // A "cat:shop" value selects a whole category; a catalogue narrows the scan to its
        // exact selection - which, for an orphan one (its files are gone, its overrides
        // remain), matches no file at all and leaves its overrides alone.
        $category = '';

        if (str_starts_with($identifier, GridChoiceProvider::CATEGORY_PREFIX)) {
            $category = substr($identifier, \strlen(GridChoiceProvider::CATEGORY_PREFIX));
            $identifier = '';
        }

        $catalogue = '' !== $identifier ? $this->translationManager->getCatalogue($identifier) : null;

        // An unknown catalogue only has rows left as overrides: no file to scan for it.
        if ('' !== $identifier && null === $catalogue && !$modified) {
            return [];
        }

        if ('' !== $identifier) {
            $catalogue ??= TranslationCatalogue::fromIdentifier($identifier);
        }

        $entries = $this->viewFactory->getEntries(
            $locale,
            $scope,
            $catalogue->category ?? $category,
            $catalogue->domain ?? '',
            $catalogue->type ?? '',
            $search,
            $modified,
            $missing,
            $this->scopeResolver->getReferenceLocale($scope),
            $this->scopeResolver->getDefaultLocale(),
            $this->scopeResolver->getLocales($scope) ?? $this->translationManager->getAvailableLocales(),
        );

        if ('' !== $identifier) {
            $entries = array_values(array_filter($entries, static fn (array $entry): bool => $entry['catalogue'] === $identifier));
        }

        // The natural order (catalogue, then key) is how the rows arrive; an explicit key
        // sort spans the catalogues instead.
        if (\is_string($sorting['key'] ?? null)) {
            $direction = 'desc' === strtolower($sorting['key']) ? -1 : 1;
            usort($entries, static fn (array $a, array $b): int => $direction * strcmp($a['key'], $b['key']));
        }

        return array_map(static fn (array $entry): array => $entry + ['locale' => $locale, 'scope' => $scope], $entries);
    }
}
