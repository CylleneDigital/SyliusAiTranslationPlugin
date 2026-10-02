<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use Pagerfanta\Pagerfanta;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Sylius\Component\Grid\View\GridViewInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The grid view of a request, for pages whose controller owns the page (no resource
 * controller): Sylius applies the filters (`criteria[...]`) and the sorting, this
 * builder the page size — the resource controller's usual share of the job (`limit`
 * clamped to the grid's limits, first limit by default) — and the page.
 */
final readonly class GridViewBuilder
{
    public function __construct(
        private GridProviderInterface $gridProvider,
        private GridViewFactoryInterface $gridViewFactory,
    ) {
    }

    public function build(string $gridName, Request $request): GridViewInterface
    {
        $grid = $this->gridProvider->get($gridName);
        $parameters = $request->query->all();

        $criteria = GridQuery::criteria($parameters);
        // Sylius skips a field of the grid that is not sortable without a word: the rows would
        // come out sorted by nothing. A field the grid does not have is its to refuse.
        $sorting = array_filter(
            GridQuery::sorting($parameters),
            static fn (string $field): bool => !$grid->hasField($field) || null !== $grid->getField($field)->getSortable(),
            \ARRAY_FILTER_USE_KEY,
        );

        // The sort key rarely tells every row apart: without a last word, the database may
        // deal the same row to two pages.
        if ($grid->hasField('id')) {
            $sorting = ([] !== $sorting ? $sorting : $grid->getSorting()) + ['id' => 'asc'];
        }

        // Nothing usable left is nothing asked: the grid's own sorting applies again.
        unset($parameters['criteria'], $parameters['sorting']);
        $parameters += array_filter(['criteria' => $criteria, 'sorting' => $sorting]);

        $view = $this->gridViewFactory->create($grid, new Parameters($parameters));

        $data = $view->getData();

        if ($data instanceof Pagerfanta) {
            $limits = $grid->getLimits();
            $limit = $request->query->getInt('limit', (int) (reset($limits) ?: 25));
            $data->setMaxPerPage(max(1, min($limit, [] !== $limits ? max($limits) : $limit)));
            $data->setCurrentPage(max(1, $request->query->getInt('page', 1)));
            // Runs the query now — the templates only walk the page.
            $data->getCurrentPageResults();
        }

        return $view;
    }
}
