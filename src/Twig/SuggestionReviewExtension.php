<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Twig;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Override\TranslationValueValidator;
use CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderConsistencyChecker;
use CylleneDigital\AiTranslationBundle\Suggestion\PluralCategories;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What the review screen shows about a suggestion, computed when the page is rendered —
 * the same card the engine's console review prints:
 *
 *  - the value the entry shows today, and where it comes from (its own override, a value
 *    it inherits — the global override in a channel, the parent language's for a regional
 *    locale — or the file): an "every key" run proposes replacements, and approving one
 *    blind is the mistake to avoid. A proposal equal to it writes no override;
 *  - the placeholder and syntax checks of the suggested value, as the approval runs them —
 *    not read from the metadata the generation stored once, which a later fix of the
 *    checker (an ICU branch no longer taken for a placeholder) leaves announcing a refusal
 *    the approval no longer makes;
 *  - the plural categories the target language needs and the value lacks: valid ICU, so
 *    the syntax check passes, but each number of a missing category falls back to
 *    "other" ("your 2th order").
 */
final class SuggestionReviewExtension extends AbstractExtension implements ResetInterface
{
    /** @var array<string, array<string, array{original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null}>> "catalogue|locale|scope" => key => entry */
    private array $entries = [];

    /** @var array<int, array{current: ?string, currentOrigin: 'override'|'inherited'|'file'|null, inheritedFrom: array{locale: string, scope: string}|null, sameAsCurrent: bool, placeholders: array{missing: list<string>, unexpected: list<string>}, syntax: list<string>, pluralGaps: list<array{argument: string, kind: string, missing: list<string>}>}> suggestion object id => its review */
    private array $reviews = [];

    public function __construct(
        private readonly TranslationManagerInterface $translationManager,
        private readonly PlaceholderConsistencyChecker $placeholderChecker,
        private readonly TranslationValueValidator $valueValidator,
    ) {
    }

    /** Emptied between requests: a worker runtime keeps the service alive across them. */
    #[\Override]
    public function reset(): void
    {
        $this->entries = [];
        $this->reviews = [];
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cyllene_digital_sylius_ai_translation_review', $this->review(...)),
        ];
    }

    /**
     * Reads the entries of a whole page of suggestions at once: two queries per (locale,
     * channel) the page holds, where the first row of each catalogue cost two of its own.
     *
     * @param iterable<mixed> $suggestions
     */
    public function prefetch(iterable $suggestions): void
    {
        $catalogues = [];

        foreach ($suggestions as $suggestion) {
            if ($suggestion instanceof TranslationSuggestion) {
                $catalogues[$suggestion->getLocale()][$suggestion->getScope()][$suggestion->getCatalogue()] = true;
            }
        }

        foreach ($catalogues as $locale => $scopes) {
            foreach ($scopes as $scope => $names) {
                $locale = (string) $locale;
                $scope = (string) $scope;

                foreach ($this->translationManager->getTranslationsForCatalogues(array_map('strval', array_keys($names)), $locale, $scope) as $catalogue => $entries) {
                    $this->entries[$catalogue . '|' . $locale . '|' . $scope] ??= $entries;
                }
            }
        }
    }

    /**
     * @return array{
     *     current: ?string,
     *     currentOrigin: 'override'|'inherited'|'file'|null,
     *     inheritedFrom: array{locale: string, scope: string}|null,
     *     sameAsCurrent: bool,
     *     placeholders: array{missing: list<string>, unexpected: list<string>},
     *     syntax: list<string>,
     *     pluralGaps: list<array{argument: string, kind: string, missing: list<string>}>,
     * }
     */
    public function review(TranslationSuggestion $suggestion): array
    {
        // A row asks three times (two cells and its button): the checks run once.
        return $this->reviews[spl_object_id($suggestion)] ??= $this->compute($suggestion);
    }

    /**
     * @return array{
     *     current: ?string,
     *     currentOrigin: 'override'|'inherited'|'file'|null,
     *     inheritedFrom: array{locale: string, scope: string}|null,
     *     sameAsCurrent: bool,
     *     placeholders: array{missing: list<string>, unexpected: list<string>},
     *     syntax: list<string>,
     *     pluralGaps: list<array{argument: string, kind: string, missing: list<string>}>,
     * }
     */
    private function compute(TranslationSuggestion $suggestion): array
    {
        $entry = $this->entry($suggestion);
        $value = $suggestion->getSuggestedValue();

        [$current, $origin] = match (true) {
            null !== $entry['override'] => [$entry['override'], 'override'],
            null !== $entry['inherited'] => [$entry['inherited'], 'inherited'],
            null !== $entry['original'] => [$entry['original'], 'file'],
            default => [null, null],
        };

        return [
            'current' => $current,
            'currentOrigin' => $origin,
            'inheritedFrom' => 'inherited' === $origin ? $entry['inheritedFrom'] : null,
            'sameAsCurrent' => null !== $value && $value === $current,
            'placeholders' => null !== $value ? $this->placeholderChecker->explain($suggestion->getSourceValue(), $value) : ['missing' => [], 'unexpected' => []],
            'syntax' => null !== $value ? $this->valueValidator->validate($value, $suggestion->getKey(), $suggestion->getCatalogue(), $suggestion->getLocale()) : [],
            'pluralGaps' => null !== $value ? PluralCategories::missingIn($value, $suggestion->getLocale()) : [],
        ];
    }

    /**
     * The suggestion's entry as the catalogue browser reads it: from the page's prefetch
     * ({@see prefetch()}), else one read per (catalogue, locale, channel), not per row.
     *
     * @return array{original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null}
     */
    private function entry(TranslationSuggestion $suggestion): array
    {
        $slice = $suggestion->getCatalogue() . '|' . $suggestion->getLocale() . '|' . $suggestion->getScope();
        $this->entries[$slice] ??= $this->translationManager->getTranslationsForCatalogues([$suggestion->getCatalogue()], $suggestion->getLocale(), $suggestion->getScope())[$suggestion->getCatalogue()];

        return $this->entries[$slice][$suggestion->getKey()]
            ?? ['original' => null, 'override' => null, 'hasOverride' => false, 'inherited' => null, 'inheritedFrom' => null];
    }
}
