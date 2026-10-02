<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Coverage\CoverageCalculator;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\AiTranslationBundle\Transfer\OverrideExporter;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\CatalogueGrid;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridViewBuilder;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportStager;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportUploadLimit;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * The catalogue screen, computed for ONE channel scope (`?scope=`, '' = global): the
 * coverage as that channel's visitors see it (its overrides over the inherited global
 * ones), the "modified" counters restricted to its own overrides, the browser on its
 * values, and the locales narrowed to the ones the channel exposes.
 */
#[IsGranted(Permission::View->value)]
final readonly class IndexController
{
    public function __construct(
        private Environment $twig,
        private ChannelScopeResolver $scopeResolver,
        private TranslationManagerInterface $translationManager,
        private TranslationSuggestionRepository $suggestionRepository,
        private CoverageCalculator $coverageCalculator,
        private GridViewBuilder $gridViewBuilder,
        private ImportStager $stager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $scope = $this->scopeResolver->fromRequest($request);
        $locales = $this->scopeResolver->getLocales($scope);

        return new Response($this->twig->render('@CylleneDigitalSyliusAiTranslationPlugin/admin/translation/index.html.twig', [
            'scope' => $scope,
            'scope_params' => ChannelScopeResolver::routeParameters($scope),
            'available_scopes' => $this->scopeResolver->getAvailableScopes(),
            'overrides_by_locale' => $this->getOverridesByLocale($scope, $locales),
            'pending_suggestion_count' => $this->suggestionRepository->countPendingFiltered(null, null, null, $scope),
            'export_formats' => OverrideExporter::FORMATS,
            'import_max_megabytes' => ImportUploadLimit::megabytes(),
            'coverage' => $this->coverageCalculator->getReport($scope, $locales),
            'resources' => $this->gridViewBuilder->build(CatalogueGrid::NAME, $request),
            'orphan_count' => $this->translationManager->countOrphanOverrides(),
            // false, not null: the twig-hooks DataBag treats a null value as a missing key.
            'pending_import' => $this->stager->stagedPreview($request) ?? false,
        ]));
    }

    /**
     * Override count per locale for one scope — the global overrides, or a channel's OWN
     * ones (what was customised for it, not what it inherits) — merged onto the locale
     * list so locales with no override still appear with 0. An orphan override's locale
     * (one the host no longer exposes) is intentionally not added as a column.
     *
     * @param list<string>|null $locales the locales to report (null = every available locale)
     *
     * @return array<string, int>
     */
    private function getOverridesByLocale(string $scope, ?array $locales): array
    {
        $result = array_fill_keys($locales ?? $this->translationManager->getAvailableLocales(), 0);

        return array_intersect_key($this->translationManager->countOverridesPerLocale($scope), $result) + $result;
    }
}
