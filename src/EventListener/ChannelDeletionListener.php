<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAiTranslationPlugin\EventListener;

use CylleneDigital\AiTranslationBundle\Scope\ScopePurger;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * A deleted channel takes what the engine stores for it along: its overrides, its
 * suggestions and its prompt context; the generation journal stays. Left in place, they
 * would apply again to a channel created later under the same code.
 *
 * On the post-delete event, after the host's own listeners: the deletion is committed by
 * then, and the purge runs in a transaction of its own - inside the host's flush, a rollback
 * would follow an announced purge. A purge that fails leaves the rows for
 * `cyllene:ai-translation:purge-scopes`, rather than turning a deletion that happened into an
 * error page; a programming error is not one of those, and is let through.
 */
final readonly class ChannelDeletionListener
{
    public function __construct(
        private ScopePurger $scopePurger,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $channel = $event->getSubject();

        if (!$channel instanceof ChannelInterface || null === $channel->getCode()) {
            return;
        }

        try {
            $this->scopePurger->purge($channel->getCode());
        } catch (\Exception $exception) {
            $this->logger->error('The purge of the AI translation data of the deleted channel "{channel}" failed: cyllene:ai-translation:purge-scopes lists what is left.', [
                'channel' => $channel->getCode(),
                'exception' => $exception,
            ]);
        }
    }
}
