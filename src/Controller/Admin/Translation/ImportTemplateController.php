<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\AiTranslationBundle\Transfer\OverrideImportTemplates;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\TransferMediaType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A ready-to-fill sample file in the requested format — the import modal links to it so
 * the expected structure is one click away, even on a fresh install where exporting a
 * real file as a starting point is impossible.
 */
#[IsGranted(Permission::View->value)]
final readonly class ImportTemplateController
{
    public function __invoke(string $format): Response
    {
        $content = OverrideImportTemplates::get($format);

        if (null === $content) {
            throw new NotFoundHttpException(\sprintf('Unknown template format "%s".', $format));
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', TransferMediaType::of($format));
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            \sprintf('translation_overrides_template.%s', $format),
        ));

        return $response;
    }
}
