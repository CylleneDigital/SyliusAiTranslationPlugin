<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Functional;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Repository\TranslationOverrideRepository;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportUploadLimit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * Both admin screens rendered for real through the HTTP kernel — Sylius layout, twig
 * hooks, grids — then the edit modal's save/reset round trip and the downloads. The
 * admin is logged in programmatically: no password, no fixtures.
 */
final class AdminScreensTest extends WebTestCase
{
    use SyncsTestSchema;

    private const string ADMIN_USERNAME = 'ai_translation_admin';

    private KernelBrowser $client;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::syncTestSchema();
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->catchExceptions(false);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_suggestion');
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_override');

        $this->client->loginUser(self::admin(self::ADMIN_USERNAME), 'admin');
    }

    public function testTheCatalogueScreenRendersTheCoverageAndTheGrid(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');

        self::assertResponseIsSuccessful();
        // The coverage cards, fed by the engine's report (its default locale badge included).
        self::assertStringContainsString('Reference:', (string) $this->client->getResponse()->getContent());
        self::assertSelectorExists('#translation-edit-form');
        self::assertStringContainsString('app.dashboard', (string) $this->client->getResponse()->getContent());
        self::assertGreaterThan(0, $crawler->filter('table')->count());
        // What admin.css recognises the plugin's screens by, next to Sylius' filters.
        self::assertCount(1, $crawler->filter('.cyllene-translation-screen'));
        self::assertGreaterThan(0, $crawler->filter('.page-body .container-xl > .mb-5 .sylius-filters')->count());
    }

    public function testAPagePastTheEndShowsTheLastPageInsteadOfFailing(): void
    {
        // Where the list lands after the last row of a filtered page left the filter.
        $this->client->request('GET', '/admin/translations', ['page' => 999]);

        self::assertResponseIsSuccessful();
    }

    public function testTheCoverageLinksOpenTheSuggestionsFilteredOnTheirLocale(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome to the shop', 'en_US', 'stub', 0.9));
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();

        $links = $crawler->filter('a[href*="/translations/suggestions"]')->each(static fn ($link): string => urldecode((string) $link->attr('href')));
        $toFrench = array_values(array_filter($links, static fn (string $href): bool => str_contains($href, 'criteria[locale]=fr_FR')));

        // Both the "missing keys" shortcut to the generation form and the "pending review" one.
        self::assertCount(2, $toFrench);
    }

    public function testTheSuggestionScreenRendersThePendingGridAndTheGenerationForm(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome to the shop', 'en_US', 'stub', 0.9));
        $entityManager->flush();

        $this->client->request('GET', '/admin/translations/suggestions');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Bienvenue', $content);
        self::assertStringContainsString('/admin/translations/suggestions/generate', $content);
    }

    public function testTheEditModalSavesThenRestoresAnOverride(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');
        $token = (string) $crawler->filter('#translation-edit-form input[name="_token"]')->attr('value');

        $row = ['_token' => $token, 'key' => 'app.welcome', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => ''];

        $this->client->request('POST', '/admin/translations/save', $row + ['value' => 'Bienvenue à la boutique']);
        self::assertResponseRedirects();

        /** @var TranslationOverrideRepository $repository */
        $repository = self::getContainer()->get(TranslationOverrideRepository::class);
        self::assertSame('Bienvenue à la boutique', $repository->findOneByKey('app.welcome', 'messages', 'fr_FR')?->getValue());

        $this->client->request('POST', '/admin/translations/reset', $row);
        self::assertResponseRedirects();
        self::assertNull($repository->findOneByKey('app.welcome', 'messages', 'fr_FR'));
    }

    public function testARegionalLocaleShowsAndKeepsTheValueItInheritsFromItsLanguage(): void
    {
        // fr_FR inherits the "fr" override through the engine's language chain.
        /** @var TranslationManagerInterface $translationManager */
        $translationManager = self::getContainer()->get(TranslationManagerInterface::class);
        $translationManager->saveOverride('app.welcome', 'messages', 'fr', 'Bienvenue (langue)');

        $crawler = $this->client->request('GET', '/admin/translations', ['criteria' => ['locale' => 'fr_FR', 'search' => ['value' => 'app.welcome']]]);
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Bienvenue (langue)', $content);
        // Where the value comes from, not "inherited from global".
        self::assertStringContainsString('inherited from fr', $content);
        self::assertStringNotContainsString('inherited from global', $content);

        // Saving the inherited value back writes no copy of it.
        $token = (string) $crawler->filter('#translation-edit-form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/translations/save', [
            '_token' => $token, 'key' => 'app.welcome', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => '', 'value' => 'Bienvenue (langue)',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('Nothing saved — the entry already shows this value.', (string) $this->client->getResponse()->getContent());

        /** @var TranslationOverrideRepository $repository */
        $repository = self::getContainer()->get(TranslationOverrideRepository::class);
        self::assertNull($repository->findOneByKey('app.welcome', 'messages', 'fr_FR'));
    }

    public function testSavingAnOverrideClosesThePendingSuggestionsOfItsKey(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue IA', 'Welcome to the shop', 'en_US', 'stub', 0.9));
        // Another key's suggestion stays pending.
        $entityManager->persist(new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau IA', 'Dashboard', 'en_US', 'stub', 0.9));
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/translations');
        $token = (string) $crawler->filter('#translation-edit-form input[name="_token"]')->attr('value');

        // A multi-line value as a textarea posts it: "\r\n", stored as "\n".
        $this->client->request('POST', '/admin/translations/save', [
            '_token' => $token, 'key' => 'app.welcome', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => '', 'value' => "Bienvenue\r\nchez nous ",
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('1 pending AI suggestion for this key was closed', (string) $this->client->getResponse()->getContent());

        /** @var TranslationOverrideRepository $repository */
        $repository = self::getContainer()->get(TranslationOverrideRepository::class);
        // Kept as typed, its trailing space included.
        self::assertSame("Bienvenue\nchez nous ", $repository->findOneByKey('app.welcome', 'messages', 'fr_FR')?->getValue());
        self::assertSame(['app.dashboard'], $entityManager->getConnection()->fetchFirstColumn('SELECT translation_key FROM cyllene_translation_suggestion'));
    }

    public function testAKeyTooLongForTheOverrideTableIsRefusedWithoutWriting(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');
        $token = (string) $crawler->filter('#translation-edit-form input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/translations/save', [
            '_token' => $token,
            'key' => str_repeat('k', 401),
            'catalogue' => 'messages',
            'locale' => 'fr_FR',
            'scope' => '',
            'value' => 'Trop long',
        ]);

        self::assertResponseRedirects();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertCount(0, $entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM cyllene_translation_override'));
    }

    public function testTheGenerationFormOffersTheConfiguredDefaultProviderFirst(): void
    {
        // The test application declares deepl before llm, its default.
        $crawler = $this->client->request('GET', '/admin/translations/suggestions');

        self::assertResponseIsSuccessful();
        self::assertSame('llm', $crawler->filter('#generate_provider option[selected]')->attr('value'));
    }

    public function testAPresetTargetTheViewCannotUseFallsBackOnAnotherLocaleThanTheSource(): void
    {
        // Unknown, or the source itself: the browser would otherwise keep the first option.
        foreach (['xx_XX', 'en_US'] as $target) {
            $crawler = $this->client->request('GET', '/admin/translations/suggestions', ['source' => 'en_US', 'target' => $target]);

            self::assertResponseIsSuccessful();
            self::assertSame('en_US', $crawler->filter('#generate_source option[selected]')->attr('value'));
            self::assertSame('fr_FR', $crawler->filter('#generate_target option[selected]')->attr('value'));
        }
    }

    public function testTheImportAnnouncesTheSizeAnUploadReallyMeets(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            \sprintf('%s MB at most', ImportUploadLimit::megabytes()),
            $crawler->filter('#import-overrides-modal .form-hint')->first()->text(),
        );
    }

    public function testTheSuggestionsRenderUnderEitherSortingOfTheConfidence(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome', 'en_US', 'stub', 0.5));
        $entityManager->flush();

        // The tie-breaker GridViewBuilder adds is a field of the grid no column shows: Sylius
        // must take it in the sorting and leave it out of the table.
        foreach (['asc', 'desc'] as $direction) {
            $crawler = $this->client->request('GET', '/admin/translations/suggestions', ['sorting' => ['confidence' => $direction]]);
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('app.welcome', (string) $this->client->getResponse()->getContent());
            self::assertCount(0, $crawler->filter('th.sylius-table-column-id'));
        }
    }

    public function testTheModalsWhereTextIsTypedDoNotCloseOnAClickBesideThem(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome', 'en_US', 'stub', 0.5));
        $entityManager->flush();

        // Reopened, a modal is filled again from its row: a stray click would lose what was typed.
        foreach (['/admin/translations' => '#translation-edit-modal', '/admin/translations/suggestions' => '#suggestion-review-modal'] as $screen => $modal) {
            $crawler = $this->client->request('GET', $screen);

            self::assertResponseIsSuccessful();
            self::assertSame('static', $crawler->filter($modal)->attr('data-bs-backdrop'));
        }
    }

    public function testAnEmptyListFilteredOnALanguageAloneOffersTheGeneration(): void
    {
        // Where a generation lands, before its suggestions arrive.
        $crawler = $this->client->request('GET', '/admin/translations/suggestions', ['criteria' => ['locale' => 'fr_FR']]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Use the form above', $crawler->filter('.empty-subtitle')->text());

        $crawler = $this->client->request('GET', '/admin/translations/suggestions', ['criteria' => ['locale' => 'fr_FR', 'search' => ['value' => 'nothing-like-this']]]);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Use the form above', $crawler->filter('.empty-subtitle')->text());
    }

    public function testAFilterSetOnAValueCanBeSetBackOnAll(): void
    {
        // The first option of a select filter is its "everything" choice: once another one is
        // picked it must stay offered, which Symfony denies a required field.
        foreach (['/admin/translations', '/admin/translations/suggestions'] as $screen) {
            $crawler = $this->client->request('GET', $screen, ['criteria' => ['catalogue' => 'messages', 'locale' => 'fr_FR']]);
            self::assertResponseIsSuccessful();

            foreach (['catalogue', 'locale'] as $filter) {
                $all = $crawler->filter(\sprintf('select[name="criteria[%s]"] > option', $filter))->first();

                self::assertSame('', $all->attr('value'));
                self::assertNull($all->attr('hidden'), \sprintf('The "%s" filter of %s hides its first option.', $filter, $screen));
            }
        }
    }

    public function testTheFailedGenerationsStayListedWhenAnotherFilterIsUsed(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome', 'en_US', 'stub', 0.5));
        $entityManager->persist(TranslationSuggestion::failed('app.dashboard', 'messages', 'fr_FR', 'Dashboard', 'en_US', 'stub', 'The provider answered 429'));
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();

        // Sylius' own filter only offers "No" and "Yes": "No" would go with every search.
        $options = $crawler->filter('select[name="criteria[failed]"] > option');
        self::assertSame('', $options->first()->attr('value'));
        self::assertNull($options->first()->attr('hidden'));

        // The form as the browser sends it for a search, the filter left on its first choice.
        $this->client->request('GET', '/admin/translations/suggestions', ['criteria' => ['failed' => '', 'search' => ['value' => 'app.']]]);
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('app.welcome', $content);
        self::assertStringContainsString('app.dashboard', $content);
    }

    public function testAScalarCriteriaOrSortingIsIgnoredOnBothScreens(): void
    {
        // Sylius types both as arrays: without the guard, a hand-built URL is a 500.
        foreach (['/admin/translations', '/admin/translations/suggestions'] as $screen) {
            $this->client->request('GET', $screen . '?criteria=x&sorting=desc&source[]=x&target[]=y');
            self::assertResponseIsSuccessful();

            // Numbered entries are no better: Sylius reads the keys as field names.
            $this->client->request('GET', $screen . '?criteria[0]=x&sorting[0]=asc&sorting[1][]=asc');
            self::assertResponseIsSuccessful();
        }
    }

    public function testTheViewExportAndTheImportTemplateCarryTheirMediaType(): void
    {
        $this->client->request('GET', '/admin/translations/export/csv', ['criteria' => ['locale' => 'en_US']]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('app.dashboard', (string) $this->client->getInternalResponse()->getContent());

        // The export follows the sorting of the screen it was asked from.
        $this->client->request('GET', '/admin/translations/export/csv', ['criteria' => ['locale' => 'en_US'], 'sorting' => ['key' => 'desc']]);
        self::assertResponseIsSuccessful();
        $sorted = (string) $this->client->getInternalResponse()->getContent();
        $welcome = strpos($sorted, 'app.welcome');
        $dashboard = strpos($sorted, 'app.dashboard');
        self::assertNotFalse($welcome);
        self::assertNotFalse($dashboard);
        self::assertGreaterThan($welcome, $dashboard);

        // A criteria or a sorting that is not made of named entries is ignored, as the grid does.
        foreach (['criteria=x&sorting=desc', 'criteria[0]=x&sorting[0]=asc'] as $query) {
            $this->client->request('GET', '/admin/translations/export/csv?' . $query);
            self::assertResponseIsSuccessful();
        }

        // The catalogue grid sorts through its own data provider, not Sylius' sorter: a
        // direction in capitals is taken on the screen, and so it is here.
        $this->client->request('GET', '/admin/translations?sorting[key]=DESC');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/translations/export/csv?criteria[locale]=en_US&sorting[key]=DESC');
        self::assertResponseIsSuccessful();
        $capitals = (string) $this->client->getInternalResponse()->getContent();
        self::assertNotFalse(strpos($capitals, 'app.welcome'));
        self::assertNotFalse(strpos($capitals, 'app.dashboard'));
        self::assertGreaterThan(strpos($capitals, 'app.welcome'), strpos($capitals, 'app.dashboard'));

        $this->client->request('GET', '/admin/translations/import-template/xlf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xliff+xml');
    }
}
