<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\SyliusAiTranslationPlugin\Menu\AdminMenuListener;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.menu.admin', AdminMenuListener::class)
        ->args([service('security.authorization_checker')])
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main', 'priority' => AdminMenuListener::PRIORITY]);
};
