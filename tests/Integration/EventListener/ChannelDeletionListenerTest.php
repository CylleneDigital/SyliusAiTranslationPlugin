<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Integration\EventListener;

use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use CylleneDigital\SyliusAiTranslationPlugin\EventListener\ChannelDeletionListener;
use Doctrine\ORM\EntityManagerInterface;
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
 * The channel deletion listener: a deleted channel takes its per-scope settings
 * (the AI prompt context) with it, staged on `sylius.channel.pre_delete` and flushed
 * with the deletion. The event is dispatched the way Sylius' channel resource
 * controller dispatches it (subject = the channel being deleted).
 */
final class ChannelDeletionListenerTest extends KernelTestCase
{
    use SyncsTestSchema;

    private const string CHANNEL_CODE = 'AI_TEST_DELETION';

    /** A second enabled channel: Sylius stops the deletion of the last enabled one. */
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
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_scope_parameters WHERE scope = ?', [self::CHANNEL_CODE]);
        $entityManager->getConnection()->executeStatement('DELETE FROM sylius_channel WHERE code IN (?, ?)', [self::CHANNEL_CODE, self::OTHER_CHANNEL_CODE]);

        self::ensureKernelShutdown();
    }

    public function testThePreDeleteEventRemovesTheChannelScopeSettings(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        // Sylius' own pre_delete listener stops the event for the last enabled channel
        // (the deletion is refused, the settings rightly stay): a second one makes the
        // test channel deletable, as on any real shop with several channels.
        $channel = $this->createChannel(self::CHANNEL_CODE, 'Deletion test');
        $this->createChannel(self::OTHER_CHANNEL_CODE, 'Deletion test (other)');

        /** @var ScopeParametersManager $scopeParameters */
        $scopeParameters = $container->get(ScopeParametersManager::class);
        $scopeParameters->setPromptContext(self::CHANNEL_CODE, 'Terse wording for the test channel.');
        self::assertSame('Terse wording for the test channel.', $scopeParameters->getPromptContext(self::CHANNEL_CODE));

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->dispatch(new ResourceControllerEvent($channel), 'sylius.channel.pre_delete');

        // The removal is staged (flush: false) and lands with the deletion itself.
        $entityManager->flush();
        $entityManager->clear();

        self::assertNull($scopeParameters->getPromptContext(self::CHANNEL_CODE));
        self::assertCount(0, $entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM cyllene_translation_scope_parameters WHERE scope = ?', [self::CHANNEL_CODE]));
    }

    public function testTheListenerIgnoresSubjectsItCannotHandle(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_scope_parameters');

        /** @var ChannelDeletionListener $listener */
        $listener = $container->get('cyllene_digital_sylius_ai_translation.scope.channel_deletion_listener');

        // Called directly (not dispatched): Sylius' own listeners on the same event
        // expect a channel, so the plugin's guard is exercised on its own.
        $listener(new ResourceControllerEvent(new \stdClass()));
        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $container->get('sylius.factory.channel');
        $listener(new ResourceControllerEvent($channelFactory->createNew()));
        $entityManager->flush();

        self::assertCount(0, $entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM cyllene_translation_scope_parameters'));
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
