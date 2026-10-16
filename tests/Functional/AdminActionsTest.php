<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Functional;

use CylleneDigital\AiTranslationBundle\Entity\GenerationLog;
use CylleneDigital\AiTranslationBundle\Entity\TranslationOverride;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManager;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Repository\TranslationOverrideRepository;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\AiTranslationBundle\Transfer\OverrideImporter;
use CylleneDigital\SyliusAiTranslationPlugin\DependencyInjection\CylleneDigitalSyliusAiTranslationExtension;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportRefusal;
use CylleneDigital\SyliusAiTranslationPlugin\Transfer\ImportStager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * The state-changing admin actions over HTTP: the import (stage, confirm, cancel),
 * the orphan purge, the suggestion bulk actions, the generate/estimate coordinate
 * validation, and the CSRF/permission guards. Same conventions as AdminScreensTest:
 * a real database, a programmatically logged-in admin, no fixtures.
 */
final class AdminActionsTest extends WebTestCase
{
    use SyncsTestSchema;

    private const string ADMIN_USERNAME = 'ai_translation_admin';

    private const string BULK_TEST_CHANNEL = 'AI_TEST_BULK';

    /** A second enabled channel: Sylius refuses to delete the last enabled one. */
    private const string OTHER_TEST_CHANNEL = 'AI_TEST_BULK_OTHER';

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
        self::deleteOverridesAndSuggestions();
        $entityManager->getConnection()->executeStatement("DELETE FROM sylius_admin_user WHERE username LIKE 'ai_action_%'");

