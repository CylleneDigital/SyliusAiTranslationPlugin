<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\Override;

use CylleneDigital\AiTranslationBundle\Entity\TranslationOverride;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\SyliusAiTranslationPlugin\Override\ManualEditOutcome;
use CylleneDigital\SyliusAiTranslationPlugin\Override\ManualOverrideEditor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * Each outcome of a hand edit, over the engine as the container wires it. The host
 * translations are the test application's: `app.dashboard` has a French text in its file,
 * `app.welcome` has none.
 */
final class ManualOverrideEditorTest extends KernelTestCase
{
    use SyncsTestSchema;

    /** A scope the engine stores as it is given: the editor leaves knowing the channel to its caller. */
    private const string CHANNEL = 'EDITOR_TEST_CHANNEL';

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::syncTestSchema();
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        self::deleteOverridesAndSuggestions();
    }

    protected function tearDown(): void
    {
        self::deleteOverridesAndSuggestions();

        parent::tearDown();
    }

    public function testANewValueIsSaved(): void
    {
        $edit = $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', 'Bienvenue');

        self::assertSame(ManualEditOutcome::Saved, $edit->outcome);
        self::assertNull($edit->placeholders);
        self::assertSame(0, $edit->closedSuggestions);
        self::assertSame('Bienvenue', $this->manager()->getOverrideValue('app.welcome', 'messages', 'fr_FR'));
    }

    public function testASaveSaysWhichPlaceholdersItDropsAndAdds(): void
    {
        $this->manager()->saveOverride('app.welcome', 'messages', 'fr_FR', 'Bienvenue %name%');

        $edit = $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', 'Bienvenue %first_name%');

        // Saved all the same: dropping a placeholder can be deliberate on a hand edit.
        self::assertSame(ManualEditOutcome::Saved, $edit->outcome);
        self::assertSame(['missing' => ['%name%'], 'unexpected' => ['%first_name%']], $edit->placeholders);
        self::assertSame('Bienvenue %first_name%', $this->manager()->getOverrideValue('app.welcome', 'messages', 'fr_FR'));
    }

    public function testASaveKeepsTheLineBreaksAndEdgeSpacesOfTheValueButNotTheBrowsersLineEndings(): void
    {
        $edit = $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', " Bienvenue,\r\nà toi ");

        self::assertSame(ManualEditOutcome::Saved, $edit->outcome);
        self::assertSame(" Bienvenue,\nà toi ", $this->manager()->getOverrideValue('app.welcome', 'messages', 'fr_FR'));

        // The same text posted again by a textarea is the value the entry already shows.
        self::assertSame(ManualEditOutcome::Unchanged, $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', " Bienvenue,\r\nà toi ")->outcome);
    }

    public function testASaveClosesThePendingSuggestionsOfItsEntryOnly(): void
    {
        $this->suggest('app.welcome', 'messages', 'fr_FR');
        $this->suggest('app.dashboard', 'messages', 'fr_FR');
        $this->suggest('app.welcome', 'shop/Checkout/messages', 'fr_FR');
        $this->suggest('app.welcome', 'messages', 'en_US');
        $this->suggest('app.welcome', 'messages', 'fr_FR', self::CHANNEL);

        self::assertSame(1, $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', 'Bienvenue')->closedSuggestions);

        // The count is the editor's, the closing the engine's: both name the same entry.
        self::assertSame([
            'app.dashboard messages fr_FR ',
            'app.welcome messages en_US ',
            'app.welcome messages fr_FR ' . self::CHANNEL,
            'app.welcome shop/Checkout/messages fr_FR ',
        ], $this->pendingSuggestions());
    }

    public function testTheValueTheEntryAlreadyShowsWritesNothing(): void
    {
        // The file value, without an override...
        self::assertSame(ManualEditOutcome::Unchanged, $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', 'Tableau de bord')->outcome);
        self::assertFalse($this->manager()->hasOverride('app.dashboard', 'messages', 'fr_FR'));

        // ...and the override it holds.
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');
        self::assertSame(ManualEditOutcome::Unchanged, $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', 'Pilotage')->outcome);
        self::assertSame('Pilotage', $this->manager()->getOverrideValue('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testTheValueTheEntryAlreadyShowsLeavesItsPendingSuggestionsToReview(): void
    {
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');
        $this->suggest('app.dashboard', 'messages', 'fr_FR');

        $edit = $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', 'Pilotage');

        // Writing the same value again would close them: nobody chose anything.
        self::assertSame(ManualEditOutcome::Unchanged, $edit->outcome);
        self::assertSame(0, $edit->closedSuggestions);
        self::assertSame(['app.dashboard messages fr_FR '], $this->pendingSuggestions());
    }

    public function testTypingTheFileValueBackRemovesTheOverride(): void
    {
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');

        $edit = $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', 'Tableau de bord');

        self::assertSame(ManualEditOutcome::Reverted, $edit->outcome);
        self::assertTrue($edit->revertedToFile);
        self::assertFalse($this->manager()->hasOverride('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testTypingTheGlobalValueBackInAChannelIsNotAReturnToTheFile(): void
    {
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Cockpit', null, self::CHANNEL);

        $edit = $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', self::CHANNEL, 'Pilotage');

        self::assertSame(ManualEditOutcome::Reverted, $edit->outcome);
        self::assertFalse($edit->revertedToFile);
        self::assertFalse($this->manager()->hasOverride('app.dashboard', 'messages', 'fr_FR', self::CHANNEL));
        self::assertSame('Pilotage', $this->manager()->getOverrideValue('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testTypingTheParentLanguagesOverrideBackIsNotAReturnToTheFile(): void
    {
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr', 'Pilotage');
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Cockpit');

        $edit = $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', 'Pilotage');

        self::assertSame(ManualEditOutcome::Reverted, $edit->outcome);
        self::assertFalse($edit->revertedToFile);
        self::assertFalse($this->manager()->hasOverride('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testTypingBackAnInheritedValueThatSaysWhatTheFileSaysIsNotAReturnToTheFile(): void
    {
        // The global override repeats the file: once the channel's own one is gone, the entry
        // still follows the global one, which may change later.
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Tableau de bord');
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Cockpit', null, self::CHANNEL);

        $edit = $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', self::CHANNEL, 'Tableau de bord');

        self::assertSame(ManualEditOutcome::Reverted, $edit->outcome);
        self::assertFalse($edit->revertedToFile);
        self::assertFalse($this->manager()->hasOverride('app.dashboard', 'messages', 'fr_FR', self::CHANNEL));
    }

    public function testAnEmptyValueIsRefusedWhereTheEntryHasATextToShow(): void
    {
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');

        self::assertSame(ManualEditOutcome::EmptyRefused, $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', " \r\n ")->outcome);
        self::assertSame('Pilotage', $this->manager()->getOverrideValue('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testAnEmptyValueClearsTheOverrideOfAnEntryWithoutText(): void
    {
        $this->manager()->saveOverride('app.welcome', 'messages', 'fr_FR', 'Bienvenue');

        self::assertSame(ManualEditOutcome::Cleared, $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', '  ')->outcome);
        self::assertFalse($this->manager()->hasOverride('app.welcome', 'messages', 'fr_FR'));
    }

    public function testAnEmptyValueOnAnEntryWithoutAnythingIsIgnored(): void
    {
        self::assertSame(ManualEditOutcome::EmptyIgnored, $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', '')->outcome);
        self::assertFalse($this->manager()->hasOverride('app.welcome', 'messages', 'fr_FR'));
    }

    public function testACatalogueWithoutFilesGetsNoNewOverride(): void
    {
        self::assertSame(ManualEditOutcome::CatalogueUnknown, $this->editor()->edit('old.key', 'gone/Old/messages', 'fr_FR', '', 'Orphelin')->outcome);
        self::assertFalse($this->manager()->hasOverride('old.key', 'gone/Old/messages', 'fr_FR'));
    }

    public function testAnOrphanOverrideCanStillBeEdited(): void
    {
        $this->manager()->saveOverride('old.key', 'gone/Old/messages', 'fr_FR', 'Orphelin');

        self::assertSame(ManualEditOutcome::Saved, $this->editor()->edit('old.key', 'gone/Old/messages', 'fr_FR', '', 'Orphelin corrigé')->outcome);
        self::assertSame('Orphelin corrigé', $this->manager()->getOverrideValue('old.key', 'gone/Old/messages', 'fr_FR'));
    }

    public function testAValueOutOfTheCataloguesSyntaxIsRefusedWithItsIssues(): void
    {
        // `messages` is a legacy catalogue: an ICU construct would be displayed as it is typed.
        $edit = $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', '{count, plural, one {# article} other {# articles}}');

        self::assertSame(ManualEditOutcome::SyntaxInvalid, $edit->outcome);
        self::assertSame(['syntax_icu_in_legacy'], $edit->syntaxIssues);
        self::assertFalse($this->manager()->hasOverride('app.welcome', 'messages', 'fr_FR'));
    }

    public function testAKeyTheOverrideTableCannotStoreIsRefused(): void
    {
        $key = str_repeat('k', TranslationOverride::MAX_KEY_LENGTH + 1);

        self::assertSame(ManualEditOutcome::KeyTooLong, $this->editor()->edit($key, 'messages', 'fr_FR', '', 'Trop long')->outcome);
        self::assertSame(0, $this->manager()->countOverrides());
    }

    public function testWhatTheEngineRefusesToStoreIsReportedAsInvalid(): void
    {
        // The engine stores a locale the way the runtime looks it up: "fr_FR", not "fr-FR".
        self::assertSame(ManualEditOutcome::Invalid, $this->editor()->edit('app.welcome', 'messages', 'fr-FR', '', 'Bienvenue')->outcome);
        self::assertSame(0, $this->manager()->countOverrides());
    }

    private function suggest(string $key, string $catalogue, string $locale, string $scope = ''): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion($key, $catalogue, $locale, 'Suggestion', 'Source', 'en_US', 'stub', 0.9, $scope));
        $entityManager->flush();
    }

    /** @return list<string> "key catalogue locale scope" of each suggestion still to review, sorted */
    private function pendingSuggestions(): array
    {
        /** @var TranslationSuggestionRepository $repository */
        $repository = self::getContainer()->get(TranslationSuggestionRepository::class);
        $pending = array_map(
            static fn (TranslationSuggestion $suggestion): string => implode(' ', [$suggestion->getKey(), $suggestion->getCatalogue(), $suggestion->getLocale(), $suggestion->getScope()]),
            $repository->findPending(),
        );
        sort($pending);

        return $pending;
    }

    private function editor(): ManualOverrideEditor
    {
        /** @var ManualOverrideEditor $editor */
        $editor = self::getContainer()->get('cyllene_digital_sylius_ai_translation.override.manual_editor');

        return $editor;
    }

    private function manager(): TranslationManagerInterface
    {
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);

        return $manager;
    }
}
