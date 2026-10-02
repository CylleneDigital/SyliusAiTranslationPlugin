<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Bridge;

use CylleneDigital\AiTranslationBundle\Scope\ScopeProviderInterface;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Maps the engine's override scope to the Sylius sales channel (aliased over the engine's
 * NullScopeProvider in config/services/bridge.php): a channel-scoped override shadows the
 * global one wherever a channel context is resolvable — shop requests, essentially.
 * CLI runs and Messenger workers have no channel context and fall back to the global
 * overrides (an async-rendered email may therefore use the global value).
 */
final readonly class SyliusChannelScopeProvider implements ScopeProviderInterface
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private ChannelRegistry $channels,
    ) {
    }

    #[\Override]
    public function getScope(): ?string
    {
        try {
            return $this->channelContext->getChannel()->getCode();
        } catch (ChannelNotFoundException) {
            return null;
        }
    }

    #[\Override]
    public function getAvailableScopes(): array
    {
        return array_map(
            static fn (ChannelInterface $channel): string => $channel->getName() ?? (string) $channel->getCode(),
            $this->channels->all(),
        );
    }
}
