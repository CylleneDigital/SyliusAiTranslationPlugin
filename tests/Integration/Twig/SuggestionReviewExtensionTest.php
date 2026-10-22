<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\Twig;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Override\TranslationValueValidator;
use CylleneDigital\AiTranslationBundle\Suggestion\PlaceholderConsistencyChecker;
use CylleneDigital\SyliusAiTranslationPlugin\Twig\SuggestionReviewExtension;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * What the review of a page of suggestions costs in queries, and that reading the page at
 * once shows each row what reading it alone would.
 */
final class SuggestionReviewExtensionTest extends KernelTestCase
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

    public function testAPageCostsWhatOneCatalogueCostsAndItsRowsNothing(): void
    {
        $oneCatalogue = [$this->suggestion('app.dashboard', 'messages')];
        $fourCatalogues = [
            $this->suggestion('app.dashboard', 'messages'),
            $this->suggestion('app.welcome', 'messages'),
            $this->suggestion('product.out_of_stock', 'shop/Product/messages'),
            $this->suggestion('checkout.pay', 'shop/Checkout/messages'),
            $this->suggestion('dashboard.saved', 'admin/Dashboard/flashes'),
        ];

        $one = $this->queries(fn () => $this->extension()->prefetch($oneCatalogue));
        $extension = $this->extension();
        $four = $this->queries(fn () => $extension->prefetch($fourCatalogues));

        self::assertGreaterThan(0, $one);
        self::assertSame($one, $four);
        self::assertSame(0, $this->queries(function () use ($extension, $fourCatalogues): void {
            foreach ($fourCatalogues as $suggestion) {
                $extension->review($suggestion);
            }
        }));
    }

    public function testEachLocaleAndChannelOfThePageIsReadOnce(): void
    {
        $one = $this->queries(fn () => $this->extension()->prefetch([$this->suggestion('app.dashboard', 'messages')]));
        $three = $this->queries(fn () => $this->extension()->prefetch([
            $this->suggestion('app.dashboard', 'messages'),
            $this->suggestion('product.out_of_stock', 'shop/Product/messages'),
            $this->suggestion('app.dashboard', 'messages', 'de_DE'),
            $this->suggestion('app.dashboard', 'messages', 'fr_FR', 'B2B'),
            $this->suggestion('checkout.pay', 'shop/Checkout/messages', 'fr_FR', 'B2B'),
        ]));

        self::assertSame(3 * $one, $three);
    }

    public function testThePrefetchShowsEachRowWhatReadingItAloneShows(): void
    {
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage');
        $manager->saveOverride('app.welcome', 'messages', 'fr', 'Bienvenue (fr)');
        $manager->saveOverride('product.out_of_stock', 'shop/Product/messages', 'fr_FR', 'Épuisé');
        $manager->saveOverride('product.out_of_stock', 'shop/Product/messages', 'fr_FR', 'Épuisé (B2B)', null, 'B2B');

        $page = [
            $this->suggestion('app.dashboard', 'messages'),
            $this->suggestion('app.welcome', 'messages'),
            $this->suggestion('product.out_of_stock', 'shop/Product/messages'),
            $this->suggestion('product.out_of_stock', 'shop/Product/messages', 'fr_FR', 'B2B'),
            $this->suggestion('checkout.pay', 'shop/Checkout/messages', 'fr_FR', 'B2B'),
            $this->suggestion('no.such.key', 'messages'),
        ];
        $prefetched = $this->extension();
        $prefetched->prefetch($page);

        foreach ($page as $suggestion) {
            self::assertSame($this->extension()->review($suggestion), $prefetched->review($suggestion), $suggestion->getKey() . ' ' . $suggestion->getScope());
        }

        self::assertSame('inherited', $prefetched->review($page[1])['currentOrigin']);
        self::assertSame('Épuisé (B2B)', $prefetched->review($page[3])['current']);
    }

    private function suggestion(string $key, string $catalogue, string $locale = 'fr_FR', string $scope = ''): TranslationSuggestion
    {
        return new TranslationSuggestion($key, $catalogue, $locale, 'Valeur', 'Value', 'en_US', 'stub', 0.9, $scope);
    }

    /** A fresh one each time: the container's keeps what it read. */
    private function extension(): SuggestionReviewExtension
    {
        $container = self::getContainer();

        /** @var TranslationManagerInterface $translationManager */
        $translationManager = $container->get(TranslationManagerInterface::class);
        /** @var PlaceholderConsistencyChecker $placeholders */
        $placeholders = $container->get(PlaceholderConsistencyChecker::class);
        /** @var TranslationValueValidator $validator */
        $validator = $container->get(TranslationValueValidator::class);

        return new SuggestionReviewExtension($translationManager, $placeholders, $validator);
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
}
