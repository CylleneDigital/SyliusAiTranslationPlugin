<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\Override;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
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

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::syncTestSchema();
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->deleteOverridesAndSuggestions();
    }

    protected function tearDown(): void
    {
        $this->deleteOverridesAndSuggestions();

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

    public function testASaveCountsThePendingSuggestionsOfItsEntryOnly(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue IA', 'Welcome to the shop', 'en_US', 'stub', 0.9));
        $entityManager->persist(new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau IA', 'Dashboard', 'en_US', 'stub', 0.9));
        $entityManager->flush();

        self::assertSame(1, $this->editor()->edit('app.welcome', 'messages', 'fr_FR', '', 'Bienvenue')->closedSuggestions);
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

    public function testTypingTheFileValueBackRemovesTheOverride(): void
    {
        $this->manager()->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');

        $edit = $this->editor()->edit('app.dashboard', 'messages', 'fr_FR', '', 'Tableau de bord');

        self::assertSame(ManualEditOutcome::Reverted, $edit->outcome);
        self::assertTrue($edit->revertedToFile);
        self::assertFalse($this->manager()->hasOverride('app.dashboard', 'messages', 'fr_FR'));
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
        $key = str_repeat('k', 401);

        self::assertSame(ManualEditOutcome::KeyTooLong, $this->editor()->edit($key, 'messages', 'fr_FR', '', 'Trop long')->outcome);
        self::assertSame(0, $this->manager()->countOverrides());
    }

    public function testWhatTheEngineRefusesToStoreIsReportedAsInvalid(): void
    {
        // The engine stores a locale the way the runtime looks it up: "fr_FR", not "fr-FR".
        self::assertSame(ManualEditOutcome::Invalid, $this->editor()->edit('app.welcome', 'messages', 'fr-FR', '', 'Bienvenue')->outcome);
        self::assertSame(0, $this->manager()->countOverrides());
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

    private function deleteOverridesAndSuggestions(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_suggestion');
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_override');
    }
}
