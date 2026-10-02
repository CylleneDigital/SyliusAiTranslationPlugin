<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Services are defined explicitly, without autowiring nor autoconfiguration, as Symfony recommends
 * for reusable bundles: ids are prefixed with the bundle alias and private unless stated otherwise.
 * The engine services (cyllene-digital/ai-translation-bundle) are registered under their class
 * name: that is how they are referenced here.
 */
return static function (ContainerConfigurator $container): void {
    $container->import('services/*.php');
};
