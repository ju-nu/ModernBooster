<?php

declare(strict_types=1);

namespace Junu\ModernBooster\Subscriber;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    private CacheInvalidator $cacheInvalidator;
    private LoggerInterface $logger;

    public function __construct(CacheInvalidator $cacheInvalidator, LoggerInterface $logger)
    {
        $this->cacheInvalidator = $cacheInvalidator;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onConfigChanged',
        ];
    }

    public function onConfigChanged(SystemConfigChangedEvent $event): void
    {
        if (strpos($event->getKey(), 'JunuModernBooster.config.') !== 0) {
            return;
        }

        try {
            // Invalidate cache tags for countdown
            $this->cacheInvalidator->invalidate(['junu-modern-booster-countdown'], true);

            // Invalidate holiday cache if countryStateCode changes
            if ($event->getKey() === 'JunuModernBooster.config.countryStateCode') {
                $this->cacheInvalidator->invalidate(['junu-modern-booster-holidays'], true);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to invalidate cache in ConfigSubscriber: ' . $e->getMessage(), [
                'exception' => $e,
                'key' => $event->getKey(),
            ]);
        }
    }
}