<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\Suggestion;

use Behat\Mink\Element\NodeElement;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\AdminScreenPage;

/** The suggestions screen: the generation form, the pending list, the review modal, the journal. */
final class IndexPage extends AdminScreenPage
{
    public function getRouteName(): string
    {
        return 'cyllene_digital_sylius_ai_translation_admin_suggestion_index';
    }

    public function canGenerate(): bool
    {
        return $this->shows('#generate-form');
    }

    public function canReview(): bool
    {
        return $this->shows('form[action$="/approve"]');
    }

    public function chooseTargetLocale(string $locale): void
    {
        $this->getDocument()->selectFieldOption('generate_target', $locale);
    }

    public function chooseCatalogue(string $catalogue): void
    {
        $this->getDocument()->selectFieldOption('generate_catalogue', $catalogue);
    }

    public function chooseProvider(string $provider): void
    {
        $this->getDocument()->selectFieldOption('generate_provider', $provider);
    }

    public function generate(): void
    {
        $this->press('#generate-form button.btn-primary[type="submit"]');
    }

    public function confirmGenerationOnEveryCatalogue(): void
    {
        $this->press('button[form="generate-form"]', $this->waitForModal('generate-all-modal'));
    }

    public function isAskedToConfirmEveryCatalogue(): bool
    {
        return str_contains($this->waitForModal('generate-all-modal')->getText(), 'catalogues');
    }

    public function askForEstimate(): void
    {
        $this->press('[data-ait-admin-generation-target="estimateButton"]');
    }

    /** The estimate as displayed, once it has an answer ('' when it is cleared). */
    public function estimate(bool $awaited = true): string
    {
        $result = $this->getDocument()->find('css', '[data-ait-admin-generation-target="estimateResult"]');

        if ($awaited) {
            $this->getDocument()->waitFor(5, static fn (): bool => !\in_array($result?->getText(), [null, '', '…'], true));
        }

        return $result?->getText() ?? '';
    }

    public function approve(string $key): void
    {
        $this->press('form[action$="/approve"] button[type="submit"]', $this->row($key));
    }

    public function reject(string $key): void
    {
        $this->press('form[action$="/reject"] button[type="submit"]', $this->row($key));
    }

    public function openReviewModal(string $key): void
    {
        $this->press('button[data-bs-target="#suggestion-review-modal"]', $this->row($key));
        $this->waitForModal('suggestion-review-modal');
    }

    public function typeSuggestedValue(string $value): void
    {
        $this->getDocument()->fillField('suggestion-review-value', $value);
    }

    public function approveFromModal(): void
    {
        $this->press('[data-ait-admin-review-modal-target="approve"]', $this->waitForModal('suggestion-review-modal'));
    }

    public function canApproveFromModal(): bool
    {
        $button = $this->waitForModal('suggestion-review-modal')->find('css', '[data-ait-admin-review-modal-target="approve"]');

        return null !== $button && !$button->hasAttribute('disabled');
    }

    /** The review modal says the row waits for the provider, and shows no generation error. */
    public function isReviewShownAsWithTheProvider(): bool
    {
        $modal = $this->waitForModal('suggestion-review-modal');
        $inFlight = $modal->find('css', '[data-ait-admin-review-modal-target="inFlight"]');
        $error = $modal->find('css', '[data-ait-admin-review-modal-target="error"]');

        return null !== $inFlight && $inFlight->isVisible() && (null === $error || !$error->isVisible());
    }

    public function selectEveryRow(): void
    {
        foreach ($this->getDocument()->findAll('css', 'tbody input[type="checkbox"]') as $checkbox) {
            $checkbox->check();
        }
    }

    public function approveSelection(): void
    {
        $this->press('[data-bs-target="#suggestion-bulk-approve-modal"]');
        $this->press('form[action$="/approve-bulk"] button[type="submit"]', $this->waitForModal('suggestion-bulk-approve-modal'));
    }

    /** @return list<string> the rows of the generation journal, the latest first */
    public function journal(): array
    {
        return array_values(array_map(
            static fn (NodeElement $row): string => $row->getText(),
            $this->getDocument()->findAll('css', '[data-test-generation-journal] tbody tr'),
        ));
    }
}
