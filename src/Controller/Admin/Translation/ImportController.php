<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Translation;

use CylleneDigital\SyliusAiTranslationPlugin\Controller\Admin\Screen;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportStager;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportUploadLimit;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** The upload of an import: checked, previewed and staged - nothing is written before its confirmation. */
#[IsGranted(Permission::Import->value)]
final readonly class ImportController
{
    /**
     * Accepted upload extensions mapped to the content media types they may legitimately
     * carry. The {@see File} constraint checks BOTH: the client extension must be one of
     * these keys AND the file's actual sniffed media type must match — so the format is
     * not trusted on the client-supplied name alone. Plain-text variants are allowed
     * because a CSV (or a small XLIFF) export is often sniffed as text/plain.
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED_UPLOAD_EXTENSIONS = [
        'xlf' => ['application/x-xliff+xml', 'application/xml', 'text/xml', 'text/plain'],
        'xliff' => ['application/x-xliff+xml', 'application/xml', 'text/xml', 'text/plain'],
        'csv' => ['text/csv', 'application/csv', 'text/plain'],
    ];

    public function __construct(
        private Screen $screen,
        private ImportStager $stager,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(Request $request, #[MapUploadedFile] ?UploadedFile $file = null): Response
    {
        $scope = $this->screen->scope($request);
        $rejected = [
            'message' => 'cyllene_digital_sylius_ai_translation.ui.import_file_rejected',
            'parameters' => ['max' => ImportUploadLimit::megabytes()],
        ];

        // Before the token check: PHP emptied the form, the token went with the file. Nothing
        // is read nor written on this path; like any refused upload, it withdraws the import
        // that was waiting - the most a forged request could do here.
        if (ImportUploadLimit::wasExceededBy($request)) {
            $this->stager->clear($request);
            $this->screen->flash($request, 'error', $rejected);

            return $this->screen->redirectBack($request, $scope);
        }

        $this->screen->validateCsrf($request);

        // A new attempt, even one refused below, withdraws the import that was waiting for
        // its confirmation: its card must not stay on screen under another file's refusal.
        $this->stager->clear($request);

        if (null === $file) {
            $this->screen->flash($request, 'error', 'cyllene_digital_sylius_ai_translation.ui.import_file_missing');

            return $this->screen->redirectBack($request, $scope);
        }

        // A file PHP itself turned down was not forgotten - and each reason has its remedy: a
        // smaller file, another try, or the server's administrator.
        if (!$file->isValid()) {
            $this->screen->flash($request, 'error', match ($file->getError()) {
                \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => $rejected,
                \UPLOAD_ERR_PARTIAL => 'cyllene_digital_sylius_ai_translation.ui.import_upload_interrupted',
                default => 'cyllene_digital_sylius_ai_translation.ui.import_upload_server_error',
            });

            return $this->screen->redirectBack($request, $scope);
        }

        // Server-side size cap + extension/media-type check BEFORE the whole file is read
        // in memory to parse it — the client name and MIME header are never trusted alone.
        $violations = $this->validator->validate($file, [
            new File(
                maxSize: ImportUploadLimit::bytes(),
                extensions: self::ALLOWED_UPLOAD_EXTENSIONS,
            ),
        ]);

        if (\count($violations) > 0) {
            $this->screen->flash($request, 'error', $rejected);

            return $this->screen->redirectBack($request, $scope);
        }

        // Nothing is written yet: the stager parks the content in the import staging cache pool and
        // the preview in the session, and the dashboard shows the confirmation card.
        $stage = $this->stager->stage($request, $file);

        if (null !== $stage->refusal()) {
            $this->screen->flash($request, 'error', $stage->refusal()->value);

            // The skipped entries, each with where it is in the file, its key and why: the
            // file can be fixed instead of searched.
            if ([] !== $stage->issues()) {
                $this->screen->flash($request, 'warning', [
                    'message' => 'cyllene_digital_sylius_ai_translation.ui.import_skipped_entries',
                    'parameters' => ['entries' => implode(' ; ', $stage->issues())],
                ]);
            }
        }

        return $this->screen->redirectBack($request, $scope);
    }
}
