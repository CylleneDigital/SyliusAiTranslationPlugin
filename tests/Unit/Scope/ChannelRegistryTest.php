<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Scope;

use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

final class ChannelRegistryTest extends TestCase
{
    public function testTheChannelsAreReadOnceAndIndexedByCode(): void
    {
        $channel = $this->channel('B2B');

        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$channel, $this->channel(null)]);

        $registry = new ChannelRegistry($repository);

        self::assertSame(['B2B' => $channel], $registry->all());
        self::assertSame($channel, $registry->get('B2B'));
        self::assertNull($registry->get('UNKNOWN'));
    }

    public function testAResetMakesTheNextRequestReadAgain(): void
    {
        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findAll')->willReturn([$this->channel('B2B')]);

        $registry = new ChannelRegistry($repository);

        $registry->all();
        $registry->reset();
        $registry->all();
    }

    private function channel(?string $code): ChannelInterface
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);

        return $channel;
    }
}
