<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\GlobResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Yaml\Yaml;

/**
 * The plugin has no semantic configuration of its own: the translation engine and its
 * config tree live in cyllene-digital/ai-translation-bundle (`cyllene_digital_ai_translation`
 * key). This extension loads the services and prepends what the two admin pages need: their
 * Twig hooks, the grid action templates, the generation bus, the import staging cache pool
 * and the theme translation roots.
 */
final class CylleneDigitalSyliusAiTranslationExtension extends Extension implements PrependExtensionInterface
{
    /** The Messenger bus the generation runs are dispatched on — see {@see prependGenerationBus()}. */
    public const string GENERATION_BUS = 'cyllene_digital_ai_translation.bus';

    /** The cache pool holding the staged imports — see {@see prependImportStagingPool()}. */
    public const string IMPORT_STAGING_POOL = 'cyllene_digital_sylius_ai_translation.import_staging';

    /** Seconds an uploaded file waits for its confirmation before the pool drops it. */
    private const int IMPORT_STAGING_LIFETIME = 3600;

    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependGenerationBus($container);
        $this->prependImportStagingPool($container);

        $this->prependTwigHooks($container);

        if ($container->hasExtension('sylius_grid')) {
            // The suggestion grid's own action types (see SuggestionGrid): the bulk
            // renderer only knows the types registered here.
            $container->prependExtensionConfig('sylius_grid', [
                'templates' => [
                    'action' => [
                        'approve_suggestion' => '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/action/approve.html.twig',
                        'review_suggestion' => '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/action/review.html.twig',
                        'edit_translation' => '@CylleneDigitalSyliusAiTranslationPlugin/admin/translation/grid/action/edit.html.twig',
                    ],
                    'bulk_action' => [
                        'approve_suggestions' => '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/bulk_action/approve.html.twig',
                        'reject_suggestions' => '@CylleneDigitalSyliusAiTranslationPlugin/admin/suggestion/grid/bulk_action/reject.html.twig',
                    ],
                ],
            ]);
        }

        $this->prependThemeTranslationRoots($container);
    }

    /**
     * The hooks of `config/twig_hooks/`, prepended rather than imported: nothing to add to the
     * host's configuration, and what it declares itself still wins.
     */
    private function prependTwigHooks(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('sylius_twig_hooks')) {
            return;
        }

        foreach (glob(__DIR__ . '/../../config/twig_hooks/*.yaml') ?: [] as $file) {
            $parsed = Yaml::parseFile($file);

            if (\is_array($parsed) && \is_array($parsed['sylius_twig_hooks'] ?? null)) {
                /** @var array<string, mixed> $hooks */
                $hooks = $parsed['sylius_twig_hooks'];
                $container->prependExtensionConfig('sylius_twig_hooks', $hooks);
            }
        }
    }

    /**
     * Sylius' default bus (sylius.command_bus) wraps every handler in doctrine_transaction,
     * and a worker handles a message on the bus it was dispatched on. A generation run
     * flushes per chunk so the suggestions already billed survive a later failing chunk;
     * inside that transaction, the failure rolls everything back (suggestions, errored
     * rows, journal) and replaying the failed message bills the whole run again. The
     * runs go through this dedicated bus instead: Messenger's default middleware only,
     * no transaction. The engine's handler has no `bus` option, so it is on every bus.
     * Sylius always sets default_bus, so declaring a second bus never changes the host's.
     */
    private function prependGenerationBus(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'buses' => [
                    self::GENERATION_BUS => [],
                ],
            ],
        ]);
    }

    /**
     * A staged import waits for its confirmation in this pool, on top of the host's
     * `cache.app`: where that one is shared (Redis…), the confirmation can be served by
     * another node than the upload. A host re-points the pool by declaring it itself —
     * prepended config merges under the host's own.
     */
    private function prependImportStagingPool(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('framework')) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    self::IMPORT_STAGING_POOL => [
                        'adapter' => 'cache.app',
                        'default_lifetime' => self::IMPORT_STAGING_LIFETIME,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Auto-registers the Sylius themes' translations/ directories as additional
     * translation roots of the engine (browsed as "@<ThemeDir>/…" catalogues). Prepended
     * config merges under the host's own: an explicit additional_paths entry with the
     * same label wins. The glob happens at compile time — themes are code, a new theme
     * lands with a deploy and its cache warm-up.
     */
    private function prependThemeTranslationRoots(ContainerBuilder $container): void
    {
        /** @var string $projectDir */
        $projectDir = $container->getParameter('kernel.project_dir');

        // The folder's content is not tracked: a file edited inside a theme rebuilds nothing.
        if (!$container->fileExists($projectDir . '/themes', false)) {
            return;
        }

        // A theme added while developing rebuilds the container by itself. `forExclusion` is
        // what makes a non-recursive glob yield the directories it matches: without it the
        // resource finds nothing and never goes stale.
        $container->addResource(new GlobResource($projectDir . '/themes', '/*/translations', false, true));

        $paths = [];
        foreach (glob($projectDir . '/themes/*/translations', \GLOB_ONLYDIR) ?: [] as $dir) {
            $label = preg_replace('/[^A-Za-z0-9_.-]/', '-', basename(\dirname($dir))) ?? '';

            if ('' !== $label) {
                $paths[$label] = $dir;
            }
        }

        if ([] !== $paths) {
            $container->prependExtensionConfig('cyllene_digital_ai_translation', [
                'additional_paths' => $paths,
            ]);
        }
    }
}
