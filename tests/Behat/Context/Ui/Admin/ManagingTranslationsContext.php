<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\Translation\IndexPage as TranslationIndexPage;
use Webmozart\Assert\Assert;

/** The catalogue screen: browsing the translations, editing one by hand, importing a file. */
final class ManagingTranslationsContext implements Context
{
    public function __construct(
        private readonly TranslationIndexPage $cataloguePage,
        private readonly TranslationManagerInterface $translationManager,
    ) {
    }

    #[When('I browse the translations')]
    #[When('I browse the translations in :locale')]
    #[When('I try to browse the translations')]
    public function iBrowseTheTranslations(?string $locale = null): void
    {
        $this->cataloguePage->tryToOpen(null !== $locale ? ['criteria' => ['locale' => $locale]] : []);
    }

    #[When('I browse the untranslated keys in :locale')]
    public function iBrowseTheUntranslatedKeysIn(string $locale): void
    {
        $this->cataloguePage->open(['criteria' => ['locale' => $locale, 'missing' => 'true']]);
    }

    #[When('I choose the :channelCode channel')]
    public function iChooseTheChannel(string $channelCode): void
    {
        $this->cataloguePage->chooseChannel($channelCode);
    }

    #[When('I edit the :key key')]
    public function iEditTheKey(string $key): void
    {
        $this->cataloguePage->openEditModal($key);
    }

    #[When('I set its value to :value')]
    public function iSetItsValueTo(string $value): void
    {
        $this->cataloguePage->typeValue($value);
        $this->cataloguePage->saveEdit();
    }

    #[When('I restore its original value')]
    public function iRestoreItsOriginalValue(): void
    {
        $this->cataloguePage->restoreOriginal();
    }

    #[When('I import the :file file')]
    public function iImportTheFile(string $file): void
    {
        $this->cataloguePage->import(\dirname(__DIR__, 3) . '/Resources/fixtures/' . $file);
    }

    #[When('I confirm the import')]
    public function iConfirmTheImport(): void
    {
        $this->cataloguePage->confirmImport();
    }

    #[When('I cancel the import')]
    public function iCancelTheImport(): void
    {
        $this->cataloguePage->cancelImport();
    }

    #[When('I purge the orphan overrides')]
    public function iPurgeTheOrphanOverrides(): void
    {
        $this->cataloguePage->purgeOrphans();
    }

    #[When('I purge the orphan overrides and confirm')]
    public function iPurgeTheOrphanOverridesAndConfirm(): void
    {
        $this->cataloguePage->answerConfirmations(true);
        $this->cataloguePage->purgeOrphans();
    }

    #[When('I purge the orphan overrides but change my mind')]
    public function iPurgeTheOrphanOverridesButChangeMyMind(): void
    {
        $this->cataloguePage->answerConfirmations(false);
        $this->cataloguePage->purgeOrphans();
    }

    #[Then('I should be warned about :count orphan override(s)')]
    public function iShouldBeWarnedAboutOrphanOverrides(int $count): void
    {
        Assert::contains($this->cataloguePage->orphanNotice(), \sprintf('%d orphan override', $count));
    }

    #[Then('I should not be offered to purge them')]
    public function iShouldNotBeOfferedToPurgeThem(): void
    {
        Assert::false($this->cataloguePage->canPurgeOrphans());
    }

    #[Then('I should see the :key key')]
    public function iShouldSeeTheKey(string $key): void
    {
        Assert::true($this->cataloguePage->hasRow($key), \sprintf('The "%s" key should be listed.', $key));
    }

    #[Then('I should not see the :key key')]
    public function iShouldNotSeeTheKey(string $key): void
    {
        Assert::false($this->cataloguePage->hasRow($key), \sprintf('The "%s" key should not be listed.', $key));
    }

    #[Then('the :key key should be untranslated')]
    public function theKeyShouldBeUntranslated(string $key): void
    {
        Assert::true($this->cataloguePage->isUntranslated($key));
    }

    #[Then('the :key key should show :value')]
    public function theKeyShouldShow(string $key, string $value): void
    {
        Assert::contains($this->cataloguePage->rowText($key), $value);
    }

    #[Then('the edit modal should be about the :key key')]
    public function theEditModalShouldBeAboutTheKey(string $key): void
    {
        Assert::same($this->cataloguePage->editedKey(), $key);
    }

    #[Then('I should not be offered to restore its original value')]
    public function iShouldNotBeOfferedToRestoreItsOriginalValue(): void
    {
        Assert::false($this->cataloguePage->canRestoreOriginal());
    }

    #[Then('the :key key of the :catalogue catalogue should be overridden with :value in :locale')]
    public function theKeyShouldBeOverriddenWith(string $key, string $catalogue, string $value, string $locale): void
    {
        Assert::same($this->translationManager->getOverrideValue($key, $catalogue, $locale), $value);
    }

    #[Then('the :key key of the :catalogue catalogue should not be overridden in :locale')]
    public function theKeyShouldNotBeOverridden(string $key, string $catalogue, string $locale): void
    {
        Assert::null($this->translationManager->getOverrideValue($key, $catalogue, $locale));
    }

    #[Then('I should be shown what the import would write')]
    public function iShouldBeShownWhatTheImportWouldWrite(): void
    {
        Assert::true($this->cataloguePage->hasImportPreview());
    }

    #[Then('I should be told :message')]
    public function iShouldBeTold(string $message): void
    {
        Assert::true(
            $this->cataloguePage->isTold($message),
            \sprintf('Expected a flash saying "%s", got: %s', $message, implode(' | ', $this->cataloguePage->flashes())),
        );
    }

    #[Then('I should not be allowed to see them')]
    public function iShouldNotBeAllowedToSeeThem(): void
    {
        Assert::true($this->cataloguePage->isForbidden());
    }

    #[Then('I should be able to read them but not to edit them')]
    public function iShouldBeAbleToReadThemButNotToEditThem(): void
    {
        Assert::true($this->cataloguePage->hasRow('app.dashboard'));
        Assert::false($this->cataloguePage->canEdit());
        Assert::false($this->cataloguePage->canImport());
    }

    #[Then('the filters should keep the :channelCode channel')]
    public function theFiltersShouldKeepTheChannel(string $channelCode): void
    {
        Assert::true($this->cataloguePage->filtersCarryChannel($channelCode));
    }
}
