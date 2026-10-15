<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAiTranslationPlugin\Unit\Scope;

use CylleneDigital\SyliusAiTranslationPlugin\Scope\ChannelRegistry;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class ChannelRegistryTest extends TestCase
{
    public function testTheChannelsAreReadOnceAndIndexedByCode(): void
    {
        $channel = $this->channel('B2B');

        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$channel, $this->channel(null)]);

        $registry = new ChannelRegistry($repository, new RequestStack(), new MockClock());

        self::assertSame(['B2B' => $channel], $registry->all());
        self::assertSame($channel, $registry->get('B2B'));
        self::assertNull($registry->get('UNKNOWN'));
    }

    public function testAResetMakesTheNextRequestReadAgain(): void
    {
        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('findAll')->willReturn([$this->channel('B2B')]);

        $registry = new ChannelRegistry($repository, $this->requestStack(), new MockClock());

        $registry->all();
        $registry->reset();
        $registry->all();
    }

    public function testARequestKeepsTheChannelsItRead(): void
    {
        $repository = $this->createMock(ChannelRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$this->channel('B2B')]);

        $clock = new MockClock();
        $registry = new ChannelRegistry($repository, $this->requestStack(), $clock);

        $registry->all();
        $clock->sleep(30);
        $registry->all();
    }

    public function testOutsideARequestTheChannelsAreReadAgainAfterASecond(): void
    {
        $channel = $this->channel('B2B');

        $repository = $this->createMock(ChannelRepositoryInterface::class);
        // A worker running a generation: the channel is deleted between two chunks.
        $repository->expects(self::exactly(2))->method('findAll')->willReturnOnConsecutiveCalls([$channel], []);

        $clock = new MockClock();
        $registry = new ChannelRegistry($repository, new RequestStack(), $clock);

        self::assertSame(['B2B' => $channel], $registry->all());
        $clock->sleep(0.5);
        self::assertSame(['B2B' => $channel], $registry->all());
        $clock->sleep(0.5);
        self::assertSame([], $registry->all());
    }

    private function requestStack(): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return $requestStack;
    }

    private function channel(?string $code): ChannelInterface
    {
        $channel = $this->createStub(ChannelInterface::class);
        $channel->method('getCode')->willReturn($code);

        return $channel;
    }
}
