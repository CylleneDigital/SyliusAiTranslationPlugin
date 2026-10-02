<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * The rows a bulk action may act on: the still-pending suggestions among the posted
 * `ids[]` (see Sylius' bulk-delete.js), loaded in a single query. Non-numeric ids are
 * dropped, already-reviewed ones are filtered out (a stale page must not re-approve a
 * suggestion decided in the meantime), and rows from another channel are excluded: the
 * form's hidden scope input pins the batch to the page it was submitted from, so a
 * hand-posted ids list cannot reach other channels' rows.
 */
final readonly class PendingSuggestionSelector
{
    public function __construct(
        private ChannelScopeResolver $scopeResolver,
        private TranslationSuggestionRepository $suggestionRepository,
    ) {
    }

    /** @return string|null the posted scope as a known channel code ('' = global), null for an unknown one */
    public function scope(Request $request): ?string
    {
        return $this->scopeResolver->resolveOrNull($request->request->getString('scope'));
    }

    /**
     * @return list<TranslationSuggestion> none for an unknown scope
     */
    public function select(Request $request): array
    {
        $scope = $this->scope($request);

        if (null === $scope) {
            return [];
        }

        $ids = [];

        foreach ($request->request->all('ids') as $id) {
            if (\is_string($id) && ctype_digit($id)) {
                $ids[(int) $id] = true;
            }
        }

        if ([] === $ids) {
            return [];
        }

        return array_values(array_filter(
            $this->suggestionRepository->findBy(['id' => array_keys($ids), 'scope' => $scope]),
            static fn (TranslationSuggestion $suggestion): bool => $suggestion->isPending(),
        ));
    }
}
