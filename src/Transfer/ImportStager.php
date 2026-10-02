<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Transfer;

use CylleneDigital\AiTranslationBundle\Transfer\OverrideImporter;
use CylleneDigital\AiTranslationBundle\Transfer\OverrideImportIssue;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * The staged (awaiting-confirmation) state of an override import: the uploaded file's
 * content parked in a cache pool, the preview parked in the session — confirming consumes
 * the stage and the route writes the overrides, a cancel or a new upload clears it (the
 * controller withdraws the waiting import before anything else, see {@see clear()}).
 *
 * The stage is per user (the session) and outlives the upload request. The content goes
 * to a cache pool rather than a local temp file: the confirmation may be served by another
 * node, and a stage nobody confirms expires by itself.
 */
final readonly class ImportStager
{
    /** Session key of the staged import (cache token + preview). */
    private const string PENDING_KEY = 'cyllene_digital_sylius_ai_translation_pending_import';

    public function __construct(
        private OverrideImporter $importer,
        private CacheItemPoolInterface $pool,
    ) {
    }

    /**
     * Previews the upload and parks it for a later confirmation. Nothing is written yet —
     * the dashboard shows the confirmation card. The caller withdraws the import that was
     * waiting ({@see clear()}): it does so for the uploads it refuses before this one, too.
     */
    public function stage(Request $request, UploadedFile $file): ImportStage
    {
        $format = $this->importer->guessFormat($file->getClientOriginalName());

        if (null === $format) {
            return ImportStage::refused(ImportRefusal::UnsupportedFormat);
        }

        $content = (string) file_get_contents($file->getPathname());

        try {
            $preview = $this->importer->preview($content, $format);
        } catch (\InvalidArgumentException) {
            return ImportStage::refused(ImportRefusal::InvalidFile);
        }

        // Setting values back to their baseline removes overrides: that alone is work. A
        // file whose every entry is skipped says which ones and why.
        if (0 === $preview->importable && 0 === $preview->reverted) {
            return ImportStage::refused(ImportRefusal::NothingToImport, OverrideImportIssue::summarize($preview->issues));
        }

        $token = bin2hex(random_bytes(16));

        if (!$this->pool->save($this->pool->getItem($token)->set($content))) {
            return ImportStage::refused(ImportRefusal::StagingFailed);
        }

        $request->getSession()->set(self::PENDING_KEY, [
            'token' => $token,
            'format' => $format,
            'name' => $file->getClientOriginalName(),
            'importable' => $preview->importable,
            'skipped' => $preview->skipped,
            'unchanged' => $preview->unchanged,
            'reverted' => $preview->reverted,
            'byLocale' => $preview->byLocale,
            // Each skipped entry — where it is in the file, its key, why — so the file can
            // be fixed instead of searched (the first ten, then how many more).
            'issues' => OverrideImportIssue::summarize($preview->issues),
        ]);

        return ImportStage::accepted();
    }

    /**
     * The staged import's content and format — or null when nothing is staged (or the
     * content expired or was evicted from the pool). Consumes the stage either way.
     *
     * @return array{content: string, format: string}|null
     */
    public function take(Request $request): ?array
    {
        $staged = $this->pop($request);

        if (null === $staged) {
            return null;
        }

        $content = $this->pool->getItem($staged['token'])->get();
        $this->pool->deleteItem($staged['token']);

        return \is_string($content) ? ['content' => $content, 'format' => $staged['format']] : null;
    }

    /**
     * The staged import's preview for the dashboard's confirmation card — null when
     * nothing is staged.
     *
     * @return array{name: string, importable: int, skipped: int, unchanged: int, reverted: int, byLocale: array<string, int>, issues: list<string>}|null
     */
    public function stagedPreview(Request $request): ?array
    {
        /** @var array{token: string, format: string, name: string, importable: int, skipped: int, unchanged: int, reverted: int, byLocale: array<string, int>, issues: list<string>}|null $pending */
        $pending = $request->getSession()->get(self::PENDING_KEY);

        if (!\is_array($pending)) {
            return null;
        }

        // The cache token and the format are the stager's business, not the template's.
        unset($pending['token'], $pending['format']);

        return $pending;
    }

    /** Drops the staged import (and its content) without writing anything. */
    public function clear(Request $request): void
    {
        $staged = $this->pop($request);

        if (null !== $staged) {
            $this->pool->deleteItem($staged['token']);
        }
    }

    /**
     * Pops the session's stage, validated — null when there is none or its shape is not
     * what stage() stored.
     *
     * @return array{token: string, format: string}|null
     */
    private function pop(Request $request): ?array
    {
        $session = $request->getSession();
        $pending = $session->get(self::PENDING_KEY);
        $session->remove(self::PENDING_KEY);

        if (!\is_array($pending) || !\is_string($pending['token'] ?? null) || !\is_string($pending['format'] ?? null)) {
            return null;
        }

        return ['token' => $pending['token'], 'format' => $pending['format']];
    }
}
