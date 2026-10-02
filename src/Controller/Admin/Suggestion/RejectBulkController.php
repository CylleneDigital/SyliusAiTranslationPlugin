<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiServiceInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The grid's bulk rejection: the selected pending rows of the page's channel ({@see PendingSuggestionSelector}). */
#[IsGranted(Permission::Review->value)]
final readonly class RejectBulkController
{
    public function __construct(
        private Screen $screen,
        private PendingSuggestionSelector $selector,
        private TranslationAiServiceInterface $aiService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->screen->validateCsrf($request);
        $suggestions = $this->selector->select($request);

        // A single flush for the whole batch instead of one per item.
        $this->aiService->rejectMany($suggestions);

        $this->screen->flash($request, [] !== $suggestions ? 'success' : 'warning', [
            'message' => 'cyllene_digital_sylius_ai_translation.ui.bulk_rejected',
            'parameters' => ['count' => \count($suggestions)],
        ]);

        return $this->screen->redirectBack($request, $this->selector->scope($request) ?? '');
    }
}
