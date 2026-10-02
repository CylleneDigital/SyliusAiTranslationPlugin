<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Transfer;

/**
 * The outcome of staging an override import: either the preview is parked (ready for the
 * dashboard's confirmation card) or the file was refused — for a reason that carries its
 * own message ({@see ImportRefusal}), with the entries the file had to skip when that is
 * why nothing is left to import.
 */
final readonly class ImportStage
{
    /**
     * @param list<string> $issues
     */
    private function __construct(
        private ?ImportRefusal $refusal,
        private array $issues = [],
    ) {
    }

    public static function accepted(): self
    {
        return new self(null);
    }

    /**
     * @param list<string> $issues the skipped entries, as lines ({@see \CylleneDigital\AiTranslationBundle\Transfer\OverrideImportIssue::summarize()})
     */
    public static function refused(ImportRefusal $refusal, array $issues = []): self
    {
        return new self($refusal, $issues);
    }

    /** Null when the upload is staged. */
    public function refusal(): ?ImportRefusal
    {
        return $this->refusal;
    }

    /** @return list<string> */
    public function issues(): array
    {
        return $this->issues;
    }
}
