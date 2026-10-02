<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\DependencyInjection;

use CylleneDigital\SyliusAiTranslationPlugin\DependencyInjection\CylleneDigitalSyliusAiTranslationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The themes' translations are found when the container is built: a theme added while
 * developing must make that container stale, or its catalogues only show after a manual
 * cache clear.
 */
final class ThemeTranslationRootsTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/ait_themes_' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->projectDir . '/themes/A/translations');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testAThemeAddedWhileDevelopingMakesTheContainerStale(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', $this->projectDir);

        (new CylleneDigitalSyliusAiTranslationExtension())->prepend($container);

        self::assertSame(
            [['additional_paths' => ['A' => $this->projectDir . '/themes/A/translations']]],
            $container->getExtensionConfig('cyllene_digital_ai_translation'),
        );

        $themes = array_values(array_filter(
            $container->getResources(),
            static fn (object $resource): bool => $resource instanceof GlobResource && [] !== iterator_to_array($resource),
        ));

        self::assertCount(1, $themes);
        self::assertSame([realpath($this->projectDir . '/themes/A/translations')], array_keys(iterator_to_array($themes[0])));
        self::assertTrue($themes[0]->isFresh(0));

        (new Filesystem())->mkdir($this->projectDir . '/themes/B/translations');

        self::assertFalse($themes[0]->isFresh(0));
    }
}
