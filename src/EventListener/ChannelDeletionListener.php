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
 * On the post-delete event: the deletion is committed by then, and the purge runs in a
 * transaction of its own - inside the host's flush, a rollback would follow an announced
 * purge. A purge that fails leaves the rows for `cyllene:ai-translation:purge-scopes`,
 * rather than turning a deletion that happened into an error page.
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
        } catch (\Throwable $exception) {
            $this->logger->error('The AI translation data of the deleted channel "{channel}" could not be purged: run cyllene:ai-translation:purge-scopes.', [
                'channel' => $channel->getCode(),
                'exception' => $exception,
            ]);
        }
    }
}
