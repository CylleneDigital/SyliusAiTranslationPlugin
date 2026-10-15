<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\Scope;

use Psr\Clock\ClockInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The Sylius channels, read once per request for everyone who asks: the engine's scope
 * provider (their names at every scope check, their locales at an import) and the screens'
 * resolver (their locales). Outside a request - a Messenger worker, the console - they are
 * kept one second at most: the engine checks the channel of a generation again before each
 * chunk, and a channel deleted while the run is in progress must stop it, while an import run
 * from the console checks every line.
 * Internal - the scope provider is what a host replaces. The resolver is not typed on it: the
 * codes it accepts come from the provider, through the engine, and the locales from here.
 */
final class ChannelRegistry implements ResetInterface
{
    private const string OUTSIDE_A_REQUEST_LIFETIME = '+1 second';

    /** @var array<string, ChannelInterface>|null channel code => channel */
    private ?array $channels = null;

    private ?\DateTimeImmutable $readAt = null;

    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Emptied between requests: a worker runtime keeps the service alive across them. */
    #[\Override]
    public function reset(): void
    {
        $this->channels = null;
        $this->readAt = null;
    }

    /**
     * @return array<string, ChannelInterface> channel code => channel
     *
     * @phpstan-impure
     */
    public function all(): array
    {
        if (null !== $this->channels && $this->isFresh()) {
            return $this->channels;
        }

        $this->channels = [];
        $this->readAt = $this->clock->now();

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

    private function isFresh(): bool
    {
        return null !== $this->requestStack->getMainRequest() ||
            $this->clock->now() < $this->readAt?->modify(self::OUTSIDE_A_REQUEST_LIFETIME);
    }
}
