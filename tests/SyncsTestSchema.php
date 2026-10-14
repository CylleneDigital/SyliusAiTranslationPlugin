<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin;

use CylleneDigital\SyliusAiTranslationPlugin\Security\Permission;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * What a kernel test needs from the database, no fixtures being loaded: the full test-app
 * schema (Sylius + engine), the two locales the host exposes and the currency of the test
 * channels — all idempotent between runs — and an admin to log in programmatically.
 */
trait SyncsTestSchema
{
    private static function syncTestSchema(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        if (!self::isMariaDbDeclaredAsMySql($entityManager->getConnection())) {
            (new SchemaTool($entityManager))->updateSchema($entityManager->getMetadataFactory()->getAllMetadata());
        }

        // Through the entities, not SQL: how an id is generated depends on the database.
        foreach (['locale' => ['en_US', 'fr_FR'], 'currency' => ['EUR']] as $resource => $codes) {
            /** @var RepositoryInterface<LocaleInterface|CurrencyInterface> $repository */
            $repository = self::getContainer()->get('sylius.repository.' . $resource);
            /** @var FactoryInterface<LocaleInterface|CurrencyInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.' . $resource);

            foreach ($codes as $code) {
                if (null === $repository->findOneBy(['code' => $code])) {
                    $entity = $factory->createNew();
                    $entity->setCode($code);
                    $entityManager->persist($entity);
                }
            }
        }

        $entityManager->flush();
    }

    /**
     * A MariaDB declared with a bare version - what a workstation does, the Sylius
     * migrations needing it - is a MySQL to DBAL: the engine then asks for MySQL's binary
     * collation, which MariaDB only knows from 11.4 on. Such a database got its schema from
     * the migrations, and is left as they made it.
     */
    private static function isMariaDbDeclaredAsMySql(Connection $connection): bool
    {
        $platform = $connection->getDatabasePlatform();

        return $platform instanceof AbstractMySQLPlatform &&
            !$platform instanceof MariaDBPlatform &&
            false === $connection->fetchOne("SELECT 1 FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_0900_bin'");
    }

    /**
     * The admin of that username, created on first use, holding exactly the given roles of the
     * plugin on top of a regular admin's — every permission by default.
     *
     * @param list<string> $roles
     */
    private static function admin(string $username, array $roles = [Permission::MANAGE_ROLE]): AdminUserInterface
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);

        /** @var RepositoryInterface<AdminUserInterface> $adminRepository */
        $adminRepository = $container->get('sylius.repository.admin_user');
        $admin = $adminRepository->findOneBy(['username' => $username]);

        if (null === $admin) {
            /** @var FactoryInterface<AdminUserInterface> $adminFactory */
            $adminFactory = $container->get('sylius.factory.admin_user');
            $admin = $adminFactory->createNew();
            $admin->setUsername($username);
            $admin->setEmail($username . '@example.com');
            $admin->setPassword('not-used-logged-in-programmatically');
            $admin->setLocaleCode('en_US');
            $admin->setEnabled(true);

            $entityManager->persist($admin);
        }

        foreach ($roles as $role) {
            $admin->addRole($role);
        }

        $entityManager->flush();

        return $admin;
    }
}
