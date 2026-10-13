<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportStager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Drops a staged import without writing anything. */
#[IsGranted(Permission::Import->value)]
final readonly class ImportCancelController
{
    public function __construct(
        private Screen $screen,
        private ImportStager $stager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->screen->validateCsrf($request);

        $this->stager->clear($request);
        $this->screen->flash($request, 'success', 'cyllene_digital_sylius_ai_translation.ui.import_cancelled');

        return $this->screen->redirectBack($request, $this->screen->postedScope($request));
    }
}
