<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\Grid;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\CatalogueRows;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * The catalogue rows over the engine as the container wires it — the host translations
 * are the test application's (tests/TestApplication/translations, written in English with
 * a partial French), its default locale en_US.
 */
final class CatalogueRowsTest extends KernelTestCase
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
        self::deleteOverridesAndSuggestions();
    }

    protected function tearDown(): void
    {
        self::deleteOverridesAndSuggestions();

        parent::tearDown();
    }

    public function testNoCriteriaBrowsesTheDefaultLocale(): void
    {
        $rows = $this->rows()->get([], '');
        $byKey = array_column($rows, null, 'key');

        // Each row is self-contained for the edit modal.
        self::assertSame('en_US', $byKey['app.welcome']['locale']);
        self::assertSame('', $byKey['app.welcome']['scope']);
        self::assertSame('Welcome to the shop', $byKey['app.welcome']['original']);
        self::assertFalse($byKey['app.welcome']['missing']);

        // The rows arrive by catalogue, then by key.
        $keys = array_column($rows, 'key');
        self::assertLessThan(array_search('app.welcome', $keys, true), array_search('app.dashboard', $keys, true));
    }

    public function testAnUnknownLocaleCriteriaFallsBackToTheDefault(): void
    {
        self::assertSame('en_US', $this->rows()->resolveLocale(['locale' => 'xx_XX'], ''));
        self::assertSame('fr_FR', $this->rows()->resolveLocale(['locale' => 'fr_FR'], ''));
    }

    public function testMissingRowsCarryTheDefaultLocaleReferenceAndTheMissingFilterKeepsThemAlone(): void
    {
        // Like the engine's effective key set, the rows are every key the view's locales
        // carry: the French ones plus the ones only English holds.
        $byKey = array_column($this->rows()->get(['locale' => 'fr_FR'], ''), null, 'key');

        self::assertTrue($byKey['app.welcome']['missing']);
        self::assertNull($byKey['app.welcome']['original']);
        self::assertSame('Welcome to the shop', $byKey['app.welcome']['reference']);
        self::assertSame('en_US', $byKey['app.welcome']['referenceLocale']);
        self::assertFalse($byKey['app.dashboard']['missing']);
        self::assertSame('Tableau de bord', $byKey['app.dashboard']['original']);

        $missing = array_column($this->rows()->get(['locale' => 'fr_FR', 'missing' => CatalogueRows::CRITERIA_TRUE], ''), 'key');

        self::assertContains('app.welcome', $missing);
        self::assertContains('product.out_of_stock', $missing);
        self::assertContains('dashboard.saved', $missing);
        self::assertNotContains('app.dashboard', $missing);
        self::assertNotContains('product.add_to_cart', $missing);
    }

    public function testTheCatalogueCriteriaNarrowsToOneCatalogueIncludingOrphans(): void
    {
        $rows = $this->rows()->get(['catalogue' => 'shop/Product/messages'], '');
        self::assertSame(['product.add_to_cart', 'product.out_of_stock'], array_column($rows, 'key'));

        // An orphan catalogue (files gone, override left) is reachable through "modified".
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('old.key', 'gone/Old/messages', 'en_US', 'Orphan');

        $rows = $this->rows()->get(['catalogue' => 'gone/Old/messages', 'modified' => CatalogueRows::CRITERIA_TRUE], '');
        self::assertSame(['old.key'], array_column($rows, 'key'));
        self::assertSame('Orphan', $rows[0]['override']);
    }

    public function testAModifiedRowKeepsItsReferenceAndItsFileValue(): void
    {
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('app.welcome', 'messages', 'fr_FR', 'Bienvenue à toi');
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Tableau');

        $rows = $this->rows()->get(['locale' => 'fr_FR', 'modified' => CatalogueRows::CRITERIA_TRUE], '');
        $byKey = array_column($rows, null, 'key');

        // Only the overridden rows, and they are the rows of the unfiltered view.
        self::assertSame(['app.dashboard', 'app.welcome'], array_keys($byKey));
        self::assertSame('Bienvenue à toi', $byKey['app.welcome']['override']);
        self::assertSame('Welcome to the shop', $byKey['app.welcome']['reference']);
        self::assertSame('en_US', $byKey['app.welcome']['referenceLocale']);
        self::assertSame('Tableau de bord', $byKey['app.dashboard']['original']);
        self::assertFalse($byKey['app.welcome']['missing']);
    }

    public function testAnOrphanCatalogueOnlyShowsItsOwnOverrides(): void
    {
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('old.key', 'gone/Old/messages', 'en_US', 'Orphan');
        $manager->saveOverride('other.key', 'gone/Other/messages', 'en_US', 'Other orphan');
        $manager->saveOverride('app.welcome', 'messages', 'en_US', 'Hello');

        $rows = $this->rows()->get(['catalogue' => 'gone/Old/messages', 'modified' => CatalogueRows::CRITERIA_TRUE], '');
        self::assertSame(['old.key'], array_column($rows, 'key'));

        // Without a catalogue, the orphans take their place in the catalogue order.
        $rows = $this->rows()->get(['modified' => CatalogueRows::CRITERIA_TRUE], '');
        self::assertSame(['gone/Old/messages', 'gone/Other/messages', 'messages'], array_column($rows, 'catalogue'));
    }

    public function testANumericKeyIsSortedAndSearchedLikeAnyOther(): void
    {
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('404', 'messages', 'en_US', 'Not found');

        $keys = array_column($this->rows()->get(['catalogue' => 'messages'], '', ['key' => 'asc']), 'key');
        self::assertSame('404', $keys[0]);

        $rows = $this->rows()->get(['search' => ['value' => 'not found']], '');
        self::assertSame(['404'], array_column($rows, 'key'));
    }

    public function testAnExplicitKeySortSpansTheCatalogues(): void
    {
        $natural = array_column($this->rows()->get([], ''), 'key');
        $sorted = array_column($this->rows()->get([], '', ['key' => 'desc']), 'key');
        $expected = $natural;
        rsort($expected, \SORT_STRING);

        self::assertSame($expected, $sorted);
        self::assertNotSame($natural, $sorted);
    }

    public function testAWholeCategoryCriteriaKeepsEveryCatalogueOfTheCategory(): void
    {
        $rows = $this->rows()->get(['catalogue' => 'cat:shop'], '');

        // shop/Checkout/messages joins in although it is only written in English: every
        // view locale's keys are listed.
        self::assertSame(
            ['shop/Checkout/messages', 'shop/Product/messages'],
            array_values(array_unique(array_column($rows, 'catalogue'))),
        );
    }

    public function testTheSearchCriteriaMatchesTheReferenceTextToo(): void
    {
        // "Welcome" is only written in English: found from the French view by its reference.
        $rows = $this->rows()->get(['locale' => 'fr_FR', 'search' => ['value' => 'Welcome']], '');

        self::assertSame(['app.welcome'], array_column($rows, 'key'));
    }

    private function rows(): CatalogueRows
    {
        /** @var CatalogueRows $rows */
        $rows = self::getContainer()->get('cyllene_digital_sylius_ai_translation.grid.catalogue_rows');

        return $rows;
    }
}
