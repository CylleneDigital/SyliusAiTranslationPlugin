<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Entity\TranslationOverride;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\OverrideRow;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Override\ManualEditOutcome;
use CylleneDigital\SyliusAiTranslationPlugin\Override\ManualOverrideEditor;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\View\PlaceholderDescription;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The edit modal's save: the rules are the editor's ({@see ManualOverrideEditor}), the wording of each outcome is here. */
#[IsGranted(Permission::Edit->value)]
final readonly class SaveController
{
    public function __construct(
        private Screen $screen,
        private RowScopeResolver $rowScope,
        private ManualOverrideEditor $editor,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] OverrideRow $row = new OverrideRow()): Response
    {
        $this->screen->validateCsrf($request);
        $scope = $this->rowScope->resolve($row);

        if (null === $scope) {
            $this->screen->flash($request, 'error', 'cyllene_digital_sylius_ai_translation.ui.translation_save_invalid');

            return $this->screen->redirectBack($request, $this->screen->scope($request));
        }

        $edit = $this->editor->edit($row->key, $row->catalogue, $row->locale, $scope, $row->value);

        [$type, $message] = match ($edit->outcome) {
            ManualEditOutcome::Saved => null === $edit->placeholders
                ? ['success', 'cyllene_digital_sylius_ai_translation.ui.translation_saved']
                : ['warning', [
                    'message' => 'cyllene_digital_sylius_ai_translation.ui.translation_saved_placeholder_warning',
                    'parameters' => ['placeholders' => PlaceholderDescription::describe($this->translator, $edit->placeholders['missing'], $edit->placeholders['unexpected'])],
                ]],
            ManualEditOutcome::Reverted => ['success', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.translation_reverted',
                'parameters' => ['baseline' => $edit->revertedToFile ? 'file' : 'inherited'],
            ]],
            ManualEditOutcome::Cleared => ['success', 'cyllene_digital_sylius_ai_translation.ui.translation_cleared'],
            ManualEditOutcome::Unchanged => ['info', 'cyllene_digital_sylius_ai_translation.ui.translation_unchanged'],
            ManualEditOutcome::EmptyIgnored => ['warning', 'cyllene_digital_sylius_ai_translation.ui.translation_empty_ignored'],
            ManualEditOutcome::EmptyRefused => ['error', 'cyllene_digital_sylius_ai_translation.ui.translation_empty_refused'],
            ManualEditOutcome::CatalogueUnknown => ['error', 'cyllene_digital_sylius_ai_translation.ui.translation_catalogue_unknown'],
            ManualEditOutcome::SyntaxInvalid => ['error', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.translation_save_syntax',
                'parameters' => ['issues' => implode(' · ', array_map(fn (string $code): string => $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.' . $code), $edit->syntaxIssues))],
            ]],
            ManualEditOutcome::KeyTooLong => ['error', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.translation_key_too_long',
                'parameters' => ['max' => TranslationOverride::MAX_KEY_LENGTH],
            ]],
            ManualEditOutcome::Invalid => ['error', 'cyllene_digital_sylius_ai_translation.ui.translation_save_invalid'],
        };

        $this->screen->flash($request, $type, $message);

        if ($edit->closedSuggestions > 0) {
            $this->screen->flash($request, 'info', [
                'message' => 'cyllene_digital_sylius_ai_translation.ui.translation_saved_suggestions_closed',
                'parameters' => ['count' => $edit->closedSuggestions],
            ]);
        }

        return $this->screen->redirectBack($request, $scope);
    }
}
