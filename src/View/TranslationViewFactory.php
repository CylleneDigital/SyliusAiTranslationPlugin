<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\View;

use CylleneDigital\AiTranslationBundle\Catalogue\TranslationCatalogue;
use CylleneDigital\AiTranslationBundle\Entity\TranslationOverride;
use CylleneDigital\AiTranslationBundle\Override\OverrideReader;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;

/**
 * Builds the rows of one browser view — the single source of truth shared by the
 * catalogue grid (which paginates them) and the export route (which serialises
 * them all): same selection semantics, same filters, so "export this view"
 * downloads exactly what the table displays.
 *
 * Navigation follows the translations/ directory convention: category (first
 * folder level), domain (the folders below, possibly nested), type (the file
 * name). No level has to be selected: the rows span every catalogue matching the
 * levels that ARE selected — the whole project when none is — and the search,
 * "missing only" and "modified only" filters narrow that union.
 */
final readonly class TranslationViewFactory
{
    public function __construct(
        private TranslationManagerInterface $translationManager,
        private OverrideReader $overrides,
    ) {
    }

    /**
     * @param string $referenceLocale the display reference (channel default locale, else the default locale)
     * @param string $defaultLocale   the coverage default locale — per-row fallback when the reference has no value
     * @param list<string> $scopeLocales the locales this view exposes (the channel's, every
     *     host locale in the global view) — their keys are listed too, so no key is hidden
     *     whichever locale is browsed
     *
     * @return list<array{key: string, catalogue: string, original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null, reference: ?string, referenceLocale: ?string, missing: bool}>
     */
    public function getEntries(
        string $locale,
        string $scope,
        string $category,
        string $domain,
        string $type,
        string $search,
        bool $onlyOverrides,
        bool $onlyMissing,
        string $referenceLocale,
        string $defaultLocale,
        array $scopeLocales,
    ): array {
        // "Modified only" costs what is modified: one query tells which catalogues hold an
        // override, and only those have their files read.
        $overrides = $onlyOverrides ? $this->translationManager->getOverridesForLocale($locale, $scope) : [];

        if ($onlyOverrides && [] === $overrides) {
            return [];
        }

        $catalogues = $this->getScopedCatalogues($category, $domain, $type);

        if ($onlyOverrides) {
            $modified = array_fill_keys(array_map(static fn (TranslationOverride $override): string => $override->getCatalogue(), $overrides), true);
            $catalogues = array_values(array_filter($catalogues, static fn (TranslationCatalogue $catalogue): bool => isset($modified[$catalogue->identifier])));
        }

        // The reference cascade, per key: the display reference first, then the
        // coverage default locale, then the view's remaining locales in host order —
        // the first locale holding a non-empty value wins.
        $referenceOrder = array_values(array_unique(array_filter(
            [$referenceLocale, $defaultLocale, ...$scopeLocales],
            static fn (string $other): bool => '' !== $other && $other !== $locale,
        )));

        // One query per locale for the whole view (instead of one or two per
        // (catalogue, locale) pair, the way the coverage report does it).
        $localeOverrides = [];

        foreach ($referenceOrder as $other) {
            $localeOverrides[$other] = $this->translationManager->getEffectiveOverridesByCatalogue($other, $scope);
        }

        // The browsed locale's entries, in two queries whatever the number of catalogues.
        $targets = $this->overrides->getTranslationsForCatalogues(
            array_map(static fn (TranslationCatalogue $catalogue): string => $catalogue->identifier, $catalogues),
            $locale,
            $scope,
        );

        // The levels that ARE selected scope the union — all catalogues when none is.
        $entries = array_merge(...array_map(
            fn (TranslationCatalogue $catalogue): array => $this->getCatalogueEntries($catalogue->identifier, $targets[$catalogue->identifier], $referenceOrder, $localeOverrides),
            $catalogues,
        ) ?: [[]]);

        // "Modified only": the same rows, reference and inherited value included, narrowed to
        // the ones holding an override — plus the overrides no file row can carry any more.
        if ($onlyOverrides) {
            $entries = array_values(array_filter($entries, static fn (array $entry): bool => $entry['hasOverride']));
            $orphans = $this->getOrphanOverrideEntries($overrides, $category, $domain, $type);

            if ([] !== $orphans) {
                $entries = [...$entries, ...$orphans];
                // Back to the order every view arrives in: catalogue, then key.
                usort($entries, static fn (array $a, array $b): int => strcmp($a['catalogue'], $b['catalogue']) ?: strcmp($a['key'], $b['key']));
            }
        }

        if ('' !== $search) {
            $needle = mb_strtolower($search);
            $entries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => str_contains(mb_strtolower($entry['key']), $needle) ||
                    str_contains(mb_strtolower($entry['original'] ?? ''), $needle) ||
                    str_contains(mb_strtolower($entry['override'] ?? ''), $needle) ||
                    str_contains(mb_strtolower($entry['inherited'] ?? ''), $needle) ||
                    str_contains(mb_strtolower($entry['reference'] ?? ''), $needle),
            ));
        }

        if ($onlyMissing) {
            $entries = array_values(array_filter($entries, static fn (array $entry): bool => $entry['missing']));
        }

        return $entries;
    }

    /**
     * The catalogues matching the levels that ARE selected — all of them when none is.
     *
     * @return list<TranslationCatalogue>
     */
    private function getScopedCatalogues(string $category, string $domain, string $type): array
    {
        return array_values(array_filter(
            $this->translationManager->getCatalogues(),
            static fn (TranslationCatalogue $catalogue): bool => ('' === $category || $catalogue->category === $category) &&
                ('' === $domain || $catalogue->domain === $domain) &&
                ('' === $type || $catalogue->type === $type),
        ));
    }

    /**
     * One catalogue's rows: the browsed locale's keys, extended with the keys every
     * other locale of the view carries (like the engine's effective-key set: a key with
     * a non-empty effective value anywhere is listed) — those extra rows are the
     * untranslated ones, shown with the reference text (first locale in the cascade
     * holding it) so they can be translated by hand in place.
     *
     * @param array<string, array{original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null}> $target the browsed locale's entries, key => entry
     * @param list<string> $referenceOrder the other locales, in reference-priority order
     * @param array<string, array<string, array<string, string>>> $localeOverrides locale => catalogue => key => effective value
     *
     * @return list<array{key: string, catalogue: string, original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null, reference: ?string, referenceLocale: ?string, missing: bool}>
     */
    private function getCatalogueEntries(string $identifier, array $target, array $referenceOrder, array $localeOverrides): array
    {
        /** @var array<string, array{0: string, 1: string}> $references key => [locale, effective value] — first source wins */
        $references = [];

        foreach ($referenceOrder as $referencing) {
            $files = $this->translationManager->getOriginalValues($identifier, $referencing);
            $overrides = $localeOverrides[$referencing][$identifier] ?? [];

            foreach (array_keys($files + $overrides) as $key) {
                $value = $overrides[$key] ?? $files[$key];

                if ('' !== $value && !isset($references[$key])) {
                    $references[$key] = [$referencing, $value];
                }
            }
        }

        // PHP turns a numeric key ("404") into an integer index: the rows carry strings.
        $keys = array_map(strval(...), array_keys($target + $references));
        sort($keys, \SORT_STRING);

        $entries = [];

        foreach ($keys as $key) {
            $data = $target[$key] ?? null;

            $entries[] = [
                'key' => $key,
                'catalogue' => $identifier,
                'original' => $data['original'] ?? null,
                'override' => $data['override'] ?? null,
                'hasOverride' => $data['hasOverride'] ?? false,
                'inherited' => $data['inherited'] ?? null,
                // Which override the inherited value comes from: the global one in a channel
                // view, or the parent language's for a regional locale.
                'inheritedFrom' => $data['inheritedFrom'] ?? null,
                'reference' => $references[$key][1] ?? null,
                'referenceLocale' => $references[$key][0] ?? null,
                'missing' => \in_array(($data['override'] ?? null) ?? ($data['inherited'] ?? null) ?? ($data['original'] ?? null), [null, ''], true),
            ];
        }

        return $entries;
    }

    /**
     * The overrides whose catalogue no longer exists as files: cleanup candidates, which
     * only the "modified only" view can show. They have no file value, nothing to inherit
     * and no reference text.
     *
     * @param list<TranslationOverride> $overrides the overrides of the browsed locale and scope
     *
     * @return list<array{key: string, catalogue: string, original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null, reference: ?string, referenceLocale: ?string, missing: bool}>
     */
    private function getOrphanOverrideEntries(array $overrides, string $category, string $domain, string $type): array
    {
        $known = array_fill_keys($this->translationManager->getCatalogueIdentifiers(), true);
        $entries = [];

        foreach ($overrides as $override) {
            if (isset($known[$override->getCatalogue()])) {
                continue;
            }

            $catalogue = TranslationCatalogue::fromIdentifier($override->getCatalogue());

            if (('' !== $category && $catalogue->category !== $category) ||
                ('' !== $domain && $catalogue->domain !== $domain) ||
                ('' !== $type && $catalogue->type !== $type)
            ) {
                continue;
            }

            $entries[] = [
                'key' => $override->getKey(),
                'catalogue' => $override->getCatalogue(),
                'original' => null,
                'override' => $override->getValue(),
                'hasOverride' => true,
                'inherited' => null,
                'inheritedFrom' => null,
                'reference' => null,
                'referenceLocale' => null,
                'missing' => false,
            ];
        }

        return $entries;
    }
}
