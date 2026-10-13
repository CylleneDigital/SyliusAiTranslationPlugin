<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Transfer\OverrideImporter;
use CylleneDigital\AiTranslationBundle\Transfer\OverrideImportIssue;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportStager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The confirmation of a staged import: the overrides are written now. */
#[IsGranted(Permission::Import->value)]
final readonly class ImportConfirmController
{
    public function __construct(
        private Screen $screen,
        private ImportStager $stager,
        private OverrideImporter $importer,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->screen->validateCsrf($request);

        $staged = $this->stager->take($request);

        if (null === $staged) {
            $this->screen->flash($request, 'error', 'cyllene_digital_sylius_ai_translation.ui.import_preview_expired');

            return $this->screen->redirectBack($request, $this->screen->postedScope($request));
        }

        try {
            $result = $this->importer->import($staged['content'], $staged['format']);
        } catch (\InvalidArgumentException) {
            $this->screen->flash($request, 'error', 'cyllene_digital_sylius_ai_translation.ui.import_invalid_file');

            return $this->screen->redirectBack($request, $this->screen->postedScope($request));
        }

        $this->screen->flash($request, 'success', [
            'message' => 'cyllene_digital_sylius_ai_translation.ui.import_success',
            'parameters' => ['imported' => $result->imported, 'skipped' => $result->skipped, 'unchanged' => $result->unchanged, 'reverted' => $result->reverted],
        ]);

        // The skipped entries again, as the preview listed them.
        $issues = OverrideImportIssue::summarize($result->issues);

        if ([] !== $issues) {
            $this->screen->flash($request, 'warning', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.import_skipped_entries',
                'parameters' => ['entries' => implode(' ; ', $issues)],
            ]);
        }

        return $this->screen->redirectBack($request, $this->screen->postedScope($request));
    }
}
