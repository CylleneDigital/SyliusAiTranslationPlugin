<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\OverrideChange;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiServiceInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The grid's bulk approval: the selected pending rows of the page's channel ({@see PendingSuggestionSelector}). */
#[IsGranted(Permission::Review->value)]
final readonly class ApproveBulkController
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

        // One flush + one cache invalidation per locale for the whole batch. Suggestions
        // whose value loses source placeholders are never half-applied: the batch returns
        // them so they can be surfaced for an individual review. It returns as well the ones
        // someone else reviewed since the page was loaded - which nothing on the entity tells
        // apart here (the engine checks the database, not the object), hence one message.
        $skipped = $this->aiService->approveMany($suggestions);
        $approved = \count($suggestions) - \count($skipped);

        // What the approvals did to the overrides, as recorded on each approved row: a
        // value the entry already showed wrote nothing, one equal to its baseline removed
        // the override.
        $changes = array_fill_keys(array_column(OverrideChange::cases(), 'value'), 0);

        foreach ($suggestions as $suggestion) {
            if ($suggestion->isApproved()) {
                ++$changes[RecordedOverrideChange::of($suggestion)->value];
            }
        }

        // Like the bulk reject, an empty batch (nothing selected, or nothing still pending
        // on this channel) says so rather than returning silently.
        if ($approved > 0 || [] === $skipped) {
            $this->screen->flash($request, $approved > 0 ? 'success' : 'warning', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.bulk_approved',
                'parameters' => [
                    'count' => $approved,
                    'unchanged' => $changes[OverrideChange::None->value],
                    'reverted' => $changes[OverrideChange::Revert->value],
                ],
            ]);
        }

        if ([] !== $skipped) {
            $this->screen->flash($request, 'warning', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.bulk_approve_skipped_keys',
                'parameters' => [
                    'count' => \count($skipped),
                    'keys' => implode(', ', array_map(static fn (TranslationSuggestion $suggestion): string => $suggestion->getKey(), $skipped)),
                ],
            ]);
        }

        return $this->screen->redirectBack($request, $this->selector->scope($request) ?? '');
    }
}
