<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Grid;

use Sylius\Bundle\GridBundle\Renderer\TwigBulkActionGridRenderer;
use Sylius\Bundle\ResourceBundle\Grid\View\ResourceGridView;
use Sylius\Component\Grid\Definition\Action;
use Sylius\Component\Grid\Renderer\BulkActionGridRendererInterface;
use Sylius\Component\Grid\View\GridViewInterface;
use Twig\Environment;

/**
 * The resource bundle's bulk-action renderer asserts a ResourceGridView — unlike its
 * row-action counterpart, which falls back to the plain grid renderer for a bare
 * GridView. The suggestions screen builds a bare GridView (its controller owns the page,
 * not the resource controller), so this decorator, outermost in the chain, restores that
 * fallback: resource views go to the decorated renderer, plain views to the grid
 * bundle's own Twig renderer with the same template map.
 */
final readonly class BulkActionGridRenderer implements BulkActionGridRendererInterface
{
    private TwigBulkActionGridRenderer $plainRenderer;

    /**
     * @param array<string, string> $bulkActionTemplates
     */
    public function __construct(
        private BulkActionGridRendererInterface $inner,
        Environment $twig,
        array $bulkActionTemplates,
    ) {
        $this->plainRenderer = new TwigBulkActionGridRenderer($twig, $bulkActionTemplates);
    }

    #[\Override]
    public function renderBulkAction(GridViewInterface $gridView, Action $bulkAction, $data = null): string
    {
        if ($gridView instanceof ResourceGridView) {
            return $this->inner->renderBulkAction($gridView, $bulkAction, $data);
        }

        return $this->plainRenderer->renderBulkAction($gridView, $bulkAction, $data);
    }
}
