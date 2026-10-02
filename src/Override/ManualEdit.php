<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Override;

/**
 * The result of a hand edit: what it came to and what there is to tell about it.
 */
final readonly class ManualEdit
{
    /**
     * @param list<string> $syntaxIssues the engine's issue codes, when the value was refused for its syntax
     * @param array{missing: list<string>, unexpected: list<string>}|null $placeholders how the saved value's placeholders differ from the one shown until now, null when they match
     */
    public function __construct(
        public ManualEditOutcome $outcome,
        /** A revert only: whether the value the entry returns to is the file's (else an inherited override). */
        public bool $revertedToFile = false,
        public array $syntaxIssues = [],
        public ?array $placeholders = null,
        /** The pending suggestions of the entry the save superseded. */
        public int $closedSuggestions = 0,
    ) {
    }
}
