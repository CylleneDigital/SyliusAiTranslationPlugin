<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\OverrideRow;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The edit modal's "remove the override": the inherited value, else the file value, returns. */
#[IsGranted(Permission::Edit->value)]
final readonly class ResetController
{
    public function __construct(
        private Screen $screen,
        private RowScopeResolver $rowScope,
        private TranslationManagerInterface $translationManager,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] OverrideRow $row = new OverrideRow()): Response
    {
        $this->screen->validateCsrf($request);
        $scope = $this->rowScope->resolveForRemoval($row);

        if (null === $scope) {
            $this->screen->flash($request, 'error', 'cyllene_digital_sylius_ai_translation.ui.translation_save_invalid');

            return $this->screen->redirectBack($request, $this->screen->scope($request));
        }

        // A page left open may ask for an override already removed: nothing was deleted then.
        if (!$this->translationManager->hasOverride($row->key, $row->catalogue, $row->locale, $scope)) {
            $this->screen->flash($request, 'info', 'cyllene_digital_sylius_ai_translation.ui.translation_reset_nothing');

            return $this->screen->redirectBack($request, $scope);
        }

        $this->translationManager->removeOverride($row->key, $row->catalogue, $row->locale, $scope);
        $this->screen->flash($request, 'success', 'cyllene_digital_sylius_ai_translation.ui.translation_reset');

        return $this->screen->redirectBack($request, $scope);
    }
}
