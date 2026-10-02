<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Transfer\OverrideExporter;
use CylleneDigital\AiTranslationBundle\Transfer\ViewExporter;
use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\CatalogueRows;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\GridQuery;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\TransferMediaType;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Downloads the browser view carried by the query parameters — every listed row, in the
 * order of the screen, with its original and effective value, untranslated rows included. The output re-imports
 * through the baseline diff: only changed values become overrides. The full delta backup
 * lives in the engine's export-overrides command.
 */
#[IsGranted(Permission::View->value)]
final readonly class ExportController
{
    public function __construct(
        private Screen $screen,
        private OverrideExporter $exporter,
        private ChannelScopeResolver $scopeResolver,
        private CatalogueRows $catalogueRows,
        private ViewExporter $viewExporter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(string $format, Request $request): Response
    {
        if (!$this->exporter->supports($format)) {
            throw new NotFoundHttpException(\sprintf('Unknown export format "%s".', $format));
        }

        $scope = $this->scopeResolver->fromRequest($request);

        // The view as its screen read it: the same entries kept, sorted by the same code.
        $criteria = GridQuery::criteria($request->query->all());
        $sorting = GridQuery::sorting($request->query->all());
        $locale = $this->catalogueRows->resolveLocale($criteria, $scope);
        $entries = $this->catalogueRows->get($criteria, $scope, $sorting);

        if ([] === $entries) {
            $this->screen->flash($request, 'warning', 'cyllene_digital_sylius_ai_translation.ui.export_nothing');

            return $this->screen->redirectBack($request, $scope);
        }

        $rows = array_map(static fn (array $entry): array => [
            'catalogue' => $entry['catalogue'],
            'key' => $entry['key'],
            'original' => $entry['original'],
            'value' => $entry['override'] ?? $entry['inherited'] ?? $entry['original'],
        ], $entries);

        $response = new Response($this->viewExporter->export($format, $rows, $locale, $scope));
        $response->headers->set('Content-Type', TransferMediaType::of($format));
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            \sprintf('translations_%s%s_%s.%s', $locale, '' !== $scope ? '_' . $scope : '', $this->clock->now()->format('Y-m-d'), $format),
        ));

        return $response;
    }
}
