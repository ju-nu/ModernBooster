<?php

declare(strict_types=1);

namespace Junu\ModernBooster\Subscriber;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    private $cacheInvalidator; // Remove strict type hint
    private LoggerInterface $logger;

    public function __construct($cacheInvalidator, LoggerInterface $logger)
    {
        // Assert that $cacheInvalidator provides the expected methods
        if (!method_exists($cacheInvalidator, 'invalidate')) {
            throw new \InvalidArgumentException('Invalid cache invalidator provided');
        }
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
            $this->cacheInvalidator->invalidate(['junu-modern-booster-countdown'], true);
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