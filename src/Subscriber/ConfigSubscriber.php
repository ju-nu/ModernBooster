<?php

declare(strict_types=1);

namespace Junu\ModernBooster\Subscriber;

use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Cache\CacheItemPoolInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'onConfigChanged',
        ];
    }

    public function onConfigChanged(SystemConfigChangedEvent $event): void
    {
        if (strpos($event->getKey(), 'JunuModernBooster.config.') === 0) {
            // Clear countdown caches
            $keys = $this->cache->getKeys();
            if ($keys instanceof \Traversable) {
                $keys = iterator_to_array($keys);
            }
            $this->cache->deleteItems(
                array_filter(
                    $keys,
                    fn($key) => strpos($key, 'delivery_countdown_') === 0
                )
            );

            // Clear holiday caches if countryStateCode changes
            if ($event->getKey() === 'JunuModernBooster.config.countryStateCode') {
                $this->cache->deleteItems(
                    array_filter(
                        $keys,
                        fn($key) => strpos($key, 'public_holidays_') === 0
                    )
                );
            }
        }
    }
}