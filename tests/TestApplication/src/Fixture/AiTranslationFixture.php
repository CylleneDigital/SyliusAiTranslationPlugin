<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Fixture;

use CylleneDigital\AiTranslationBundle\Entity\GenerationLog;
use CylleneDigital\AiTranslationBundle\Entity\TranslationSuggestion;
use CylleneDigital\AiTranslationBundle\Override\TranslationManagerInterface;
use CylleneDigital\AiTranslationBundle\Scope\ScopeParametersManager;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Test application only: something to look at on both screens right after
 * `sylius:fixtures:load` - overrides and pending suggestions, some for every channel and
 * some for one, a channel's own context for the AI, and a few runs in the journal.
 */
final class AiTranslationFixture extends AbstractFixture
{
    private const string SOURCE_LOCALE = 'en_US';

    private const string PROVIDER = 'fixtures';

    public function __construct(
        private readonly TranslationManagerInterface $translationManager,
        private readonly ScopeParametersManager $scopeParameters,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'ai_translation';
    }

    /**
     * @param array{
     *     contexts: array<string, string>,
     *     overrides: list<array{key: string, catalogue: string, locale: string, value: string, channel: string}>,
     *     suggestions: list<array{key: string, catalogue: string, locale: string, value: ?string, channel: string, confidence: float, error: ?string, in_flight: bool}>,
     *     runs: list<array{catalogue: string, locale: string, channel: string, created: int, error: ?string}>,
     * } $options
     */
    public function load(array $options): void
    {
        foreach ($options['contexts'] as $channel => $context) {
            $this->scopeParameters->setPromptContext($this->scope($channel), $context);
        }

        foreach ($options['overrides'] as $override) {
            $this->translationManager->saveOverride(
                $override['key'],
                $override['catalogue'],
                $override['locale'],
                $override['value'],
                $this->translationManager->getOriginalValue($override['key'], $override['catalogue'], $override['locale']),
                $this->scope($override['channel']),
            );
        }

        foreach ($options['suggestions'] as $suggestion) {
            $source = $this->translationManager->getOriginalValue($suggestion['key'], $suggestion['catalogue'], self::SOURCE_LOCALE) ?? $suggestion['key'];
            $scope = $this->scope($suggestion['channel']);

            if ($suggestion['in_flight']) {
                // What the engine stores before calling the provider: an errored row, marked in flight.
                $reserved = TranslationSuggestion::failed($suggestion['key'], $suggestion['catalogue'], $suggestion['locale'], $source, self::SOURCE_LOCALE, self::PROVIDER, 'Interrupted before the reply was stored', $scope);
                $reserved->setMetadata(['in_flight' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP')]);
                $this->entityManager->persist($reserved);

                continue;
            }

            $this->entityManager->persist(null !== $suggestion['error']
                ? TranslationSuggestion::failed($suggestion['key'], $suggestion['catalogue'], $suggestion['locale'], $source, self::SOURCE_LOCALE, self::PROVIDER, $suggestion['error'], $scope)
                : new TranslationSuggestion($suggestion['key'], $suggestion['catalogue'], $suggestion['locale'], $suggestion['value'], $source, self::SOURCE_LOCALE, self::PROVIDER, $suggestion['confidence'], $scope));
        }

        foreach ($options['runs'] as $run) {
            $scope = $this->scope($run['channel']);

            $this->entityManager->persist(null !== $run['error']
                ? GenerationLog::failure($run['catalogue'], $run['locale'], self::SOURCE_LOCALE, self::PROVIDER, $run['error'], $run['created'], $scope)
                : GenerationLog::success($run['catalogue'], $run['locale'], self::SOURCE_LOCALE, self::PROVIDER, $run['created'], $scope));
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('contexts')
                    ->info('Channel code => the context given to the AI for that channel')
                    ->useAttributeAsKey('channel')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('overrides')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('key')->isRequired()->end()
                            ->scalarNode('catalogue')->defaultValue('messages')->end()
                            ->scalarNode('locale')->isRequired()->end()
                            ->scalarNode('value')->isRequired()->end()
                            ->scalarNode('channel')->defaultValue('')->info('A channel code, empty = every channel')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('suggestions')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('key')->isRequired()->end()
                            ->scalarNode('catalogue')->defaultValue('messages')->end()
                            ->scalarNode('locale')->isRequired()->end()
                            ->scalarNode('value')->defaultNull()->end()
                            ->scalarNode('channel')->defaultValue('')->end()
                            ->floatNode('confidence')->defaultValue(0.9)->end()
                            ->scalarNode('error')->defaultNull()->info('A failed generation: no value, the provider error instead')->end()
                            ->booleanNode('in_flight')->defaultFalse()->info('Sent to the provider by a run that has not stored the reply yet')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('runs')
                    ->info('The generation journal')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('catalogue')->defaultValue('messages')->end()
                            ->scalarNode('locale')->isRequired()->end()
                            ->scalarNode('channel')->defaultValue('')->end()
                            ->integerNode('created')->defaultValue(0)->end()
                            ->scalarNode('error')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /** The engine's scope of a channel: its code, as the plugin's scope provider maps it. */
    private function scope(string $channel): string
    {
        if ('' !== $channel && !$this->translationManager->isKnownScope($channel)) {
            throw new \InvalidArgumentException(\sprintf('No channel "%s" to load AI translation fixtures for.', $channel));
        }

        return $channel;
    }
}
