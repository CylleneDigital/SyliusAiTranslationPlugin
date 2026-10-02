<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Suggestion\SuggestionAlreadyReviewedException;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiServiceInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rejecting deletes the suggestion. Reached from two places: Sylius' grid delete modal
 * (`_method=DELETE` + a csrf token named after the row id) and the plugin's review modal
 * (POST + the screen's csrf intention).
 */
#[IsGranted(Permission::Review->value)]
final readonly class RejectController
{
    public function __construct(
        private Screen $screen,
        private TranslationAiServiceInterface $aiService,
    ) {
    }

    public function __invoke(Request $request, #[MapEntity(id: 'id')] TranslationSuggestion $suggestion): Response
    {
        $valid = $this->screen->isTokenValid((string) $suggestion->getId(), $request->request->get('_csrf_token')) ||
            $this->screen->isScreenTokenValid($request->request->get('_token'));

        if (!$valid) {
            throw new AccessDeniedException('Invalid CSRF token.');
        }

        // A stale page acting on a suggestion reviewed meanwhile, or one reviewed between
        // this check and the engine's own: the same warning.
        $rejected = $suggestion->isPending();

        if ($rejected) {
            try {
                $this->aiService->reject($suggestion);
            } catch (SuggestionAlreadyReviewedException) {
                $rejected = false;
            }
        }

        $rejected
            ? $this->screen->flash($request, 'success', 'cyllene_digital_sylius_ai_translation.ui.suggestion_rejected')
            : $this->screen->flash($request, 'warning', 'cyllene_digital_sylius_ai_translation.ui.suggestion_already_reviewed');

        return $this->screen->redirectBack($request, $suggestion->getScope());
    }
}
