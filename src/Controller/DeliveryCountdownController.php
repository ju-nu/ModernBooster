<?php

declare(strict_types=1);

namespace Junu\ModernBooster\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpClient\HttpClient;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Psr\Log\LoggerInterface;
use Shopware\Core\PlatformRequest;
use DateTime;
use DateTimeZone;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class DeliveryCountdownController
{
    private CacheItemPoolInterface $cache;
    private SystemConfigService $systemConfigService;
    private LoggerInterface $logger;
    private RequestStack $requestStack;

    public function __construct(
        CacheItemPoolInterface $cache,
        SystemConfigService $systemConfigService,
        LoggerInterface $logger,
        RequestStack $requestStack
    ) {
        $this->cache = $cache;
        $this->systemConfigService = $systemConfigService;
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->logger->info('DeliveryCountdownController initialized');
    }

    #[Route(path: '/JunuModernBooster/delivery-information', name: 'frontend.junu.delivery.information', methods: ['GET'], defaults: ['XmlHttpRequest' => true])]
    public function getCountdown(): JsonResponse
    {
        $this->logger->info('getCountdown called');

        $request = $this->requestStack->getCurrentRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);

        // Input validation
        $cutoffTime = $this->systemConfigService->get('JunuModernBooster.config.cutoffTime') ?? '14:00:00';
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $cutoffTime)) {
            $this->logger->warning('Invalid cutoffTime format: ' . $cutoffTime);
            $cutoffTime = '14:00:00';
        }

        $supportWeekends = $this->systemConfigService->get('JunuModernBooster.config.supportWeekends') ?? true;
        $countryStateCode = $this->systemConfigService->get('JunuModernBooster.config.countryStateCode') ?? 'DE-HE';
        if (!preg_match('/^[A-Z]{2}-[A-Z]{2}$/', $countryStateCode)) {
            $this->logger->warning('Invalid countryStateCode format: ' . $countryStateCode);
            $countryStateCode = 'DE-HE';
        }

        $useNagerHolidays = $this->systemConfigService->get('JunuModernBooster.config.useNagerHolidays') ?? true;
        $ignoreDate1 = $this->systemConfigService->get('JunuModernBooster.config.ignoreDate1');
        $ignoreDate2 = $this->systemConfigService->get('JunuModernBooster.config.ignoreDate2');
        $ignoreDate3 = $this->systemConfigService->get('JunuModernBooster.config.ignoreDate3');
        $locale = $request->getLocale() ?? 'en-GB';

        // Config-specific cache key
        $configHash = md5(json_encode([
            $cutoffTime,
            $supportWeekends,
            $countryStateCode,
            $useNagerHolidays,
            $ignoreDate1,
            $ignoreDate2,
            $ignoreDate3
        ]));
        $cacheKey = 'delivery_countdown_' . date('Ymd') . '_' . $locale . '_' . $configHash;

        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $this->jsonCachedResponse($cacheItem->get());
        }

        $ignoredDates = [];
        foreach ([$ignoreDate1, $ignoreDate2, $ignoreDate3] as $index => $date) {
            if ($date && is_string($date)) {
                try {
                    $ignoredDates[] = (new DateTime($date))->format('Y-m-d');
                } catch (\Exception $e) {
                    $this->logger->warning("Invalid ignore date {$index}: {$date}");
                }
            }
        }

        if ($useNagerHolidays) {
            $publicHolidays = $this->fetchPublicHolidays($countryStateCode);
            if (is_array($publicHolidays)) {
                $ignoredDates = array_merge($ignoredDates, $publicHolidays);
            } else {
                $this->logger->error('fetchPublicHolidays did not return an array');
            }
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $today = $now->format('Y-m-d');
        $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');

        $cutoffToday = new DateTime("{$today} {$cutoffTime}", new DateTimeZone('UTC'));
        $cutoffTomorrow = new DateTime("{$tomorrow} {$cutoffTime}", new DateTimeZone('UTC'));

        $timeDiff = $now < $cutoffToday
            ? $cutoffToday->getTimestamp() - $now->getTimestamp()
            : $cutoffTomorrow->getTimestamp() - $now->getTimestamp();

        $hours = (int) floor($timeDiff / 3600);
        $minutes = (int) floor(($timeDiff % 3600) / 60);

        $deliveryDate = $now < $cutoffToday
            ? (clone $now)->modify('+1 day')
            : (clone $now)->modify('+2 days');

        $deliveryDate = $this->getNextValidDeliveryDate($deliveryDate, $supportWeekends, $ignoredDates);

        // Format deliveryDate using PHP timezone
        $displayTimezone = new DateTimeZone(date_default_timezone_get());
        $deliveryDate->setTimezone($displayTimezone);
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            $displayTimezone->getName(),
            \IntlDateFormatter::GREGORIAN,
            'EEEE, dd. MMMM yyyy'
        );
        $formattedDate = $formatter->format($deliveryDate);

        $snippetKey = $now < $cutoffToday
            ? 'junu.product.delivery.orderWithin'
            : 'junu.product.delivery.orderWithinTomorrow';

        $responseData = [
            'hours' => (string) $hours,
            'mins' => (string) $minutes,
            'deliveryDate' => $deliveryDate->format('Y-m-d'), // Keep Y-m-d for JS logic
            'formattedDate' => $formattedDate, // Localized for display
            'snippetKey' => $snippetKey
        ];

        $cacheItem->set($responseData);
        $cacheItem->expiresAfter(300); // Cache for 5 minutes
        $this->cache->save($cacheItem);

        return $this->jsonCachedResponse($responseData);
    }

    private function fetchPublicHolidays(string $countryStateCode): array
    {
        if (!preg_match('/^[A-Z]{2}-[A-Z]{2}$/', $countryStateCode)) {
            $this->logger->warning('Invalid countryStateCode format in fetchPublicHolidays: ' . $countryStateCode);
            return [];
        }

        [$countryCode, $stateCode] = explode('-', $countryStateCode);
        $year = date('Y');
        $cacheKey = "public_holidays_{$countryCode}_{$stateCode}_{$year}";

        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $holidayDates = [];
        $maxRetries = 3;
        $retryDelay = 1; // seconds

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $client = HttpClient::create();
                $response = $client->request(
                    'GET',
                    "https://date.nager.at/api/v3/publicholidays/{$year}/{$countryCode}"
                );
                $holidays = json_decode($response->getContent(), true);

                if (!is_array($holidays)) {
                    $this->logger->error('Public holidays API response is not an array');
                    return [];
                }

                foreach ($holidays as $holiday) {
                    $holidayDate = $holiday['date'] ?? null;
                    if (!$holidayDate) {
                        continue; // skip broken entry
                    }

                    // Always allow global holidays
                    if (!empty($holiday['global'])) {
                        $holidayDates[] = $holidayDate;
                        continue;
                    }

                    // Add regional holidays only if the target state is listed
                    if (isset($holiday['counties']) && is_array($holiday['counties'])) {
                        if (in_array("{$countryCode}-{$stateCode}", $holiday['counties'], true)) {
                            $holidayDates[] = $holidayDate;
                        }
                    }
                }

                $cacheItem->set($holidayDates);
                $cacheItem->expiresAfter(365 * 24 * 3600); // Cache for 1 year
                $this->cache->save($cacheItem);

                return $holidayDates;
            } catch (TransportExceptionInterface $e) {
                $this->logger->warning("Attempt {$attempt} failed to fetch public holidays: " . $e->getMessage());
                if ($attempt === $maxRetries) {
                    $this->logger->error('Failed to fetch public holidays after ' . $maxRetries . ' attempts');
                    return [];
                }
                sleep($retryDelay);
            } catch (\Exception $e) {
                $this->logger->error('Unexpected error fetching public holidays: ' . $e->getMessage());
                return [];
            }
        }

        return [];
    }

    private function isInvalidDeliveryDate(DateTime $date, bool $supportWeekends, array $ignoredDates): bool
    {
        $dayOfWeek = (int)$date->format('w'); // 0 (Sun) - 6 (Sat)
        $dateString = $date->format('Y-m-d');

        return (!$supportWeekends && ($dayOfWeek === 0 || $dayOfWeek === 6)) || in_array($dateString, $ignoredDates);
    }

    private function getNextValidDeliveryDate(DateTime $date, bool $supportWeekends, $ignoredDates): DateTime
    {
        if (!is_array($ignoredDates)) {
            $this->logger->error('Ignored dates is not an array in getNextValidDeliveryDate');
            $ignoredDates = [];
        }

        while ($this->isInvalidDeliveryDate($date, $supportWeekends, $ignoredDates)) {
            $date->modify('+1 day');
        }

        return $date;
    }

    private function jsonCachedResponse(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->setPublic();
        $response->setMaxAge(60); // HTTP cache for 60 seconds
        return $response;
    }
}