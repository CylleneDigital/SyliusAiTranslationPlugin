<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Override;

use CylleneDigital\AiTranslationBundle\Override\InvalidOverrideException;
use CylleneDigital\AiTranslationBundle\Override\OverrideChange;
use CylleneDigital\AiTranslationBundle\Override\OverrideReader;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Override\TranslationValueValidator;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderConsistencyChecker;

/**
 * The hand edit of one entry, under the engine's rule for every door that writes a chosen
 * value ({@see OverrideChange}): a value the entry already shows writes nothing, the value
 * it would show without its override (its baseline: the value it inherits — the global
 * override in a channel, the parent language's for a regional locale — else the file
 * value) removes the override instead of storing a copy.
 *
 * The entry's coordinates are the caller's to check (a known locale, a known channel).
 */
final readonly class ManualOverrideEditor
{
    public function __construct(
        private TranslationManagerInterface $translationManager,
        private OverrideReader $overrides,
        private TranslationSuggestionRepository $suggestionRepository,
        private TranslationValueValidator $valueValidator,
        private PlaceholderConsistencyChecker $placeholderChecker,
    ) {
    }

    /**
     * @param string $value the value as typed, its edge spaces included — a label concatenated in a template may need them
     */
    public function edit(string $key, string $catalogue, string $locale, string $scope, string $value): ManualEdit
    {
        // Only the line endings are the browser's: a textarea posts "\r\n", the catalogues hold "\n".
        $value = str_replace("\r\n", "\n", $value);
        $value = '' === trim($value) ? '' : $value;

        $fileValue = $this->overrides->getFileValue($key, $catalogue, $locale);
        $baseline = $this->overrides->getBaselineValue($key, $catalogue, $locale, $scope);
        $stored = $this->translationManager->getOverrideValue($key, $catalogue, $locale, $scope);

        if ('' === $value && null === $baseline) {
            if (null === $stored) {
                return new ManualEdit(ManualEditOutcome::EmptyIgnored);
            }

            $this->translationManager->removeOverride($key, $catalogue, $locale, $scope);

            return new ManualEdit(ManualEditOutcome::Cleared);
        }

        // An empty override would be served as an empty text where the entry has one to show.
        if ('' === $value) {
            return new ManualEdit(ManualEditOutcome::EmptyRefused);
        }

        $change = OverrideChange::decide($value, $baseline, $stored);

        if (OverrideChange::None === $change) {
            return new ManualEdit(ManualEditOutcome::Unchanged);
        }

        if (OverrideChange::Revert === $change) {
            $this->translationManager->removeOverride($key, $catalogue, $locale, $scope);

            // Where the value comes from, not what it reads: an inherited override that says
            // what the file says is still the one the entry follows from now on.
            $inherited = $this->translationManager->getTranslationsForCatalogue($catalogue, $locale, $scope)[$key]['inherited'] ?? null;

            return new ManualEdit(ManualEditOutcome::Reverted, revertedToFile: null === $inherited);
        }

        // A hand-posted catalogue whose files are gone must not become a brand-new orphan
        // override — the row would only be reachable from the "modified only" view. Editing
        // an EXISTING orphan override (the cleanup workflow) still can.
        if (null === $this->translationManager->getCatalogue($catalogue) && null === $stored) {
            return new ManualEdit(ManualEditOutcome::CatalogueUnknown);
        }

        // The value must match the catalogue's declared syntax (ICU vs legacy).
        $issues = $this->valueValidator->validate($value, $key, $catalogue, $locale);

        if ([] !== $issues) {
            return new ManualEdit(ManualEditOutcome::SyntaxInvalid, syntaxIssues: $issues);
        }

        // Symfony accepts any string as a key (whole sentences, in the validators
        // catalogue): one the override table cannot store is refused up front.
        if (!$this->translationManager->acceptsKey($key)) {
            return new ManualEdit(ManualEditOutcome::KeyTooLong);
        }

        // The write closes the entry's pending suggestions (someone chose the value): counted
        // first, so the reviewer learns why they left the review screen.
        $closed = $this->countPendingSuggestions($key, $catalogue, $locale, $scope);

        try {
            $this->translationManager->saveOverride($key, $catalogue, $locale, $value, $fileValue, $scope);
        } catch (InvalidOverrideException) {
            return new ManualEdit(ManualEditOutcome::Invalid);
        }

        // Dropping a placeholder can be deliberate on a hand edit: saved, but said —
        // compared with the value the visitors saw until now.
        $previous = $stored ?? $baseline;
        $placeholders = null !== $previous && !$this->placeholderChecker->isConsistent($previous, $value)
            ? $this->placeholderChecker->explain($previous, $value)
            : null;

        return new ManualEdit(ManualEditOutcome::Saved, placeholders: $placeholders, closedSuggestions: $closed);
    }

    private function countPendingSuggestions(string $key, string $catalogue, string $locale, string $scope): int
    {
        return (int) $this->suggestionRepository->createPendingQueryBuilder($scope)
            ->select('COUNT(o.id)')
            ->andWhere('o.locale = :locale')
            ->andWhere('o.catalogue = :catalogue')
            ->andWhere('o.key = :key')
            ->setParameter('locale', $locale)
            ->setParameter('catalogue', $catalogue)
            ->setParameter('key', $key)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
