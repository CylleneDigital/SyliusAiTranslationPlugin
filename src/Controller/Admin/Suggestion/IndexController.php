<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Suggestion;

use CylleneDigital\AiTranslationBundle\Entity\GenerationLog;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Provider\TranslationProviderRegistry;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridChoiceProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridQuery;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridViewBuilder;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\SuggestionGrid;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * The suggestions screen. Like the catalogue one, everything here is computed for ONE
 * channel scope (`?scope=`, '' = global): the pending grid, its counters, and the
 * generation form — whose runs produce suggestions FOR that channel, approved into its
 * own overrides.
 *
 * The pending list is a Sylius grid ({@see SuggestionGrid}) built here rather than by the
 * resource controller: the page carries more than the grid (channel filter, generation
 * form, review modal) and every action redirects back to the channel being browsed.
 */
#[IsGranted(Permission::View->value)]
final readonly class IndexController
{
    /** How many runs of the journal the screen shows, the latest first. */
    private const int JOURNAL_LENGTH = 10;

    public function __construct(
        private Environment $twig,
        private ChannelScopeResolver $scopeResolver,
        private GridViewBuilder $gridViewBuilder,
        private TranslationSuggestionRepository $suggestionRepository,
        private TranslationProviderRegistry $providerRegistry,
        private TranslationManagerInterface $translationManager,
        private GridChoiceProvider $choices,
        private ManagerRegistry $doctrine,
        private ?string $defaultProvider,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $scope = $this->scopeResolver->fromRequest($request);
        // A channel narrows the locales to the ones it exposes.
        $locales = $this->scopeResolver->getLocales($scope) ?? $this->translationManager->getAvailableLocales();
        $query = $request->query->all();

        return new Response($this->twig->render('@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/index.html.twig', [
            'scope' => $scope,
            'scope_params' => ChannelScopeResolver::routeParameters($scope),
            'available_scopes' => $this->scopeResolver->getAvailableScopes(),
            'resources' => $this->gridViewBuilder->build(SuggestionGrid::NAME, $request),
            'pending_count' => $this->suggestionRepository->countPendingFiltered(null, null, null, $scope),
            'available_providers' => $this->providerRegistry->getNames(),
            'default_provider' => $this->defaultProvider,
            'available_catalogues' => $this->translationManager->getCatalogues(),
            'catalogue_groups' => $this->choices->getCatalogueChoicesByCategory(),
            'available_locales' => $locales,
            // Same cascade as the catalogue's reference column: the channel's default
            // locale when a channel is browsed, else the coverage default locale.
            'source_locale_default' => $this->scopeResolver->getReferenceLocale($scope),
            'filtered' => GridQuery::isFiltering($query),
            // Where a generation lands, and what "nothing for this language" looks like - for a
            // language the form above can generate: any other one is a search to adjust.
            'filtered_by_target_locale_only' => GridQuery::isFilteringOnlyBy($query, 'locale') && \in_array(GridQuery::criteria($query)['locale'], $locales, true),
            // Generation is asynchronous: its outcome, failures included, is read from the journal.
            'generation_logs' => $this->doctrine->getRepository(GenerationLog::class)->findBy(['scope' => $scope], ['id' => 'DESC'], self::JOURNAL_LENGTH),
        ]));
    }
}
