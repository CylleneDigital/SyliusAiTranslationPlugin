<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAiTranslationPlugin\Security\PermissionVoter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.security.permission_voter', PermissionVoter::class)
        ->args([service('security.access.decision_manager')])
        ->tag('security.voter');
};