        $this->client->loginUser(self::admin(self::ADMIN_USERNAME), 'admin');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The kernel is down after the parent: a dedicated boot for the cleanup, the
        // same way the test's own setUp boots it. The suggestions of the test channel
        // are swept by the next setUp's DELETE; the channel itself must go here.
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM sylius_channel WHERE code IN (?, ?)', [self::BULK_TEST_CHANNEL, self::OTHER_TEST_CHANNEL]);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_generation_log WHERE scope = ?', [self::BULK_TEST_CHANNEL]);

        self::ensureKernelShutdown();
    }

    public function testTheImportFlowStagesPreviewsThenWritesTheOverrides(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('#translation-edit-form input[name=_token]')->attr('value');

        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\n");

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects('/admin/translations');

        // The staged import is previewed on the dashboard — nothing is written yet.
        $this->client->request('GET', '/admin/translations');
        $crawler = $this->client->getCrawler();
        self::assertSelectorExists('form[action$="/import/confirm"]');
        self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));

        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('1 override imported', (string) $this->client->getResponse()->getContent());

        self::assertSame('Bienvenue à toi', $this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR')?->getValue());
    }

    public function testAnImportThatOnlyRevertsValuesIsStagedAndRemovesTheOverrides(): void
    {
        /** @var TranslationManager $translationManager */
        $translationManager = self::getContainer()->get(TranslationManager::class);
        $translationManager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Tableau modifié');

        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('#translation-edit-form input[name=_token]')->attr('value');

        // The file value: nothing to write, but the stored override goes.
        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.dashboard,\"Tableau de bord\",\n");

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertSelectorExists('form[action$="/import/confirm"]');
        self::assertStringContainsString('1 override will be removed', (string) $this->client->getResponse()->getContent());
        self::assertNotNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR'));

        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('1 override removed, back to the value the entry inherits or its file value', (string) $this->client->getResponse()->getContent());

        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testTheImportPreviewNamesTheEntriesItWillSkip(): void
    {
        $token = $this->transferToken();
        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\nxx_XX,messages,app.dashboard,\"Tableau\",\n");

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();

        // Where the entry is in the file (the spreadsheet row), its key, and why.
        $content = html_entity_decode((string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Entries that will be skipped', $content);
        self::assertStringContainsString('row 3 — app.dashboard: unknown locale "xx_XX"', $content);

        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        $this->client->followRedirect();
        self::assertStringContainsString('row 3 — app.dashboard: unknown locale "xx_XX"', html_entity_decode((string) $this->client->getResponse()->getContent()));
        self::assertSame('Bienvenue à toi', $this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR')?->getValue());
    }

    public function testTheReviewCardShowsTheCurrentValueAndTheChecksOfTheApproval(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // The file value of fr_FR (through its "fr" file): approving it would write nothing.
        $entityManager->persist(new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau de bord', 'Dashboard', 'en_US', 'stub', 0.9));
        // A lost placeholder and a lost plural category, computed now — no metadata stored.
        $entityManager->persist(new TranslationSuggestion('app.greeting', 'messages', 'fr_FR', 'Bonjour {count, plural, other {# articles}}', 'Hello %name%, {count, plural, one {# item} other {# items}}', 'en_US', 'stub', 0.8));
        $entityManager->flush();

        $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        $content = html_entity_decode((string) $this->client->getResponse()->getContent());

        self::assertStringContainsString('data-suggestion-current="Tableau de bord"', $content);
        self::assertStringContainsString('data-suggestion-current-origin="file value"', $content);
        self::assertStringContainsString('Already shown', $content);
        self::assertStringContainsString('data-suggestion-mismatch="missing: %name%"', $content);
        self::assertStringContainsString('"count" (plural): one', $content);
    }

    public function testApprovingTheValueAlreadyShownWritesNoOverride(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suggestion = new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau de bord', 'Dashboard', 'en_US', 'stub', 0.9);
        $entityManager->persist($suggestion);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/admin/translations/suggestions/%d/approve', $suggestion->getId()), ['_token' => $this->suggestionToken()]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertStringContainsString('it was already the value shown, no override written', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR'));
    }

    public function testAPlaceholderRefusalSaysWhichWayThePlaceholdersDiffer(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suggestion = new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue %name%', 'Welcome to the shop', 'en_US', 'stub', 0.9);
        $entityManager->persist($suggestion);
        $entityManager->flush();

        $this->client->request('POST', \sprintf('/admin/translations/suggestions/%d/approve', $suggestion->getId()), ['_token' => $this->suggestionToken()]);
        $this->client->followRedirect();

        // Added, not lost: the refusal must not send the reviewer after a missing one.
        self::assertStringContainsString('the placeholders differ from the source (extra: %name%)', html_entity_decode((string) $this->client->getResponse()->getContent()));
        self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));
    }

    public function testCancellingTheStagedImportWritesNothing(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('#translation-edit-form input[name=_token]')->attr('value');

        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\n");

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects('/admin/translations');

        $this->client->request('POST', '/admin/translations/import/cancel', ['_token' => $token]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('Import cancelled — nothing was written.', (string) $this->client->getResponse()->getContent());

        self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));

        // The stage is consumed: confirming again hits the expired path, not a stale write.
        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('The staged import has expired', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));
    }

    public function testAnImportWithoutAnAnnouncedLengthIsReadLikeAnyOther(): void
    {
        // A chunked upload reaches PHP with an empty CONTENT_LENGTH: not a number, not a crash.
        foreach (['', 'abc'] as $length) {
            $this->client->request('POST', '/admin/translations/import', ['_token' => $this->transferToken()], ['file' => $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\n")], ['CONTENT_LENGTH' => $length]);
            self::assertResponseRedirects();
            $crawler = $this->client->followRedirect();
            self::assertCount(1, $crawler->filter('form[action$="/import/confirm"]'));
        }
    }

    public function testAnUploadPhpDroppedForItsSizeIsToldSoInsteadOfAForgedRequest(): void
    {
        if (0 === ini_parse_quantity((string) \ini_get('post_max_size'))) {
            self::markTestSkipped('This PHP has no post_max_size: it never empties a request for its size.');
        }

        // Over post_max_size, PHP hands an empty form: no file, no token.
        $this->client->request('POST', '/admin/translations/import', [], [], ['CONTENT_LENGTH' => (string) \PHP_INT_MAX]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('File rejected', (string) $this->client->getResponse()->getContent());
    }

    public function testAnUploadThatFailedSaysWhetherToRetryShrinkTheFileOrCallTheAdministrator(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ai_translation_upload_');
        file_put_contents($path, "locale,catalogue,translation_key,value,scope\n");

        foreach ([\UPLOAD_ERR_PARTIAL => 'did not reach the server whole', \UPLOAD_ERR_NO_TMP_DIR => 'The server could not store the file', \UPLOAD_ERR_CANT_WRITE => 'The server could not store the file', \UPLOAD_ERR_INI_SIZE => 'File rejected'] as $error => $message) {
            $this->client->request('POST', '/admin/translations/import', ['_token' => $this->transferToken()], ['file' => new UploadedFile($path, 'overrides.csv', 'text/csv', $error, true)]);
            self::assertResponseRedirects();
            $this->client->followRedirect();
            self::assertStringContainsString($message, (string) $this->client->getResponse()->getContent());
        }
    }

    public function testARefusedUploadWithdrawsTheImportThatWasWaiting(): void
    {
        $token = $this->transferToken();
        $valid = "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\n";

        // Refused by the stager (nothing in it to import), by the controller (not a translation
        // file), then by PHP before either saw it (a request it emptied for its size).
        $refusals = [
            [['_token' => $token], ['file' => $this->csvUpload('empty.csv', "locale,catalogue,translation_key,value,scope\n")], []],
            [['_token' => $token], ['file' => $this->csvUpload('notes.txt', 'Just notes.')], []],
        ];

        // A PHP without a post_max_size never empties a request: nothing to refuse there.
        if (ini_parse_quantity((string) \ini_get('post_max_size')) > 0) {
            $refusals[] = [[], [], ['CONTENT_LENGTH' => (string) \PHP_INT_MAX]];
        }

        foreach ($refusals as [$form, $files, $server]) {
            $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $this->csvUpload('overrides.csv', $valid)]);
            $crawler = $this->client->followRedirect();
            self::assertCount(1, $crawler->filter('form[action$="/import/confirm"]'));

            $this->client->request('POST', '/admin/translations/import', $form, $files, $server);
            $crawler = $this->client->followRedirect();
            self::assertCount(0, $crawler->filter('form[action$="/import/confirm"]'));

            $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
            $this->client->followRedirect();
            self::assertStringContainsString('The staged import has expired', (string) $this->client->getResponse()->getContent());
            self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));
        }
    }

    public function testARowInALocaleItsChannelDoesNotExposeIsNotWritten(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->createTestChannel($entityManager);

        /** @var RepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        /** @var RepositoryInterface<LocaleInterface> $locales */
        $locales = self::getContainer()->get('sylius.repository.locale');
        $channel = $channels->findOneBy(['code' => self::BULK_TEST_CHANNEL]);
        $english = $locales->findOneBy(['code' => 'en_US']);
        self::assertNotNull($channel);
        self::assertNotNull($english);
        $channel->addLocale($english);
        $entityManager->flush();

        $row = ['_token' => $this->transferToken(), 'key' => 'app.dashboard', 'catalogue' => 'messages', 'scope' => self::BULK_TEST_CHANNEL];

        // Only a hand-built request names a locale the channel's screen does not offer.
        $this->client->request('POST', '/admin/translations/save', $row + ['locale' => 'fr_FR', 'value' => 'Pilotage']);
        self::assertResponseRedirects();
        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::BULK_TEST_CHANNEL));

        $this->client->request('POST', '/admin/translations/save', $row + ['locale' => 'en_US', 'value' => 'Cockpit']);
        self::assertResponseRedirects();
        self::assertSame('Cockpit', $this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'en_US', self::BULK_TEST_CHANNEL)?->getValue());

        // An override left in a locale the channel dropped since can still be removed.
        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage', null, self::BULK_TEST_CHANNEL);
        $this->client->request('POST', '/admin/translations/reset', ['_token' => $this->transferToken(), 'key' => 'app.dashboard', 'catalogue' => 'messages', 'scope' => self::BULK_TEST_CHANNEL, 'locale' => 'fr_FR']);
        self::assertResponseRedirects();
        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::BULK_TEST_CHANNEL));
    }

    public function testDeletingAChannelFromItsGridPurgesItsTranslationsAndKeepsItsJournal(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->createTestChannel($entityManager);
        $this->createTestChannel($entityManager, self::OTHER_TEST_CHANNEL);

        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage', null, self::BULK_TEST_CHANNEL);
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Cockpit', null, self::OTHER_TEST_CHANNEL);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome', 'en_US', 'stub', 0.9, self::BULK_TEST_CHANNEL));
        $entityManager->persist(GenerationLog::success('messages', 'fr_FR', 'en_US', 'stub', 1, self::BULK_TEST_CHANNEL));
        $entityManager->flush();

        /** @var RepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $id = $channels->findOneBy(['code' => self::BULK_TEST_CHANNEL])?->getId();
        self::assertIsInt($id);

        // Through Sylius' own controller and transaction, as an admin does it from the grid.
        $crawler = $this->client->request('GET', '/admin/channels/');
        $this->client->submit($crawler->filter(\sprintf('form[action="/admin/channels/%d"]', $id))->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertNull($channels->findOneBy(['code' => self::BULK_TEST_CHANNEL]));
        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::BULK_TEST_CHANNEL));
        $connection = $entityManager->getConnection();
        self::assertCount(0, $connection->fetchFirstColumn('SELECT 1 FROM cyllene_translation_suggestion WHERE scope = ?', [self::BULK_TEST_CHANNEL]));
        self::assertCount(1, $connection->fetchFirstColumn('SELECT 1 FROM cyllene_translation_generation_log WHERE scope = ?', [self::BULK_TEST_CHANNEL]));
        self::assertSame('Cockpit', $this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::OTHER_TEST_CHANNEL)?->getValue());
    }

    public function testAnImportSkipsARowInALocaleItsChannelDoesNotExpose(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->createTestChannel($entityManager);

        /** @var RepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        /** @var RepositoryInterface<LocaleInterface> $locales */
        $locales = self::getContainer()->get('sylius.repository.locale');
        $channel = $channels->findOneBy(['code' => self::BULK_TEST_CHANNEL]);
        $english = $locales->findOneBy(['code' => 'en_US']);
        self::assertNotNull($channel);
        self::assertNotNull($english);
        $channel->addLocale($english);
        $entityManager->flush();

        $token = $this->transferToken();
        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nen_US,messages,app.dashboard,Cockpit," . self::BULK_TEST_CHANNEL . "\nfr_FR,messages,app.dashboard,Pilotage," . self::BULK_TEST_CHANNEL . "\n");

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        $this->client->followRedirect();
        self::assertStringContainsString(
            \sprintf('row 3 — app.dashboard: scope "%s" does not expose locale "fr_FR"', self::BULK_TEST_CHANNEL),
            html_entity_decode((string) $this->client->getResponse()->getContent()),
        );

        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        self::assertResponseRedirects();
        self::assertSame('Cockpit', $this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'en_US', self::BULK_TEST_CHANNEL)?->getValue());
        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::BULK_TEST_CHANNEL));
    }

    public function testGoingBackToAnInheritedOverrideIsNotCalledAReturnToTheFile(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->createTestChannel($entityManager);

        /** @var TranslationManagerInterface $manager */
        $manager = self::getContainer()->get(TranslationManagerInterface::class);
        // The global override reads like the file: the channel entry still follows it, not the file.
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Tableau de bord');
        $manager->saveOverride('app.dashboard', 'messages', 'fr_FR', 'Pilotage', null, self::BULK_TEST_CHANNEL);

        $this->client->request('POST', '/admin/translations/save', ['_token' => $this->transferToken(), 'key' => 'app.dashboard', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => self::BULK_TEST_CHANNEL, 'value' => 'Tableau de bord']);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Same as the value the entry inherits', $content);
        self::assertStringNotContainsString('Same as the file value', $content);
        self::assertNull($this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::BULK_TEST_CHANNEL));
    }

    public function testAStagedImportWhoseContentExpiredIsRefused(): void
    {
        $token = $this->transferToken();

        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\n");

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects('/admin/translations');

        // The pool dropped the content (its lifetime ran out, or the cache was cleared by a
        // deployment) while the preview was still in the session.
        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get(CylleneDigitalSyliusAiTranslationExtension::IMPORT_STAGING_POOL);
        $pool->clear();

        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('The staged import has expired', (string) $this->client->getResponse()->getContent());
        self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));
    }

    public function testAValidFileTheCacheRefusesIsNotReportedAsInvalid(): void
    {
        // A pool that keeps nothing: an item over the backend's size limit, a cache that is down.
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(static fn (string $key) => (new ArrayAdapter())->getItem($key));
        $pool->method('save')->willReturn(false);

        /** @var OverrideImporter $importer */
        $importer = self::getContainer()->get(OverrideImporter::class);
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $file = $this->csvUpload('overrides.csv', "locale,catalogue,translation_key,value,scope\nfr_FR,messages,app.welcome,\"Bienvenue à toi\",\n");
        $stager = new ImportStager($importer, $pool);

        self::assertSame(ImportRefusal::StagingFailed, $stager->stage($request, $file)->refusal());
        self::assertNull($stager->stagedPreview($request));
    }

    public function testAnUnsupportedUploadIsRejectedWithoutStaging(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('#translation-edit-form input[name=_token]')->attr('value');

        $path = tempnam(sys_get_temp_dir(), 'ai_import_test_');
        file_put_contents((string) $path, '<?php // not a translation file');
        $file = new UploadedFile((string) $path, 'evil.php', 'application/x-httpd-php', null, true);

        $this->client->request('POST', '/admin/translations/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('File rejected', (string) $this->client->getResponse()->getContent());

        // Nothing staged: the confirm path refuses instead of writing.
        $this->client->request('POST', '/admin/translations/import/confirm', ['_token' => $token]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('The staged import has expired', (string) $this->client->getResponse()->getContent());
    }

    public function testPurgingOrphansDeletesOnlyTheOverridesWhoseFilesAreGone(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // A live override and an orphan one (its catalogue has no files in the test app).
        $entityManager->persist((new TranslationOverride('app.welcome', 'messages', 'fr_FR'))->setValue('Live'));
        $entityManager->persist((new TranslationOverride('old.key', 'gone/Old/messages', 'fr_FR'))->setValue('Orphan'));
        $entityManager->flush();

        $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        // The dashboard counts the orphan without the purge button deleting anything yet.
        self::assertStringContainsString('orphan', (string) $this->client->getResponse()->getContent());

        $this->client->request('POST', '/admin/translations/purge-orphans', ['_token' => $this->transferToken()]);
        self::assertResponseRedirects('/admin/translations');
        $this->client->followRedirect();
        self::assertStringContainsString('1 orphan override deleted.', (string) $this->client->getResponse()->getContent());

        self::assertSame('Live', $this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR')?->getValue());
        self::assertNull($this->overrideRepository()->findOneByKey('old.key', 'gone/Old/messages', 'fr_FR'));
    }

    public function testAnActionOfTheCatalogueScreenReturnsToTheChannelItCameFrom(): void
    {
        $this->client->request('POST', '/admin/translations/purge-orphans', ['_token' => $this->transferToken()], [], [
            'HTTP_REFERER' => 'http://localhost/admin/translations?scope=B2B_WEB&page=2',
        ]);

        self::assertResponseRedirects('/admin/translations?scope=B2B_WEB&page=2');

        // Another screen's address is not followed: only this one's query string is reused.
        $this->client->request('POST', '/admin/translations/purge-orphans', ['_token' => $this->transferToken()], [], [
            'HTTP_REFERER' => 'http://localhost/admin/products?scope=B2B_WEB',
        ]);

        self::assertResponseRedirects('/admin/translations');
    }

    public function testAnActionWithoutARefererReturnsToTheChannelOfItsAddress(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->createTestChannel($entityManager);

        // Every form of the catalogue screen carries the channel being browsed in its address:
        // the one place PHP leaves alone when it empties an upload over post_max_size.
        $crawler = $this->client->request('GET', '/admin/translations', ['scope' => self::BULK_TEST_CHANNEL]);
        $query = '?scope=' . self::BULK_TEST_CHANNEL;
        self::assertStringEndsWith($query, (string) $crawler->filter('#import-overrides-modal form')->attr('action'));
        self::assertStringEndsWith($query, (string) $crawler->filter('#translation-edit-form')->attr('action'));
        self::assertCount(0, $crawler->filter('#import-overrides-modal input[name="scope"]'));

        $token = $this->transferToken();

        foreach (['purge-orphans', 'import', 'import/confirm', 'import/cancel'] as $action) {
            $this->client->request('POST', '/admin/translations/' . $action . $query, ['_token' => $token], [], ['HTTP_REFERER' => '']);
            self::assertResponseRedirects('/admin/translations' . $query, null, $action);
        }

        // A save or a reset the plugin refuses goes back to that channel too.
        foreach (['save', 'reset'] as $action) {
            $this->client->request('POST', '/admin/translations/' . $action . $query, ['_token' => $token, 'scope' => 'NOPE'], [], ['HTTP_REFERER' => '']);
            self::assertResponseRedirects('/admin/translations' . $query, null, $action);
        }

        if (0 !== ini_parse_quantity((string) \ini_get('post_max_size'))) {
            $this->client->request('POST', '/admin/translations/import' . $query, [], [], ['CONTENT_LENGTH' => (string) \PHP_INT_MAX, 'HTTP_REFERER' => '']);
            self::assertResponseRedirects('/admin/translations' . $query);
        }

        // A channel that does not exist is the global view, not a broken address.
        $this->client->request('POST', '/admin/translations/purge-orphans?scope=NOPE', ['_token' => $token], [], ['HTTP_REFERER' => '']);
        self::assertResponseRedirects('/admin/translations');

        // A scope that is not a string is refused before anything is written, not after.
        $entityManager->persist((new TranslationOverride('old.key', 'gone/Old/messages', 'fr_FR'))->setValue('Orphan'));
        $entityManager->flush();
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/admin/translations/purge-orphans?scope[]=x', ['_token' => $token]);
        self::assertResponseStatusCodeSame(400);
        self::assertNotNull($this->overrideRepository()->findOneByKey('old.key', 'gone/Old/messages', 'fr_FR'));
    }

    public function testResettingARowWithoutAnOverrideDoesNotClaimADeletion(): void
    {
        $row = ['_token' => $this->transferToken(), 'key' => 'app.welcome', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => ''];

        $this->client->request('POST', '/admin/translations/reset', $row);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'Nothing to delete');

        $this->client->request('POST', '/admin/translations/save', $row + ['value' => 'Bienvenue']);
        $this->client->followRedirect();
        $this->client->request('POST', '/admin/translations/reset', $row);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Override deleted');
    }

    public function testTheBulkActionsActOnTheSelectedPendingSuggestionsOnly(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $first = new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue A', 'Welcome to the shop', 'en_US', 'stub', 0.9);
        $second = new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau B', 'Dashboard', 'en_US', 'stub', 0.8);
        $entityManager->persist($first);
        $entityManager->persist($second);
        $entityManager->flush();

        // The bulk action bar (and its CSRF token) renders once the grid has rows.
        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('form[action$="approve-bulk"] input[name=_token]')->attr('value');

        $this->client->request('POST', '/admin/translations/suggestions/approve-bulk', [
            '_token' => $token,
            'ids' => [$first->getId(), $second->getId()],
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('2 suggestions approved', (string) $this->client->getResponse()->getContent());

        /** @var TranslationSuggestionRepository $suggestions */
        $suggestions = self::getContainer()->get(TranslationSuggestionRepository::class);
        self::assertTrue($suggestions->find($first->getId())?->isApproved());
        self::assertTrue($suggestions->find($second->getId())?->isApproved());
        self::assertSame('Bienvenue A', $this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR')?->getValue());
        self::assertSame('Tableau B', $this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR')?->getValue());

        // A fresh pending row: the bulk reject deletes it.
        $third = new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue C', 'Welcome to the shop', 'en_US', 'stub', 0.7);
        $entityManager->persist($third);
        $entityManager->flush();

        $this->client->request('GET', '/admin/translations/suggestions');
        $this->client->request('POST', '/admin/translations/suggestions/reject-bulk', [
            '_token' => $token,
            'ids' => [$third->getId()],
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('1 suggestion rejected and deleted', (string) $this->client->getResponse()->getContent());

        self::assertNull($suggestions->find($third->getId()));
    }

    public function testTheBulkActionsStayPinnedToThePostedScope(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        // A real channel: the batch only acts for a scope that resolves to one.
        $this->createTestChannel($entityManager);

        $globalRow = new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue G', 'Welcome to the shop', 'en_US', 'stub', 0.9);
        $channelRow = new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau H', 'Dashboard', 'en_US', 'stub', 0.8, self::BULK_TEST_CHANNEL);
        $entityManager->persist($globalRow);
        $entityManager->persist($channelRow);
        $entityManager->flush();

        /** @var TranslationSuggestionRepository $suggestions */
        $suggestions = $container->get(TranslationSuggestionRepository::class);

        // The channel view's bulk form carries the channel's scope in its hidden input.
        $crawler = $this->client->request('GET', '/admin/translations/suggestions', ['scope' => self::BULK_TEST_CHANNEL]);
        self::assertResponseIsSuccessful();
        $scopeInput = $crawler->filter('form[action$="approve-bulk"] input[name=scope]');
        self::assertCount(1, $scopeInput);
        self::assertSame(self::BULK_TEST_CHANNEL, (string) $scopeInput->attr('value'));
        $token = (string) $crawler->filter('form[action$="approve-bulk"] input[name=_token]')->attr('value');

        // Pinned to the channel, the batch cannot touch the global rows.
        $this->client->request('POST', '/admin/translations/suggestions/approve-bulk', [
            '_token' => $token,
            'scope' => self::BULK_TEST_CHANNEL,
            'ids' => [$globalRow->getId()],
        ]);
        self::assertResponseRedirects();
        self::assertTrue($suggestions->find($globalRow->getId())?->isPending());
        self::assertNull($this->overrideRepository()->findOneByKey('app.welcome', 'messages', 'fr_FR'));

        // Pinned to the global view, the batch cannot touch the channel's rows.
        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('form[action$="reject-bulk"] input[name=_token]')->attr('value');

        $this->client->request('POST', '/admin/translations/suggestions/reject-bulk', [
            '_token' => $token,
            'scope' => '',
            'ids' => [$channelRow->getId()],
        ]);
        self::assertResponseRedirects();
        self::assertTrue($suggestions->find($channelRow->getId())?->isPending());

        // An unknown channel code pins the batch to nothing.
        $this->client->request('POST', '/admin/translations/suggestions/approve-bulk', [
            '_token' => $token,
            'scope' => 'NO_SUCH_CHANNEL',
            'ids' => [$globalRow->getId(), $channelRow->getId()],
        ]);
        self::assertResponseRedirects();
        self::assertTrue($suggestions->find($globalRow->getId())?->isPending());
        self::assertTrue($suggestions->find($channelRow->getId())?->isPending());

        // Same scope as the rows: the batch acts (the legitimate path through a channel).
        $this->client->request('POST', '/admin/translations/suggestions/approve-bulk', [
            '_token' => $token,
            'scope' => self::BULK_TEST_CHANNEL,
            'ids' => [$channelRow->getId()],
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('1 suggestion approved', (string) $this->client->getResponse()->getContent());
        self::assertTrue($suggestions->find($channelRow->getId())?->isApproved());
        self::assertSame('Tableau H', $this->overrideRepository()->findOneByKey('app.dashboard', 'messages', 'fr_FR', self::BULK_TEST_CHANNEL)?->getValue());
    }

    public function testTheSuggestionScreenShowsTheLastRunsOfTheBrowsedChannelFailuresIncluded(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_generation_log');
        $entityManager->persist(GenerationLog::success('messages', 'fr_FR', 'en_US', 'stub', 3));
        $entityManager->persist(GenerationLog::failure('shop/Product/messages', 'fr_FR', 'en_US', 'stub', 'Provider answered 401'));
        $entityManager->persist(GenerationLog::success('messages', 'fr_FR', 'en_US', 'stub', 7, 'ANOTHER_CHANNEL'));
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();

        $journal = $crawler->filter('[data-test-generation-journal]');
        self::assertCount(1, $journal);
        // The global view lists its own runs, the latest first, not another channel's.
        self::assertCount(2, $journal->filter('tbody tr'));
        self::assertStringContainsString('Provider answered 401', $journal->filter('tbody tr')->eq(0)->text());
        self::assertStringContainsString('3 suggestions', $journal->filter('tbody tr')->eq(1)->text());
        self::assertStringNotContainsString('7 suggestions', $journal->text());

        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_generation_log');
    }

    public function testTheEstimateRefusesCoordinatesTheHostDoesNotExpose(): void
    {
        // array_merge, not `+`: the union operator would keep the valid target locale.
        $valid = ['source_locale' => 'en_US', 'target_locale' => 'fr_FR'];

        $cases = [
            [array_merge($valid, ['target_locale' => 'xx_XX']), 'Unknown locale'],
            [['source_locale' => 'fr_FR', 'target_locale' => 'fr_FR'], 'Pick two different locales'],
            [array_merge($valid, ['catalogue' => 'gone/Old/messages']), 'Unknown catalogue'],
            [array_merge($valid, ['provider' => 'ghost']), 'Unknown provider'],
            [array_merge($valid, ['scope' => 'NO_SUCH_CHANNEL']), 'Unknown channel'],
        ];

        foreach ($cases as [$parameters, $expected]) {
            $this->client->request('GET', '/admin/translations/suggestions/estimate', $parameters);
            self::assertResponseStatusCodeSame(422);

            /** @var array{message: string} $body */
            $body = json_decode((string) $this->client->getResponse()->getContent(), true);
            self::assertStringContainsString($expected, $body['message']);
        }
    }

    public function testTheEstimateAnswersTheVolumeForValidCoordinates(): void
    {
        $this->client->request('GET', '/admin/translations/suggestions/estimate', [
            'source_locale' => 'en_US',
            'target_locale' => 'fr_FR',
        ]);

        self::assertResponseIsSuccessful();

        /** @var array{message: string} $body */
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        // app.welcome exists in the English files and is missing in French: at least one key.
        self::assertStringContainsString('source characters', $body['message']);
    }

    public function testTheEstimateReadsTheFormCheckboxesAsBooleans(): void
    {
        $coordinates = ['source_locale' => 'en_US', 'target_locale' => 'fr_FR', 'catalogue' => 'messages'];

        // What the form's script sends: '0' / '1', never a boolean.
        $this->client->request('GET', '/admin/translations/suggestions/estimate', $coordinates + ['all' => '0', 'retry_errors' => '0']);
        self::assertResponseIsSuccessful();
        $onlyMissing = (string) $this->client->getResponse()->getContent();

        // "Every key" re-translates app.dashboard too, which French already has.
        $this->client->request('GET', '/admin/translations/suggestions/estimate', $coordinates + ['all' => '1', 'retry_errors' => '1']);
        self::assertResponseIsSuccessful();
        self::assertNotSame($onlyMissing, (string) $this->client->getResponse()->getContent());
    }

    public function testEmptyingAValueTheFilesHoldIsRefused(): void
    {
        $row = ['_token' => $this->transferToken(), 'key' => 'app.dashboard', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => ''];

        // Neither on a row without an override...
        $this->client->request('POST', '/admin/translations/save', $row + ['value' => '  ']);
        self::assertResponseRedirects();
        self::assertNull($this->overrideRepository()->findOneBy(['key' => 'app.dashboard']));

        // ...nor over the one it holds, which stays as it was.
        $this->client->request('POST', '/admin/translations/save', $row + ['value' => 'Tableau']);
        $this->client->request('POST', '/admin/translations/save', $row + ['value' => '']);
        self::assertResponseRedirects();
        self::assertSame('Tableau', $this->overrideRepository()->findOneBy(['key' => 'app.dashboard'])?->getValue());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'an empty value is not stored');
    }

    public function testAValueOfTheWrongTypeGetsSymfonysOwnAnswer(): void
    {
        // Only a hand-built request sends these: no flash, the mapping refuses them itself.
        $this->client->catchExceptions(true);

        $this->client->request('POST', '/admin/translations/save', ['_token' => $this->transferToken(), 'key' => ['x']]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/admin/translations/suggestions/estimate', ['all' => 'maybe']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testASuggestionRouteOnlyTakesANumericId(): void
    {
        $this->client->catchExceptions(true);

        $this->client->request('POST', '/admin/translations/suggestions/abc/approve', ['_token' => $this->suggestionToken()]);
        self::assertResponseStatusCodeSame(404);

        $this->client->request('POST', '/admin/translations/suggestions/999999999/approve', ['_token' => $this->suggestionToken()]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheGenerateRefusesBrokenCoordinatesWithoutDispatching(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('form[action$="/generate"] input[name=_token]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $before = \count($entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM messenger_messages'));

        $this->client->request('POST', '/admin/translations/suggestions/generate', [
            '_token' => $token,
            'source_locale' => 'en_US',
            'target_locale' => 'xx_XX',
            'catalogue' => 'messages',
            'scope' => '',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('Unknown locale', (string) $this->client->getResponse()->getContent());

        // The refusal happened BEFORE the dispatch: no billed run was queued.
        self::assertCount($before, $entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM messenger_messages'));
    }

    public function testTheGenerateDispatchesARunForValidCoordinates(): void
    {
        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        $token = (string) $crawler->filter('form[action$="/generate"] input[name=_token]')->attr('value');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $before = \count($entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM messenger_messages'));

        $this->client->request('POST', '/admin/translations/suggestions/generate', [
            '_token' => $token,
            'source_locale' => 'en_US',
            'target_locale' => 'fr_FR',
            'catalogue' => 'messages',
            'scope' => '',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertStringContainsString('Generation queued (1 catalogue)', (string) $this->client->getResponse()->getContent());

        self::assertSame($before + 1, \count($entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM messenger_messages')));

        // The worker handles the run on the bus stamped here: the plugin's own, never
        // Sylius' transactional default bus (a failing chunk would roll back the
        // suggestions already billed).
        /** @var ListableReceiverInterface $transport */
        $transport = self::getContainer()->get('messenger.transport.cyllene_ai_translation');
        $envelope = $transport->find($entityManager->getConnection()->fetchOne('SELECT MAX(id) FROM messenger_messages'));
        self::assertSame(CylleneDigitalSyliusAiTranslationExtension::GENERATION_BUS, $envelope?->last(BusNameStamp::class)?->getBusName());
    }

    public function testStateChangingActionsWithoutTheirCsrfTokenAreRefused(): void
    {
        // The AccessDeniedException must become a 403 response, not a thrown exception.
        $this->client->catchExceptions(true);

        $row = ['key' => 'app.welcome', 'catalogue' => 'messages', 'locale' => 'fr_FR', 'scope' => '', 'value' => 'Forged'];

        $this->client->request('POST', '/admin/translations/save', $row);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/translations/save', $row + ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnAdminWithoutThePluginRoleGetsAFourOThree(): void
    {
        // The AccessDeniedException must become a 403 response, not a thrown exception.
        $this->client->catchExceptions(true);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        /** @var FactoryInterface<AdminUserInterface> $adminFactory */
        $adminFactory = self::getContainer()->get('sylius.factory.admin_user');
        $admin = $adminFactory->createNew();
        $admin->setUsername('ai_action_norole');
        $admin->setEmail('ai_action_norole@example.com');
        $admin->setPassword('not-used-logged-in-programmatically');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);

        // No role at all: not even the role_hierarchy grant of ROLE_AI_TRANSLATION_MANAGE,
        // because that one hangs on ROLE_ADMINISTRATION_ACCESS.
        foreach ($admin->getRoles() as $role) {
            $admin->removeRole($role);
        }

        $entityManager->persist($admin);
        $entityManager->flush();

        $this->client->loginUser($admin, 'admin');
        $this->client->request('GET', '/admin/translations');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheAdminMenuCarriesThePluginEntry(): void
    {
        // Without any channel, Sylius' dashboard redirects to the channel creation form.
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->createTestChannel($entityManager);

        // The dashboard has no plugin content of its own: the links below can only
        // come from the sidebar entry the plugin's listener adds to the menu.
        $this->client->request('GET', '/admin/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('/admin/translations', $content);
        self::assertStringContainsString('/admin/translations/suggestions', $content);
    }

    /** A test channel (removed by tearDown): code, name, default locale and base currency. */
    private function createTestChannel(EntityManagerInterface $entityManager, string $code = self::BULK_TEST_CHANNEL): void
    {
        $container = self::getContainer();

        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $container->get('sylius.factory.channel');
        $channel = $channelFactory->createNew();
        $channel->setCode($code);
        $channel->setName('Bulk scope test ' . $code);

        /** @var RepositoryInterface<LocaleInterface> $localeRepository */
        $localeRepository = $container->get('sylius.repository.locale');
        $channel->setDefaultLocale($localeRepository->findOneBy(['code' => 'en_US']));

        /** @var RepositoryInterface<CurrencyInterface> $currencyRepository */
        $currencyRepository = $container->get('sylius.repository.currency');
        $channel->setBaseCurrency($currencyRepository->findOneBy(['code' => 'EUR']));

        $entityManager->persist($channel);
        $entityManager->flush();
    }

    /** The transfer intention token, from the edit modal form of the catalogue screen. */
    private function transferToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/translations');

        return (string) $crawler->filter('#translation-edit-form input[name=_token]')->attr('value');
    }

    /** The suggestion screen's intention token, from its review modal form. */
    private function suggestionToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/translations/suggestions');

        return (string) $crawler->filter('#suggestion-review-form input[name=_token]')->attr('value');
    }

    private function overrideRepository(): TranslationOverrideRepository
    {
        /** @var TranslationOverrideRepository $repository */
        $repository = self::getContainer()->get(TranslationOverrideRepository::class);

        return $repository;
    }

    private function csvUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ai_import_test_');
        file_put_contents((string) $path, $content);

        return new UploadedFile((string) $path, $name, 'text/csv', null, true);
    }
}
