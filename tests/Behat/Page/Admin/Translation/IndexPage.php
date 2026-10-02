<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\Translation;

use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\AdminScreenPage;

/** The catalogue screen: browsing, the edit modal, the import and the orphan purge. */
final class IndexPage extends AdminScreenPage
{
    public function getRouteName(): string
    {
        return 'cyllene_digital_sylius_ai_translation_admin_translation_index';
    }

    public function isUntranslated(string $key): bool
    {
        return str_contains(mb_strtolower($this->rowText($key)), 'untranslated');
    }

    public function canEdit(): bool
    {
        return $this->shows('[data-bs-target="#translation-edit-modal"]');
    }

    public function canImport(): bool
    {
        return $this->shows('#import-overrides-modal');
    }

    /** Opens the edit modal from the row's button, as a click in the browser does. */
    public function openEditModal(string $key): void
    {
        $this->press('button[data-bs-target="#translation-edit-modal"]', $this->row($key));
        $this->waitForModal('translation-edit-modal');
    }

    public function editedKey(): string
    {
        $value = $this->waitForModal('translation-edit-modal')->find('css', 'input[name="key"]')?->getValue();

        return \is_string($value) ? $value : '';
    }

    public function typeValue(string $value): void
    {
        $this->getDocument()->fillField('translation-edit-value', $value);
    }

    public function saveEdit(): void
    {
        $this->press('button.btn-primary[type="submit"]', $this->waitForModal('translation-edit-modal'));
    }

    public function restoreOriginal(): void
    {
        $this->press('[data-ait-admin-edit-modal-target="reset"]', $this->waitForModal('translation-edit-modal'));
    }

    public function canRestoreOriginal(): bool
    {
        return $this->waitForModal('translation-edit-modal')->find('css', '[data-ait-admin-edit-modal-target="reset"]')?->isVisible() ?? false;
    }

    public function orphanNotice(): string
    {
        return $this->getDocument()->find('css', '[data-test-orphan-overrides]')?->getText() ?? '';
    }

    public function canPurgeOrphans(): bool
    {
        return $this->shows('form[action$="/purge-orphans"]');
    }

    public function purgeOrphans(): void
    {
        $this->press('form[action$="/purge-orphans"] button[type="submit"]');
    }

    /** In a real browser, the purge asks through a native dialog: answer it ahead, it would block the click. */
    public function answerConfirmations(bool $accept): void
    {
        $this->getSession()->executeScript(\sprintf('window.confirm = () => %s;', $accept ? 'true' : 'false'));
    }

    public function import(string $path): void
    {
        $this->getDocument()->attachFileToField('import_file', $path);
        $this->press('form[action$="/translations/import"] button[type="submit"]');
    }

    public function hasImportPreview(): bool
    {
        return $this->shows('form[action$="/import/confirm"]');
    }

    public function confirmImport(): void
    {
        $this->press('form[action$="/import/confirm"] button[type="submit"]');
    }

    public function cancelImport(): void
    {
        $this->press('form[action$="/import/cancel"] button[type="submit"]');
    }
}
