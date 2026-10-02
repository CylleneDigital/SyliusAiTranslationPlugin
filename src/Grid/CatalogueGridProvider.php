<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Sylius\Component\Grid\Data\DataProviderInterface;
use Sylius\Component\Grid\Definition\Grid;
use Sylius\Component\Grid\Parameters;

/**
 * Feeds the catalogue grid: the rows are computed (translation files ∪ overrides ∪
 * reference locales), not queried: CatalogueRows applies the criteria and the sorting, and
 * this provider replaces the Doctrine driver and paginates what it returns - Pagerfanta
 * over the array, sized by the GridViewBuilder as usual.
 */
final readonly class CatalogueGridProvider implements DataProviderInterface
{
    /** The id a grid names this provider by: Sylius looks it up among the tagged data providers. */
    public const string SERVICE_ID = 'cyllene_digital_sylius_ai_translation.grid.catalogue_provider';

    public function __construct(
        private CatalogueRows $rows,
        private ChannelScopeResolver $scopeResolver,
    ) {
    }

    /**
     * @return Pagerfanta<array{key: string, catalogue: string, locale: string, scope: string, original: ?string, override: ?string, hasOverride: bool, inherited: ?string, inheritedFrom: array{locale: string, scope: string}|null, reference: ?string, referenceLocale: ?string, missing: bool}>
     */
    #[\Override]
    public function getData(Grid $grid, Parameters $parameters): Pagerfanta
    {
        $scope = $this->scopeResolver->fromMainRequest();

        /** @var array<string, mixed> $criteria */
        $criteria = (array) $parameters->get('criteria', []);
        $entries = $this->rows->get($criteria, $scope, (array) $parameters->get('sorting', $grid->getSorting()));

        // A page past the end - the last row of a filtered page just left the filter - shows
        // the last page instead of failing.
        return (new Pagerfanta(new ArrayAdapter($entries)))->setNormalizeOutOfRangePages(true);
    }
}
