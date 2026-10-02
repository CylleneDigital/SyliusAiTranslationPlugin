<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Bridge;

use CylleneDigital\AiTranslationBundle\Override\AuthorProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Stamps overrides and suggestion reviews with the logged-in admin user (aliased over
 * the engine's anonymous default in config/services/bridge.php). CLI runs have no token and
 * stay anonymous.
 */
final readonly class SyliusAdminAuthorProvider implements AuthorProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[\Override]
    public function getAuthorIdentifier(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }
}
