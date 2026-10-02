<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Coverage\CoverageCalculator;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use CylleneDigital\SyliusAiTranslationPlugin\EventListener\ChannelDeletionListener;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelScopeResolver;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('cyllene_digital_sylius_ai_translation.scope.channel_registry', ChannelRegistry::class)
        ->args([service('sylius.repository.channel')])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('cyllene_digital_sylius_ai_translation.scope.channel_resolver', ChannelScopeResolver::class)
        ->args([
            service(TranslationManagerInterface::class),
            service('cyllene_digital_sylius_ai_translation.scope.channel_registry'),
            service(CoverageCalculator::class),
            service('request_stack'),
        ]);

    $services->set('cyllene_digital_sylius_ai_translation.scope.channel_deletion_listener', ChannelDeletionListener::class)
        ->args([service(ScopeParametersManager::class)])
        ->tag('kernel.event_listener', ['event' => 'sylius.channel.pre_delete']);
};
