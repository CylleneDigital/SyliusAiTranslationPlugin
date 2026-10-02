<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Estimation\GenerationCostEstimator;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiServiceInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\GenerationRefusal;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Input\GenerationRun;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The volume and the cost of a run, for the coordinates of the generation form: nothing is sent to the provider. */
#[IsGranted(Permission::Generate->value)]
final readonly class EstimateController
{
    public function __construct(
        private ChannelScopeResolver $scopeResolver,
        private GenerationRunValidator $runValidator,
        private GenerationCostEstimator $costEstimator,
        private TranslatorInterface&LocaleAwareInterface $translator,
    ) {
    }

    public function __invoke(#[MapQueryString] GenerationRun $run = new GenerationRun()): JsonResponse
    {
        $scope = $this->scopeResolver->resolveOrNull($run->scope);

        // A channel that no longer exists must not silently turn into a billed GLOBAL run.
        $refusal = null === $scope ? GenerationRefusal::UnknownScope : $this->runValidator->validate($run, $scope);

        if (null === $scope || null !== $refusal) {
            return new JsonResponse([
                'message' => $this->translator->trans(($refusal ?? GenerationRefusal::UnknownScope)->value, [], 'flashes'),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $estimate = $this->costEstimator->estimate(
            $run->catalogueOrNull(),
            $run->targetLocale,
            $run->sourceLocale,
            $run->providerOrNull(),
            !$run->all,
            $scope,
            $run->retryErrors,
        );

        if (0 === $estimate->keys) {
            return new JsonResponse(['message' => $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.estimate_nothing')]);
        }

        $parts = [
            $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.estimate_volume', [
                'keys' => $estimate->keys,
                'chars' => $estimate->characters,
                'chunks' => $estimate->chunks,
            ]),
        ];

        // The figures stop at the per-run cap, like the run itself: say what is left out.
        if ($estimate->isCapped()) {
            $parts[] = $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.estimate_capped', [
                'beyond' => $estimate->keysBeyondCap,
                'cap' => TranslationAiServiceInterface::MAX_KEYS_PER_RUN,
            ]);
        }

        if (null !== $estimate->inputTokens && null !== $estimate->outputTokens) {
            $parts[] = $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.estimate_tokens', [
                'input' => $estimate->inputTokens,
                'output' => $estimate->outputTokens,
            ]);
        }

        if ($estimate->isPriced()) {
            // Cents are enough from ten of them up; below, a cost would round to nothing.
            $formatter = new \NumberFormatter($this->translator->getLocale(), \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $estimate->cost >= 0.1 ? 2 : 4);
            // A reasoning model bills tokens nobody can count beforehand: its cost is a floor.
            $parts[] = $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.' . ($estimate->costIsLowerBound ? 'estimate_cost_at_least' : 'estimate_cost'), [
                'cost' => (string) $formatter->format((float) $estimate->cost),
                'currency' => (string) $estimate->currency,
            ]);
        }

        if (null !== $estimate->quotaRemaining && null !== $estimate->quotaLimit) {
            $parts[] = $this->translator->trans('cyllene_digital_sylius_ai_translation.ui.estimate_quota', [
                'remaining' => $estimate->quotaRemaining,
                'limit' => $estimate->quotaLimit,
            ]);
        }

        return new JsonResponse(['message' => implode(' · ', $parts)]);
    }
}
