<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * The templates carry no script of their own: every behaviour is a Stimulus controller of
 * assets/admin/entrypoint.js, which is what lets a host run a strict Content Security
 * Policy. Checked on the sources, the suite having no browser.
 */
final class AdminAssetsTest extends TestCase
{
    public function testTheTemplatesCarryNoInlineScript(): void
    {
        foreach ((new Finder())->files()->name('*.twig')->in(\dirname(__DIR__, 2) . '/templates') as $template) {
            self::assertDoesNotMatchRegularExpression(
                '/<script\b|\son[a-z]+\s*=\s*["\']/i',
                $template->getContents(),
                \sprintf('%s carries an inline script or event handler: make it a Stimulus controller.', $template->getRelativePathname()),
            );
        }
    }

    public function testEveryControllerATemplateNamesIsRegistered(): void
    {
        $root = \dirname(__DIR__, 2);

        preg_match_all("/app\\.register\\('([\\w-]+)'/", (string) file_get_contents($root . '/assets/admin/entrypoint.js'), $matches);
        $registered = $matches[1];
        sort($registered);

        $used = [];
        foreach ((new Finder())->files()->name('*.twig')->in($root . '/templates') as $template) {
            preg_match_all('/data-controller="([\w-]+)"/', $template->getContents(), $matches);
            foreach ($matches[1] as $identifier) {
                $used[$identifier] = true;
            }
        }

        $used = array_keys($used);
        sort($used);

        self::assertNotSame([], $registered);
        self::assertSame($registered, $used);
    }
}
