<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit;

use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusAdminAuthorProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusChannelScopeProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusLocaleProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Security\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * docs/architecture/public-contract.md tells a host it may replace or decorate these services. That only
 * holds while nothing in the plugin is typed on their classes: a decorator handed to such
 * a constructor is a TypeError, and a 500 on the screen.
 */
final class ReplaceableServicesTest extends TestCase
{
    private const array REPLACEABLE = [
        SyliusLocaleProvider::class,
        SyliusAdminAuthorProvider::class,
        SyliusChannelScopeProvider::class,
        PermissionVoter::class,
    ];

    public function testNoPluginClassDependsOnAReplaceableServiceClass(): void
    {
        foreach ((new Finder())->files()->name('*.php')->in(\dirname(__DIR__, 2) . '/src') as $file) {
            foreach (self::REPLACEABLE as $class) {
                $short = substr($class, (int) strrpos($class, '\\') + 1);

                if ($file->getFilenameWithoutExtension() === $short) {
                    continue;
                }

                // Comments aside, the name has no business anywhere else: an import (aliased or
                // grouped), a parameter, a union, a property, an instanceof all carry it.
                self::assertDoesNotMatchRegularExpression('/\\b' . $short . '\\b/', php_strip_whitespace($file->getPathname()), \sprintf('%s refers to %s, which a host may replace.', $file->getRelativePathname(), $short));
            }
        }
    }
}
