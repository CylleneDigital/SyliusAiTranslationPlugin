<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use CylleneDigital\AiTranslationBundle\Entity\GenerationLog;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Sylius\Behat\Service\SecurityServiceInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * The state the scenarios start from. The translations themselves are the test
 * application's files (tests/TestApplication/translations): written in English, partly in
 * French - "app.dashboard" has both, "app.welcome" only the English.
 */
final class TranslationContext implements Context
{
    /**
     * @param FactoryInterface<AdminUserInterface> $adminFactory
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $adminFactory,
        private readonly SecurityServiceInterface $securityService,
        private readonly TranslationManagerInterface $translationManager,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Sylius' purge empties the tables behind the engine's back: the overrides it served in
     * the previous scenario would still be in its cache.
     */
    #[BeforeScenario]
    public function forgetTheCachedOverrides(): void
    {
        $this->cache->clear();
    }

    #[Given('I am logged in as an administrator managing the translations')]
    public function iAmLoggedInAsAnAdministratorManagingTheTranslations(): void
    {
        $this->logInWith([Permission::MANAGE_ROLE]);
    }

    #[Given('I am logged in as an administrator who may only :permission the translations')]
    public function iAmLoggedInAsAnAdministratorWhoMayOnly(string $permission): void
    {
        $this->logInWith([Permission::from('cyllene_digital_sylius_ai_translation.' . $permission)->role()]);
    }

    #[Given('I am logged in as an administrator without any role on the translations')]
    public function iAmLoggedInAsAnAdministratorWithoutAnyRoleOnTheTranslations(): void
    {
        $this->logInWith([]);
    }

    #[Given('the :key key of the :catalogue catalogue is overridden with :value in :locale')]
    public function theKeyIsOverriddenWith(string $key, string $catalogue, string $value, string $locale): void
    {
        $this->translationManager->saveOverride($key, $catalogue, $locale, $value);
    }

    #[Given('the :key key of the :catalogue catalogue is overridden with :value in :locale for the :channelCode channel')]
    public function theKeyIsOverriddenWithForTheChannel(string $key, string $catalogue, string $value, string $locale, string $channelCode): void
    {
        $this->translationManager->saveOverride($key, $catalogue, $locale, $value, null, $channelCode);
    }

    #[Given('the AI suggested :value for the :key key of the :catalogue catalogue in :locale')]
    public function theAiSuggested(string $value, string $key, string $catalogue, string $locale): void
    {
        $source = $this->translationManager->getOriginalValue($key, $catalogue, 'en_US') ?? $key;

        $this->entityManager->persist(new TranslationSuggestion($key, $catalogue, $locale, $value, $source, 'en_US', 'stub', 0.9));
        $this->entityManager->flush();
    }

    #[Given('the AI suggested :value for the :key key of the :catalogue catalogue in :locale for the :channelCode channel')]
    public function theAiSuggestedForTheChannel(string $value, string $key, string $catalogue, string $locale, string $channelCode): void
    {
        $source = $this->translationManager->getOriginalValue($key, $catalogue, 'en_US') ?? $key;

        $this->entityManager->persist(new TranslationSuggestion($key, $catalogue, $locale, $value, $source, 'en_US', 'stub', 0.9, $channelCode));
        $this->entityManager->flush();
    }

    /** What the engine stores before calling the provider: an errored row, marked in flight. */
    #[Given('the AI suggestion for the :key key of the :catalogue catalogue in :locale is still with the provider')]
    public function theAiSuggestionIsStillWithTheProvider(string $key, string $catalogue, string $locale): void
    {
        $source = $this->translationManager->getOriginalValue($key, $catalogue, 'en_US') ?? $key;

        $suggestion = TranslationSuggestion::failed($key, $catalogue, $locale, $source, 'en_US', 'stub', 'Interrupted before the reply was stored');
        $suggestion->setMetadata(['in_flight' => (new \DateTimeImmutable())->format(\DATE_ATOM)]);
        $this->entityManager->persist($suggestion);
        $this->entityManager->flush();
    }

    #[Given('a generation of the :catalogue catalogue into :locale produced :count suggestions')]
    public function aGenerationProducedSuggestions(string $catalogue, string $locale, int $count): void
    {
        $this->entityManager->persist(GenerationLog::success($catalogue, $locale, 'en_US', 'stub', $count));
        $this->entityManager->flush();
    }

    #[Given('a generation of the :catalogue catalogue into :locale failed with :error')]
    public function aGenerationFailedWith(string $catalogue, string $locale, string $error): void
    {
        $this->entityManager->persist(GenerationLog::failure($catalogue, $locale, 'en_US', 'stub', $error));
        $this->entityManager->flush();
    }

    /**
     * A regular Sylius admin (ROLE_ADMINISTRATION_ACCESS) holding exactly these roles of the
     * plugin: the test environment grants none by itself.
     *
     * @param list<string> $roles
     */
    private function logInWith(array $roles): void
    {
        $admin = $this->adminFactory->createNew();
        $admin->setUsername('translator');
        $admin->setEmail('translator@example.com');
        $admin->setPlainPassword('sylius');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);

        foreach ($roles as $role) {
            $admin->addRole($role);
        }

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->securityService->logIn($admin);
    }
}
