<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Page\Admin\Suggestion\IndexPage as SuggestionIndexPage;
use Webmozart\Assert\Assert;

/** The suggestions screen: asking for a generation, deciding on what it proposed. */
final class ReviewingSuggestionsContext implements Context
{
    public function __construct(
        private readonly SuggestionIndexPage $suggestionsPage,
        private readonly TranslationManagerInterface $translationManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[When('I browse the AI suggestions')]
    #[When('I try to browse the AI suggestions')]
    public function iBrowseTheAiSuggestions(): void
    {
        $this->suggestionsPage->tryToOpen();
    }

    #[When('I browse the AI suggestions of the :channelCode channel')]
    public function iBrowseTheAiSuggestionsOfTheChannel(string $channelCode): void
    {
        $this->suggestionsPage->open(['scope' => $channelCode]);
    }

    #[When('I approve the suggestion for the :key key')]
    public function iApproveTheSuggestionForTheKey(string $key): void
    {
        $this->suggestionsPage->approve($key);
    }

    #[When('I reject the suggestion for the :key key')]
    public function iRejectTheSuggestionForTheKey(string $key): void
    {
        $this->suggestionsPage->reject($key);
    }

    #[When('I review the suggestion for the :key key')]
    public function iReviewTheSuggestionForTheKey(string $key): void
    {
        $this->suggestionsPage->openReviewModal($key);
    }

    #[When('I approve it as :value')]
    public function iApproveItAs(string $value): void
    {
        $this->suggestionsPage->typeSuggestedValue($value);
        $this->suggestionsPage->approveFromModal();
    }

    #[When('I empty its value')]
    public function iEmptyItsValue(): void
    {
        $this->suggestionsPage->typeSuggestedValue('');
    }

    #[When('I approve every suggestion at once')]
    public function iApproveEverySuggestionAtOnce(): void
    {
        $this->suggestionsPage->selectEveryRow();
        $this->suggestionsPage->approveSelection();
    }

    #[When('I ask for a generation of the :catalogue catalogue into :locale')]
    public function iAskForAGenerationOfTheCatalogueInto(string $catalogue, string $locale): void
    {
        $this->suggestionsPage->chooseTargetLocale($locale);
        $this->suggestionsPage->chooseCatalogue($catalogue);
        $this->suggestionsPage->generate();
    }

    #[When('I ask for a generation of every catalogue into :locale')]
    public function iAskForAGenerationOfEveryCatalogueInto(string $locale): void
    {
        $this->suggestionsPage->chooseTargetLocale($locale);
        $this->suggestionsPage->chooseCatalogue('');
        $this->suggestionsPage->generate();
    }

    #[When('I confirm it')]
    public function iConfirmIt(): void
    {
        $this->suggestionsPage->confirmGenerationOnEveryCatalogue();
    }

    #[When('I ask what generating the :catalogue catalogue into :locale would cost')]
    public function iAskWhatGeneratingWouldCost(string $catalogue, string $locale): void
    {
        $this->suggestionsPage->chooseTargetLocale($locale);
        $this->suggestionsPage->chooseCatalogue($catalogue);
        $this->suggestionsPage->askForEstimate();
    }

    #[When('I switch to the :provider provider')]
    public function iSwitchToTheProvider(string $provider): void
    {
        $this->suggestionsPage->chooseProvider($provider);
    }

    #[Then('I should see a suggestion for the :key key')]
    public function iShouldSeeASuggestionForTheKey(string $key): void
    {
        Assert::true($this->suggestionsPage->hasRow($key));
    }

    #[Then('there should be no suggestion left for the :key key')]
    public function thereShouldBeNoSuggestionLeftForTheKey(string $key): void
    {
        $this->entityManager->clear();

        $pending = array_filter(
            $this->entityManager->getRepository(TranslationSuggestion::class)->findBy(['key' => $key]),
            static fn (TranslationSuggestion $suggestion): bool => $suggestion->isPending(),
        );

        Assert::isEmpty($pending);
    }

    #[Then('the :key key of the :catalogue catalogue should now read :value in :locale')]
    public function theKeyShouldNowRead(string $key, string $catalogue, string $value, string $locale): void
    {
        Assert::same($this->translationManager->getOverrideValue($key, $catalogue, $locale), $value);
    }

    #[Then('I should be asked to confirm a generation on every catalogue')]
    public function iShouldBeAskedToConfirmAGenerationOnEveryCatalogue(): void
    {
        Assert::true($this->suggestionsPage->isAskedToConfirmEveryCatalogue());
    }

    #[Then('I should be given an estimate of the keys to translate')]
    public function iShouldBeGivenAnEstimateOfTheKeysToTranslate(): void
    {
        Assert::regex($this->suggestionsPage->estimate(), '/\d+ keys?/');
    }

    #[Then('the estimate should be cleared')]
    public function theEstimateShouldBeCleared(): void
    {
        Assert::same($this->suggestionsPage->estimate(awaited: false), '');
    }

    #[Then('I should not be able to approve it')]
    public function iShouldNotBeAbleToApproveIt(): void
    {
        Assert::false($this->suggestionsPage->canApproveFromModal());
    }

    #[Then('the journal should start with the failure :error')]
    public function theJournalShouldStartWithTheFailure(string $error): void
    {
        Assert::contains($this->suggestionsPage->journal()[0] ?? '', $error);
    }

    #[Then('the journal should list a run of :count suggestions')]
    public function theJournalShouldListARunOfSuggestions(int $count): void
    {
        Assert::contains(implode(' | ', $this->suggestionsPage->journal()), \sprintf('%d suggestions', $count));
    }

    #[Then('I should not be allowed to see the suggestions')]
    public function iShouldNotBeAllowedToSeeTheSuggestions(): void
    {
        Assert::true($this->suggestionsPage->isForbidden());
    }

    #[Then('I should be able to decide on them but not to generate more')]
    public function iShouldBeAbleToDecideOnThemButNotToGenerateMore(): void
    {
        Assert::true($this->suggestionsPage->canReview());
        Assert::false($this->suggestionsPage->canGenerate());
    }

    #[Then('I should be able to read them but not to decide on them')]
    public function iShouldBeAbleToReadThemButNotToDecideOnThem(): void
    {
        Assert::true($this->suggestionsPage->hasRow('app.welcome'));
        Assert::false($this->suggestionsPage->canReview());
        Assert::false($this->suggestionsPage->canGenerate());
    }
}
