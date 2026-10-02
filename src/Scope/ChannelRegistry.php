<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Scope;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The Sylius channels, read once per request for everyone who asks: the engine's scope
 * provider (their names, at every scope check) and the screens' resolver (their locales).
 * Internal - the scope provider is what a host replaces. The resolver is not typed on it: the
 * codes it accepts come from the provider, through the engine, and the locales from here.
 */
final class ChannelRegistry implements ResetInterface
{
    /** @var array<string, ChannelInterface>|null channel code => channel */
    private ?array $channels = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
    ) {
    }

    /** Emptied between requests: a worker runtime keeps the service alive across them. */
    #[\Override]
    public function reset(): void
    {
        $this->channels = null;
    }

    /** @return array<string, ChannelInterface> channel code => channel */
    public function all(): array
    {
        if (null !== $this->channels) {
            return $this->channels;
        }

        $this->channels = [];

        foreach ($this->channelRepository->findAll() as $channel) {
            $code = $channel->getCode();

            if (null !== $code) {
                $this->channels[$code] = $channel;
            }
        }

        return $this->channels;
    }

    public function get(string $code): ?ChannelInterface
    {
        return $this->all()[$code] ?? null;
    }
}
