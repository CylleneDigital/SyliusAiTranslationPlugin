<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Functional;

use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\CylleneDigital\SyliusAiTranslationPlugin\SyncsTestSchema;

/**
 * One permission per kind of consequence (see {@see Permission}): what each role opens,
 * what it leaves closed, and what its holder is shown. Every admin here is a regular Sylius
 * admin (ROLE_ADMINISTRATION_ACCESS) holding exactly one role of the plugin.
 */
final class PermissionsTest extends WebTestCase
{
    use SyncsTestSchema;

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
        // An AccessDeniedException must become a 403 response, not a thrown exception.
        $this->client->catchExceptions(true);

        $connection = $this->entityManager()->getConnection();
        $connection->executeStatement('DELETE FROM cyllene_translation_suggestion');
        $connection->executeStatement('DELETE FROM cyllene_translation_override');
        $connection->executeStatement("DELETE FROM sylius_admin_user WHERE username LIKE 'ai_permission_%'");
    }

    /**
     * @return iterable<string, array{string, list<Permission>}>
     */
    public static function roles(): iterable
    {
        yield 'view' => ['ROLE_AI_TRANSLATION_VIEW', []];
        yield 'edit' => ['ROLE_AI_TRANSLATION_EDIT', [Permission::Edit]];
        yield 'import' => ['ROLE_AI_TRANSLATION_IMPORT', [Permission::Import]];
        yield 'review' => ['ROLE_AI_TRANSLATION_REVIEW', [Permission::Review]];
        yield 'generate' => ['ROLE_AI_TRANSLATION_GENERATE', [Permission::Generate]];
        yield 'manage' => [Permission::MANAGE_ROLE, [Permission::Edit, Permission::Import, Permission::Review, Permission::Generate]];
    }

    /**
     * @param list<Permission> $granted on top of the reading every role gives
     */
    #[DataProvider('roles')]
    public function testARoleOpensItsRoutesAndNoOther(string $role, array $granted): void
    {
        $this->client->loginUser($this->createAdmin($role), 'admin');

        foreach ($this->routes() as $name => [$permission, $method, $uri, $parameters]) {
            $open = Permission::View === $permission || \in_array($permission, $granted, true);

            self::assertSame(!$open, $this->isClosed($method, $uri, $parameters), \sprintf('%s should %sopen "%s".', $role, $open ? '' : 'not ', $name));
        }
    }

    public function testEveryWriteIsRefusedWithoutItsCsrfToken(): void
    {
        $this->client->loginUser($this->createAdmin(Permission::MANAGE_ROLE), 'admin');

        foreach ($this->routes() as $name => [, $method, $uri, $parameters]) {
            if ('GET' === $method) {
                continue;
            }

            foreach ([[], ['_token' => 'forged']] as $token) {
                $this->client->request($method, $uri, $parameters + $token);
                self::assertSame(403, $this->client->getResponse()->getStatusCode(), \sprintf('"%s" should refuse %s.', $name, [] === $token ? 'a missing token' : 'a forged token'));
            }
        }

        self::assertCount(1, $this->entityManager()->getConnection()->fetchFirstColumn('SELECT 1 FROM cyllene_translation_suggestion'));
        self::assertCount(0, $this->entityManager()->getConnection()->fetchFirstColumn('SELECT 1 FROM cyllene_translation_override'));
    }

    public function testAnAdminWithoutAnyRoleOfThePluginSeesNeitherScreenNorMenuEntry(): void
    {
        $this->client->loginUser($this->createAdmin(null), 'admin');

        $this->client->request('GET', '/admin/translations');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/locales/');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('/admin/translations', (string) $this->client->getResponse()->getContent());
    }

    public function testAReadOnlyAdminIsShownNoWayToWrite(): void
    {
        $this->client->loginUser($this->createAdmin('ROLE_AI_TRANSLATION_VIEW'), 'admin');

        $this->entityManager()->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome to the shop', 'en_US', 'stub', 0.9));
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#translation-edit-modal'));
        self::assertCount(0, $crawler->filter('#import-overrides-modal'));
        self::assertCount(0, $crawler->filter('[data-bs-target="#translation-edit-modal"]'));
        // The coverage's shortcut to the generation form would land on a page without it.
        self::assertCount(0, $crawler->filter('a[href*="/translations/suggestions"][href*="target="]'));
        // The export stays: it reads.
        self::assertGreaterThan(0, $crawler->filter('a[href*="/translations/export/"]')->count());

        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('app.welcome', (string) $this->client->getResponse()->getContent());
        self::assertCount(0, $crawler->filter('#generate-form'));
        self::assertCount(0, $crawler->filter('#suggestion-review-modal'));
        self::assertCount(0, $crawler->filter('form[action$="/approve"]'));
        self::assertCount(0, $crawler->filter('[data-bs-target="#suggestion-review-modal"]'));
    }

    public function testAnEmptyListOnlyPointsAtTheGenerationFormToWhoHasIt(): void
    {
        $this->client->loginUser($this->createAdmin(Permission::MANAGE_ROLE), 'admin');
        $crawler = $this->client->request('GET', '/admin/translations');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('a[href*="/translations/suggestions"][href*="target="]')->count());

        $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Use the form above', (string) $this->client->getResponse()->getContent());

        // The filter form sent as it is, every field on its first choice, narrows nothing.
        $this->client->request('GET', '/admin/translations/suggestions', ['criteria' => ['catalogue' => '', 'locale' => '', 'failed' => '', 'search' => ['value' => '']]]);
        self::assertStringContainsString('Use the form above', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/admin/translations/suggestions', ['criteria' => ['search' => ['value' => 'nothing-like-this']]]);
        self::assertStringNotContainsString('Use the form above', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($this->createAdmin('ROLE_AI_TRANSLATION_REVIEW'), 'admin');
        $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Nothing is waiting for a review', $content);
        self::assertStringNotContainsString('Use the form above', $content);
    }

    public function testAReviewerIsShownTheDecisionsButNotTheGeneration(): void
    {
        $this->client->loginUser($this->createAdmin('ROLE_AI_TRANSLATION_REVIEW'), 'admin');

        $this->entityManager()->persist(new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome to the shop', 'en_US', 'stub', 0.9));
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/admin/translations/suggestions');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#suggestion-review-modal'));
        self::assertGreaterThan(0, $crawler->filter('form[action$="/approve"]')->count());
        self::assertCount(0, $crawler->filter('#generate-form'));
    }

    /**
     * The sixteen routes of the plugin, each with the permission it asks. The two that name a
     * suggestion get a real one: the entity is resolved before the permission is checked.
     *
     * @return array<string, array{Permission, string, string, array<string, mixed>}>
     */
    private function routes(): array
    {
        $suggestion = $this->entityManager()->getRepository(TranslationSuggestion::class)->findOneBy([]);

        if (null === $suggestion) {
            $suggestion = new TranslationSuggestion('app.welcome', 'messages', 'fr_FR', 'Bienvenue', 'Welcome to the shop', 'en_US', 'stub', 0.9);
            $this->entityManager()->persist($suggestion);
            $this->entityManager()->flush();
        }

        $run = ['source_locale' => 'en_US', 'target_locale' => 'fr_FR', 'catalogue' => 'messages'];

        return [
            'translation_index' => [Permission::View, 'GET', '/admin/translations', []],
            'translation_export' => [Permission::View, 'GET', '/admin/translations/export/csv', []],
            'translation_import_template' => [Permission::View, 'GET', '/admin/translations/import-template/csv', []],
            'translation_save' => [Permission::Edit, 'POST', '/admin/translations/save', []],
            'translation_reset' => [Permission::Edit, 'POST', '/admin/translations/reset', []],
            'translation_purge_orphans' => [Permission::Edit, 'POST', '/admin/translations/purge-orphans', []],
            'translation_import' => [Permission::Import, 'POST', '/admin/translations/import', []],
            'translation_import_confirm' => [Permission::Import, 'POST', '/admin/translations/import/confirm', []],
            'translation_import_cancel' => [Permission::Import, 'POST', '/admin/translations/import/cancel', []],
            'suggestion_index' => [Permission::View, 'GET', '/admin/translations/suggestions', []],
            'suggestion_estimate' => [Permission::Generate, 'GET', '/admin/translations/suggestions/estimate', $run],
            'suggestion_generate' => [Permission::Generate, 'POST', '/admin/translations/suggestions/generate', $run],
            'suggestion_approve' => [Permission::Review, 'POST', \sprintf('/admin/translations/suggestions/%d/approve', $suggestion->getId()), []],
            'suggestion_reject' => [Permission::Review, 'POST', \sprintf('/admin/translations/suggestions/%d/reject', $suggestion->getId()), []],
            'suggestion_approve_bulk' => [Permission::Review, 'POST', '/admin/translations/suggestions/approve-bulk', []],
            'suggestion_reject_bulk' => [Permission::Review, 'POST', '/admin/translations/suggestions/reject-bulk', []],
        ];
    }

    /**
     * Whether the permission closes the route. The writes are attempted without a CSRF
     * token: once past the permission they are refused too, but by the token check and with
     * its own message - which is how the two refusals are told apart.
     *
     * @param array<string, mixed> $parameters
     */
    private function isClosed(string $method, string $uri, array $parameters): bool
    {
        $this->client->catchExceptions(false);

        try {
            $this->client->request($method, $uri, $parameters);
        } catch (AccessDeniedException $exception) {
            return 'Invalid CSRF token.' !== $exception->getMessage();
        } finally {
            $this->client->catchExceptions(true);
        }

        return false;
    }

    private function createAdmin(?string $role): AdminUserInterface
    {
        $suffix = strtolower((string) preg_replace('/\W+/', '_', $role ?? 'none'));

        return self::admin('ai_permission_' . $suffix, null !== $role ? [$role] : []);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
