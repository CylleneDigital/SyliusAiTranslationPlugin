<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Bridge;

use CylleneDigital\SyliusAiTranslationPlugin\Bridge\SyliusChannelScopeProvider;
use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

final class SyliusChannelScopeProviderTest extends TestCase
{
    public function testTheScopesAreTheChannelsNamedOrElseCoded(): void
    {
        $repository = $this->createStub(ChannelRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$this->channel('B2B', 'Business'), $this->channel('WEB', null)]);

        $provider = new SyliusChannelScopeProvider($this->createStub(ChannelContextInterface::class), new ChannelRegistry($repository));

        self::assertSame(['B2B' => 'Business', 'WEB' => 'WEB'], $provider->getAvailableScopes());
    }

    public function testTheScopeIsTheCurrentChannel(): void
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($this->channel('B2B', 'Business'));

        self::assertSame('B2B', $this->provider($context)->getScope());
    }

    public function testThereIsNoScopeWithoutAChannelContext(): void
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willThrowException(new ChannelNotFoundException());

        self::assertNull($this->provider($context)->getScope());
    }

    private function provider(ChannelContextInterface $context): SyliusChannelScopeProvider
    {
        return new SyliusChannelScopeProvider($context, new ChannelRegistry($this->createStub(ChannelRepositoryInterface::class)));
    }

    private function channel(string $code, ?string $name): ChannelInterface
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);
        $channel->method('getName')->willReturn($name);

        return $channel;
    }
}
