<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\EventListener;

use CylleneDigital\AiTranslationBundle\Entity\GenerationLog;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use CylleneDigital\AiTranslationBundle\Scope\ScopePurger;
use CylleneDigital\SyliusAiTranslationPlugin\EventListener\ChannelDeletionListener;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * The channel deletion listener: once Sylius has deleted a channel, the engine's data for
 * it goes too - overrides, suggestions, prompt context - and its journal stays. The event
 * is dispatched the way Sylius' channel resource controller dispatches it, after the
 * deletion is flushed (subject = the deleted channel).
 */
final class ChannelDeletionListenerTest extends KernelTestCase
{
    use SyncsTestSchema;

    private const string CHANNEL_CODE = 'AI_TEST_DELETION';

    /** A second channel, whose data the purge of the first one must leave alone. */
    private const string OTHER_CHANNEL_CODE = 'AI_TEST_DELETION_OTHER';

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::syncTestSchema();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The kernel is down after the parent: a dedicated boot for the cleanup, the
        // same way the test's own setUp boots it.
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $scopes = [self::CHANNEL_CODE, self::OTHER_CHANNEL_CODE];
        $connection->executeStatement('DELETE FROM cyllene_translation_scope_parameters WHERE scope IN (?, ?)', $scopes);
        $connection->executeStatement('DELETE FROM cyllene_translation_generation_log WHERE scope IN (?, ?)', $scopes);
        // The overrides and suggestions, cache.app with them: rows deleted behind the engine's back.
        self::deleteOverridesAndSuggestions();
        $connection->executeStatement('DELETE FROM sylius_channel WHERE code IN (?, ?)', $scopes);

        self::ensureKernelShutdown();
    }

    public function testADeletedChannelTakesItsDataAlongAndKeepsItsJournal(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        $channel = $this->createChannel(self::CHANNEL_CODE, 'Deletion test');
        $this->createChannel(self::OTHER_CHANNEL_CODE, 'Deletion test (other)');

        /** @var TranslationManagerInterface $translationManager */
        $translationManager = $container->get(TranslationManagerInterface::class);
        $translationManager->saveOverride('app.welcome', 'messages', 'fr_FR', 'Bienvenue (canal)', null, self::CHANNEL_CODE);
        $translationManager->saveOverride('app.welcome', 'messages', 'fr_FR', 'Bienvenue (autre canal)', null, self::OTHER_CHANNEL_CODE);

        /** @var ScopeParametersManager $scopeParameters */
        $scopeParameters = $container->get(ScopeParametersManager::class);
        $scopeParameters->setPromptContext(self::CHANNEL_CODE, 'Terse wording for the test channel.');

        $entityManager->persist(new TranslationSuggestion('app.dashboard', 'messages', 'fr_FR', 'Tableau de bord', 'Dashboard', 'en_US', 'stub', 0.9, self::CHANNEL_CODE));
        $entityManager->persist(GenerationLog::success('messages', 'fr_FR', 'en_US', 'stub', 1, self::CHANNEL_CODE));
        $entityManager->flush();

        // As Sylius' resource controller does it: the deletion is flushed, then post_delete.
        /** @var RepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = $container->get('sylius.repository.channel');
        $channelRepository->remove($channel);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->dispatch(new ResourceControllerEvent($channel), 'sylius.channel.post_delete');
        $entityManager->clear();

        $connection = $entityManager->getConnection();
        self::assertNull($translationManager->getOverrideValue('app.welcome', 'messages', 'fr_FR', self::CHANNEL_CODE));
        self::assertNull($scopeParameters->getPromptContext(self::CHANNEL_CODE));
        self::assertCount(0, $connection->fetchFirstColumn('SELECT 1 FROM cyllene_translation_suggestion WHERE scope = ?', [self::CHANNEL_CODE]));
        self::assertCount(1, $connection->fetchFirstColumn('SELECT 1 FROM cyllene_translation_generation_log WHERE scope = ?', [self::CHANNEL_CODE]));

        self::assertSame('Bienvenue (autre canal)', $translationManager->getOverrideValue('app.welcome', 'messages', 'fr_FR', self::OTHER_CHANNEL_CODE));
    }

    public function testThePurgeRunsAfterTheHostsOwnListeners(): void
    {
        /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $plugin = self::getContainer()->get('cyllene_digital_sylius_ai_translation.scope.channel_deletion_listener');
        // A host listener registered at a low priority of its own still runs before the purge.
        $probe = static function (): void {};
        $dispatcher->addListener('sylius.channel.post_delete', $probe, -1000);

        $order = [];
        foreach ($dispatcher->getListeners('sylius.channel.post_delete') as $listener) {
            $order[] = match (true) {
                $listener === $plugin, \is_array($listener) && $listener[0] === $plugin => 'plugin',
                $listener === $probe => 'probe',
                default => 'sylius',
            };
        }
        $dispatcher->removeListener('sylius.channel.post_delete', $probe);

        // Sylius' own listener of that event flushes: a purge that fails must not close the
        // entity manager before it.
        self::assertSame('plugin', $order[array_key_last($order)] ?? null);
        self::assertContains('sylius', $order);
        self::assertContains('probe', $order);
    }

    public function testAPurgeThatFailsIsLoggedAndLeavesTheDeletionAlone(): void
    {
        $logger = self::spyLogger();

        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = self::getContainer()->get('sylius.factory.channel');
        $channel = $channelFactory->createNew();
        // The engine refuses to purge the global scope '': the one failure a test can cause,
        // the purger being final - a database that fails mid-purge is not reproduced.
        $channel->setCode('');

        self::listener($logger)(new ResourceControllerEvent($channel));

        self::assertCount(1, $logger->errors);
        self::assertStringStartsWith('error: ', $logger->errors[0]);
    }

    public function testTheListenerIgnoresSubjectsItCannotHandle(): void
    {
        $logger = self::spyLogger();
        $listener = self::listener($logger);

        // Called directly (not dispatched): Sylius' own listeners on the same event
        // expect a channel, so the plugin's guard is exercised on its own.
        $listener(new ResourceControllerEvent(new \stdClass()));
        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = self::getContainer()->get('sylius.factory.channel');
        $listener(new ResourceControllerEvent($channelFactory->createNew()));

        self::assertSame([], $logger->errors);
    }

    private static function listener(LoggerInterface $logger): ChannelDeletionListener
    {
        /** @var ScopePurger $scopePurger */
        $scopePurger = self::getContainer()->get(ScopePurger::class);

        return new ChannelDeletionListener($scopePurger, $logger);
    }

    /**
     * @return AbstractLogger&object{errors: list<string>}
     */
    private static function spyLogger(): AbstractLogger
    {
        return new class() extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->errors[] = (\is_string($level) ? $level : '?') . ': ' . $message;
            }
        };
    }

    private function createChannel(string $code, string $name): ChannelInterface
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $container->get('sylius.factory.channel');
        $channel = $channelFactory->createNew();
        $channel->setCode($code);
        $channel->setName($name);
        $channel->setEnabled(true);

        /** @var RepositoryInterface<LocaleInterface> $localeRepository */
        $localeRepository = $container->get('sylius.repository.locale');
        $locale = $localeRepository->findOneBy(['code' => 'en_US']);
        $channel->setDefaultLocale($locale);

        /** @var RepositoryInterface<CurrencyInterface> $currencyRepository */
        $currencyRepository = $container->get('sylius.repository.currency');
        $currency = $currencyRepository->findOneBy(['code' => 'EUR']);
        $channel->setBaseCurrency($currency);

        $entityManager->persist($channel);
        $entityManager->flush();

        return $channel;
    }
}
