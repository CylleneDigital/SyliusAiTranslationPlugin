<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Message\GenerateSuggestionsMessage;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\GenerationRefusal;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\GenerationRun;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Queues the generation runs of the form: billed calls to the AI provider, one per catalogue. */
#[IsGranted(Permission::Generate->value)]
final readonly class GenerateController
{
    public function __construct(
        private Screen $screen,
        private ChannelScopeResolver $scopeResolver,
        private GenerationRunValidator $runValidator,
        private TranslationManagerInterface $translationManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Request $request, #[MapRequestPayload] GenerationRun $run = new GenerationRun()): Response
    {
        $this->screen->validateCsrf($request);

        $scope = $this->scopeResolver->resolveOrNull($run->scope);

        // A channel that no longer exists must not silently turn into a billed GLOBAL run.
        if (null === $scope) {
            $this->screen->flash($request, 'error', GenerationRefusal::UnknownScope->value);

            return $this->screen->redirectToIndex('');
        }

        // Same coordinates the estimate validated: a hand-posted locale the host does not
        // expose would open a billed run that sees EVERY key as missing.
        if (null !== $refusal = $this->runValidator->validate($run, $scope)) {
            $this->screen->flash($request, 'error', $refusal->value);

            return $this->screen->redirectToIndex($scope);
        }

        // A blank catalogue = one billed run per catalogue; the screen asks for an explicit
        // confirmation before submitting that (and each run is capped by the engine).
        $catalogues = null !== $run->catalogueOrNull() ? [$run->catalogue] : $this->translationManager->getCatalogueIdentifiers();

        foreach ($catalogues as $catalogue) {
            $this->messageBus->dispatch(new GenerateSuggestionsMessage(
                $catalogue,
                $run->targetLocale,
                $run->sourceLocale,
                $run->providerOrNull(),
                !$run->all,
                $scope,
                $run->retryErrors,
            ));
        }

        $this->screen->flash($request, 'success', [
            'message' => 'cyllene_digital_sylius_ai_translation.ui.generation_queued',
            'parameters' => ['count' => \count($catalogues)],
        ]);

        return $this->screen->redirectToIndex($scope, ['criteria' => ['locale' => $run->targetLocale]]);
    }
}
