<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CylleneDigital\AiTranslationBundle\Catalogue\LocaleProviderInterface;
use CylleneDigital\AiTranslationBundle\Override\AuthorProviderInterface;
use CylleneDigital\AiTranslationBundle\Scope\ScopeProviderInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusAdminAuthorProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusChannelScopeProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusLocaleProvider;

/*
 * The Sylius adapters of the engine's host-facing interfaces. Plain aliases: the engine already
 * aliases these interfaces to its defaults, and the last write - this bundle loads after the
 * engine - deterministically wins.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The DB-backed provider: the autowired default of its interface is the immutable one, which
    // only knows the locales frozen at compile time - not the ones defined in the admin.
    $services->set('cyllene_digital_sylius_ai_translation.bridge.locale_provider', SyliusLocaleProvider::class)
        ->args([service('sylius.translation_locale_provider')]);

    $services->alias(LocaleProviderInterface::class, 'cyllene_digital_sylius_ai_translation.bridge.locale_provider');

    $services->set('cyllene_digital_sylius_ai_translation.bridge.author_provider', SyliusAdminAuthorProvider::class)
        ->args([service('security.helper')]);

    $services->alias(AuthorProviderInterface::class, 'cyllene_digital_sylius_ai_translation.bridge.author_provider');

    $services->set('cyllene_digital_sylius_ai_translation.bridge.channel_scope_provider', SyliusChannelScopeProvider::class)
        ->args([
            service('sylius.context.channel'),
            service('cyllene_digital_sylius_ai_translation.scope.channel_registry'),
        ]);

    $services->alias(ScopeProviderInterface::class, 'cyllene_digital_sylius_ai_translation.bridge.channel_scope_provider');
};
