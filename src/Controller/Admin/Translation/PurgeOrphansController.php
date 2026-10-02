<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Bulk-deletes the overrides whose catalogue no longer exists as files. */
#[IsGranted(Permission::Edit->value)]
final readonly class PurgeOrphansController
{
    public function __construct(
        private Screen $screen,
        private TranslationManagerInterface $translationManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->screen->validateCsrf($request);

        $this->screen->flash($request, 'success', [
            'message' => 'cyllene_digital_sylius_ai_translation.ui.purge_orphans_done',
            'parameters' => ['count' => $this->translationManager->purgeOrphanOverrides()],
        ]);

        return $this->screen->redirectBack($request, '');
    }
}
