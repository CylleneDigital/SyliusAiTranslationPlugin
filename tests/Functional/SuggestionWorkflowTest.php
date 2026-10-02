<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Functional;

use CylleneDigital\AiTranslationBundle\Entity\TranslationOverride;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManager;
use CylleneDigital\AiTranslationBundle\Repository\TranslationOverrideRepository;
use CylleneDigital\AiTranslationBundle\Repository\TranslationSuggestionRepository;
use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use CylleneDigital\AiTranslationBundle\Suggestion\TranslationAiService;
use CylleneDigital\SyliusAiTranslationPlugin\Form\Extension\ChannelTypeExtension;
use CylleneDigital\SyliusAiTranslationPlugin\Grid\SuggestionGrid;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelType;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Component\Grid\View\GridViewFactoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * End-to-end on a real database (the test application's `_test` database): catalogue
 * discovery from the host translations/ files (tests/TestApplication/translations),
 * then the suggestion → approve → override → translation-applied chain.
 */
final class SuggestionWorkflowTest extends KernelTestCase
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

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_suggestion');
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_override');
    }

    public function testCataloguesAreDiscoveredFromTheHostTranslationFiles(): void
    {
        /** @var TranslationManager $manager */
        $manager = self::getContainer()->get(TranslationManager::class);

        $identifiers = $manager->getCatalogueIdentifiers();

        self::assertContains('messages', $identifiers);
        self::assertContains('shop/Product/messages', $identifiers);
        self::assertContains('admin/Dashboard/flashes', $identifiers);

        $catalogue = $manager->getCatalogue('shop/Product/messages');
        self::assertNotNull($catalogue);
        self::assertSame('shop', $catalogue->category);
        self::assertSame('Product', $catalogue->domain);
        self::assertSame('messages', $catalogue->type);

        $translations = $manager->getTranslationsForCatalogue('messages', 'en_US');
        self::assertArrayHasKey('app.dashboard', $translations);
        self::assertSame('Dashboard', $translations['app.dashboard']['original']);

        // A key missing from the French files is reported as missing (AI suggestion input).
        $french = $manager->getTranslationsForCatalogue('messages', 'fr_FR');
        self::assertArrayNotHasKey('app.welcome', $french);
    }

    public function testApprovingASuggestionCreatesAnOverrideAppliedByTheTranslator(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var TranslationAiService $aiService */
        $aiService = $container->get(TranslationAiService::class);

        $suggestion = new TranslationSuggestion(
            'app.welcome',
            'messages',
            'fr_FR',
            'Bienvenue dans la boutique',
            'Welcome to the shop',
            'en_US',
            'stub',
            0.95,
        );
        $entityManager->persist($suggestion);
        $entityManager->flush();

        $aiService->approve($suggestion);

        self::assertTrue($suggestion->isApproved());
        self::assertSame('system', $suggestion->getReviewedBy());

        /** @var TranslationOverrideRepository $overrideRepository */
        $overrideRepository = $container->get(TranslationOverrideRepository::class);
        $override = $overrideRepository->findOneByKey('app.welcome', 'messages', 'fr_FR');

        self::assertInstanceOf(TranslationOverride::class, $override);
        self::assertSame('Bienvenue dans la boutique', $override->getValue());

        /** @var TranslatorInterface $translator */
        $translator = $container->get(TranslatorInterface::class);
        self::assertSame('Bienvenue dans la boutique', $translator->trans('app.welcome', [], 'messages', 'fr_FR'));
    }

    public function testApprovingAChannelSuggestionWritesTheOverrideInThatChannelOnly(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var TranslationAiService $aiService */
        $aiService = $container->get(TranslationAiService::class);

        $suggestion = new TranslationSuggestion(
            'app.welcome',
            'messages',
            'fr_FR',
            'Bienvenue chez nos pros',
            'Welcome to the shop',
            'en_US',
            'stub',
            0.95,
            'B2B',
        );
        $entityManager->persist($suggestion);
        $entityManager->flush();

        $aiService->approve($suggestion);

        /** @var TranslationOverrideRepository $overrideRepository */
        $overrideRepository = $container->get(TranslationOverrideRepository::class);

        $scoped = $overrideRepository->findOneByKey('app.welcome', 'messages', 'fr_FR', 'B2B');
        self::assertInstanceOf(TranslationOverride::class, $scoped);
        self::assertSame('Bienvenue chez nos pros', $scoped->getValue());
        self::assertSame('B2B', $scoped->getScope());

        // The global value is left untouched: a channel run fills that channel only.
        self::assertNull($overrideRepository->findOneByKey('app.welcome', 'messages', 'fr_FR'));

        /** @var TranslationSuggestionRepository $suggestionRepository */
        $suggestionRepository = $container->get(TranslationSuggestionRepository::class);
        self::assertSame(0, $suggestionRepository->countPendingFiltered(null, null, null, 'B2B'));
        self::assertSame(0, $suggestionRepository->countPendingFiltered(null, null, null, ''));
    }

    public function testRejectingASuggestionDeletesIt(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var TranslationAiService $aiService */
        $aiService = $container->get(TranslationAiService::class);

        $suggestion = new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome to the shop', 'en_US', 'stub', 0.5);
        $entityManager->persist($suggestion);
        $entityManager->flush();
        $id = $suggestion->getId();

        $aiService->reject($suggestion);
        $entityManager->clear();

        /** @var TranslationSuggestionRepository $suggestionRepository */
        $suggestionRepository = $container->get(TranslationSuggestionRepository::class);
        self::assertNull($suggestionRepository->find($id));
        self::assertSame(0, $suggestionRepository->countPendingFiltered(null, null, null, ''));

        /** @var TranslationOverrideRepository $overrideRepository */
        $overrideRepository = $container->get(TranslationOverrideRepository::class);
        self::assertNull($overrideRepository->findOneByKey('app.welcome', 'messages', 'fr_FR'));
    }

    public function testTheSuggestionGridListsThePendingRowsOfTheRequestedScope(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Global', 'Welcome', 'en_US', 'stub', 0.6));
        $entityManager->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Scoped', 'Welcome', 'en_US', 'stub', 0.9, 'B2B'));
        $entityManager->flush();

        /** @var GridProviderInterface $gridProvider */
        $gridProvider = $container->get(GridProviderInterface::class);

        /** @var GridViewFactoryInterface $viewFactory */
        $viewFactory = $container->get(GridViewFactoryInterface::class);

        // Nobody is logged in: the list is read-only, the decisions belong to who may review.
        self::assertSame([], $gridProvider->get(SuggestionGrid::NAME)->getActionGroups());

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $container->get('security.token_storage');
        $reviewer = new InMemoryUser('reviewer', null, [Permission::Review->role()]);
        $tokenStorage->setToken(new UsernamePasswordToken($reviewer, 'admin', $reviewer->getRoles()));

        // No request on the stack: the grid falls back to the global scope.
        $grid = $gridProvider->get(SuggestionGrid::NAME);
        self::assertSame([25, 50, 100], $grid->getLimits());
        self::assertTrue($grid->getField('confidence')->isSortable());
        self::assertSame(['approve', 'review', 'delete'], array_keys($grid->getActionGroup('item')->getActions()));
        self::assertSame(['approve', 'reject'], array_keys($grid->getActionGroup('bulk')->getActions()));

        /** @var \Pagerfanta\Pagerfanta<TranslationSuggestion> $data */
        $data = $viewFactory->create($grid, new Parameters())->getData();
        $rows = iterator_to_array($data->getCurrentPageResults());

        self::assertCount(1, $rows);
        self::assertSame('Global', $rows[0]->getSuggestedValue());
    }

    public function testTheChannelFormStoresThePromptContextAsScopeParameters(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('DELETE FROM cyllene_translation_scope_parameters');

        /** @var ScopeParametersManager $scopeParameters */
        $scopeParameters = $container->get(ScopeParametersManager::class);

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');

        /** @var FactoryInterface<ChannelInterface> $channelFactory */
        $channelFactory = $container->get('sylius.factory.channel');
        $channel = $channelFactory->createNew();
        $channel->setCode('TEST_AI_CTX');

        // Only the extension is under test: no CSRF, no channel validation (the channel
        // itself is never persisted here — its code is all the settings row needs).
        $options = ['csrf_protection' => false, 'validation_groups' => false];

        // The unmapped field is part of the channel form, empty for a channel without settings.
        $form = $formFactory->create(ChannelType::class, $channel, $options);
        self::assertTrue($form->has(ChannelTypeExtension::FIELD));
        self::assertNull($form->get(ChannelTypeExtension::FIELD)->getData());

        // Submitting the field stages the settings row: the controller's flush writes it.
        $form->submit([ChannelTypeExtension::FIELD => '  Wholesale buyers, terse wording.  '], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('Wholesale buyers, terse wording.', $scopeParameters->getPromptContext('TEST_AI_CTX'));

        // A new form for that channel is prefilled; blanking the field deletes the row.
        $form = $formFactory->create(ChannelType::class, $channel, $options);
        self::assertSame('Wholesale buyers, terse wording.', $form->get(ChannelTypeExtension::FIELD)->getData());
        $form->submit([ChannelTypeExtension::FIELD => ''], false);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $entityManager->flush();
        $entityManager->clear();

        self::assertNull($scopeParameters->getPromptContext('TEST_AI_CTX'));
        self::assertCount(0, $entityManager->getConnection()->fetchFirstColumn('SELECT 1 FROM cyllene_translation_scope_parameters'));
    }

    public function testRemovingTheOverrideFallsBackToTheFileValue(): void
    {
        $container = self::getContainer();

        /** @var TranslationManager $manager */
        $manager = $container->get(TranslationManager::class);
        $manager->saveOverride('app.dashboard', 'messages', 'en_US', 'Overridden');
        $manager->removeOverride('app.dashboard', 'messages', 'en_US');

        $translations = $manager->getTranslationsForCatalogue('messages', 'en_US');
        self::assertFalse($translations['app.dashboard']['hasOverride']);
        self::assertSame('Dashboard', $translations['app.dashboard']['original']);
    }
}
