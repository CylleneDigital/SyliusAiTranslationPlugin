<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\View;

use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\View\TranslationViewFactory;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * What a view costs in queries: the result is CatalogueRowsTest's business. The engine reads
 * the overrides of every catalogue of a view at once, so the number of catalogues must not
 * change it.
 */
final class TranslationViewFactoryTest extends KernelTestCase
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

    public function testTheWholeProjectCostsWhatOneCatalogueCosts(): void
    {
        // What a process reads once (the channels, the locales) is not what a view costs.
        $this->entries();
        $oneCatalogue = $this->queries(fn () => $this->entries(type: 'messages', domain: 'Product'));
        $everyCatalogue = $this->queries(fn () => $this->entries());

        self::assertGreaterThan(0, $oneCatalogue);
        self::assertSame($oneCatalogue, $everyCatalogue);
    }

    public function testModifiedOnlyWithNothingModifiedCostsOneQuery(): void
    {
        self::assertSame(1, $this->queries(fn () => self::assertSame([], $this->entries(onlyOverrides: true))));
    }

    public function testModifiedOnlyCostsTheSameWhateverTheNumberOfModifiedCatalogues(): void
    {
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Tableau');
        $one = $this->queries(fn () => $this->entries(onlyOverrides: true));

        $manager->saveOverride('product.add_to_cart', 'shop/Product/messages', 'fr_FR', 'Au panier');
        $manager->saveOverride('dashboard.saved', 'admin/Dashboard/flashes', 'fr_FR', 'Enregistré');
        $three = $this->queries(fn () => $this->entries(onlyOverrides: true));

        self::assertSame($one, $three);
    }

    /** @param callable(): mixed $read */
    private function queries(callable $read): int
    {
        /** @var DebugDataHolder $holder */
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $read();
        $count = 0;

        // Untyped by Symfony: connection name => the queries it ran.
        foreach ($holder->getData() as $queries) {
            $count += \is_array($queries) ? \count($queries) : 0;
        }

        return $count;
    }

    /** @return list<array<string, mixed>> */
    private function entries(string $domain = '', string $type = '', bool $onlyOverrides = false): array
    {
        /** @var TranslationViewFactory $factory */
        $factory = self::getContainer()->get('cyllene_digital_sylius_ai_translation.view.translation_view_factory');

        return $factory->getEntries('fr_FR', '', '', $domain, $type, '', $onlyOverrides, false, 'en_US', 'en_US', ['en_US', 'fr_FR']);
    }
}
