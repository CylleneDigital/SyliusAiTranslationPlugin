<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationValueInvalidException;
use CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderMismatchException;
use CylleneDigital\AiTranslationBundle\Suggestion\SuggestionAlreadyReviewedException;
use CylleneDigital\AiTranslationBundle\Suggestion\SuggestionValueMissingException;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiServiceInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\View\PlaceholderDescription;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Applies the suggestion — optionally with a reviewer-edited value (review modal). */
#[IsGranted(Permission::Review->value)]
final readonly class ApproveController
{
    public function __construct(
        private Screen $screen,
        private TranslationAiServiceInterface $aiService,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, #[MapEntity(id: 'id')] TranslationSuggestion $suggestion): Response
    {
        $this->screen->validateCsrf($request);

        // A stale page acting on a suggestion reviewed meanwhile.
        if (!$suggestion->isPending()) {
            $this->screen->flash($request, 'warning', 'cyllene_digital_sylius_ai_translation.ui.suggestion_already_reviewed');

            return $this->screen->redirectBack($request, $suggestion->getScope());
        }

        $editedValue = $request->request->get('value');

        // A textarea posts "\r\n", the catalogues hold "\n": an untouched multi-line value
        // must stay the suggested one, not count as an edit.
        $editedValue = \is_string($editedValue) ? str_replace("\r\n", "\n", $editedValue) : null;

        try {
            $this->aiService->approve($suggestion, $editedValue);

            // An approval writes no override for a value the entry already shows: the message
            // says what it actually did, as the engine recorded it on the approved row.
            $this->screen->flash($request, 'success', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.suggestion_approved',
                'parameters' => ['change' => RecordedOverrideChange::of($suggestion)->value],
            ]);
        } catch (SuggestionValueMissingException) {
            // An errored row without a hand-written value: nothing to apply.
            $this->screen->flash($request, 'error', 'cyllene_digital_sylius_ai_translation.ui.suggestion_value_required');
        } catch (SuggestionAlreadyReviewedException) {
            // Reviewed in another window (or by someone else) between the page and the click.
            $this->screen->flash($request, 'warning', 'cyllene_digital_sylius_ai_translation.ui.suggestion_already_reviewed');
        } catch (PlaceholderMismatchException $e) {
            // The engine says which way each placeholder differs: lost, or added.
            $this->screen->flash($request, 'error', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.suggestion_placeholder_mismatch',
                'parameters' => ['placeholders' => PlaceholderDescription::describe($this->translator, $e->missing, $e->unexpected)],
            ]);
        } catch (TranslationValueInvalidException $e) {
            // The value's syntax does not match the catalogue's declared format.
            $this->screen->flash($request, 'error', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.suggestion_syntax_invalid',
                'parameters' => ['issues' => implode(' · ', array_map(fn (string $code): string => $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.' . $code), $e->issues))],
            ]);
        }

        return $this->screen->redirectBack($request, $suggestion->getScope());
    }
}
