<?php

declare(strict_types=1);

namespace WebProject\Symfony\TibberApiBundle\Service;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Cache\CacheItemPoolInterface;
use WebProject\TibberApiClient\Model\ConsumptionNode;
use WebProject\TibberApiClient\Model\Enum\AppScreen;
use WebProject\TibberApiClient\Model\Enum\EnergyResolution;
use WebProject\TibberApiClient\Model\Enum\PriceResolution;
use WebProject\TibberApiClient\Model\Home;
use WebProject\TibberApiClient\Model\Price;
use WebProject\TibberApiClient\Model\PriceInfo;
use WebProject\TibberApiClient\Model\SendPushNotificationResult;
use WebProject\TibberApiClient\Model\UpdateHomeInput;
use WebProject\TibberApiClient\Model\Viewer;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

use function max;
use function md5;
use function min;
use function sprintf;
use function strlen;
use function substr;
use function time;

class CachedTibberService implements TibberServiceInterface
{
    public const DEFAULT_TTL_VIEWER          = 86400; // 24 hours
    public const DEFAULT_TTL_HOMES           = 86400; // 24 hours
    public const DEFAULT_TTL_HOME            = 86400; // 24 hours
    public const DEFAULT_TTL_PRICE_INFO      = 1800;  // 30 minutes
    public const DEFAULT_TTL_TODAY_PRICES    = 3600;  // 1 hour
    public const DEFAULT_TTL_TOMORROW_PRICES = 14400; // 4 hours
    public const DEFAULT_TTL_CURRENT_PRICE   = 300;   // 5 minutes
    public const DEFAULT_TTL_CONSUMPTION     = 3600;  // 1 hour

    /**
     * @param array<string, int> $ttlConfig
     */
    public function __construct(
        private readonly TibberServiceInterface $inner,
        private readonly CacheItemPoolInterface $cache,
        private readonly array $ttlConfig = [],
        private readonly string $cachePrefix = 'tibber_',
    ) {
    }

    public function getViewer(): Viewer
    {
        $key  = $this->cachePrefix . 'viewer';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var Viewer $viewer */
            $viewer = $item->get();

            return $viewer;
        }

        $viewer = $this->inner->getViewer();
        $item->set($viewer);
        $item->expiresAfter($this->ttlConfig['viewer'] ?? self::DEFAULT_TTL_VIEWER);
        $this->cache->save($item);

        return $viewer;
    }

    /**
     * @return array<Home>
     */
    public function getHomes(): array
    {
        $key  = $this->cachePrefix . 'homes';
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<Home> $homes */
            $homes = $item->get();

            return $homes;
        }

        $homes = $this->inner->getHomes();
        $item->set($homes);
        $item->expiresAfter($this->ttlConfig['homes'] ?? self::DEFAULT_TTL_HOMES);
        $this->cache->save($item);

        return $homes;
    }

    public function getHome(string $homeId): ?Home
    {
        $key  = $this->buildKey('home', $homeId);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var Home|null $home */
            $home = $item->get();

            return $home;
        }

        $home = $this->inner->getHome($homeId);
        $item->set($home);
        $item->expiresAfter($this->ttlConfig['home'] ?? self::DEFAULT_TTL_HOME);
        $this->cache->save($item);

        return $home;
    }

    public function getPriceInfo(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): ?PriceInfo
    {
        $key  = $this->buildKey('price_info', $homeId . '_' . $resolution->value . '_' . $this->getMarketDate());
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var PriceInfo|null $priceInfo */
            $priceInfo = $item->get();

            return $priceInfo;
        }

        $priceInfo = $this->inner->getPriceInfo($homeId, $resolution);
        $item->set($priceInfo);

        $configuredTtl    = $this->ttlConfig['price_info'] ?? self::DEFAULT_TTL_PRICE_INFO;
        $intervalSeconds  = PriceResolution::QUARTER_HOURLY === $resolution ? 900 : 3600;
        $secondsRemaining = $intervalSeconds - (time() % $intervalSeconds);

        if (0 === $configuredTtl) {
            $item->expiresAfter(0);
        } else {
            // If tomorrow's prices are not yet included, recheck after 5 minutes
            $ttl = (null !== $priceInfo && [] === $priceInfo->tomorrow)
                ? min(300, $secondsRemaining)
                : min($configuredTtl, $secondsRemaining);

            $item->expiresAfter(max(10, $ttl));
        }

        $this->cache->save($item);

        return $priceInfo;
    }

    public function getCurrentPrice(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): ?Price
    {
        $intervalSeconds   = PriceResolution::QUARTER_HOURLY === $resolution ? 900 : 3600;
        $intervalTimestamp = time() - (time() % $intervalSeconds);
        $key               = $this->buildKey('current_price', $homeId . '_' . $resolution->value . '_' . $intervalTimestamp);
        $item              = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var Price|null $price */
            $price = $item->get();

            return $price;
        }

        $price = $this->inner->getCurrentPrice($homeId, $resolution);
        $item->set($price);

        // Expire gracefully at the end of the current pricing interval (15 min or 60 min)
        $configuredTtl = $this->ttlConfig['current_price'] ?? self::DEFAULT_TTL_CURRENT_PRICE;
        if (0 === $configuredTtl) {
            $item->expiresAfter(0);
        } else {
            $secondsRemaining = $intervalSeconds - (time() % $intervalSeconds);
            $effectiveTtl     = max(10, min($configuredTtl, $secondsRemaining));
            $item->expiresAfter($effectiveTtl);
        }

        $this->cache->save($item);

        return $price;
    }

    /**
     * @return array<Price>
     */
    public function getTodaysPrices(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): array
    {
        $key  = $this->buildKey('today_prices', $homeId . '_' . $resolution->value . '_' . $this->getMarketDate());
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<Price> $prices */
            $prices = $item->get();

            return $prices;
        }

        $prices = $this->inner->getTodaysPrices($homeId, $resolution);
        $item->set($prices);
        $item->expiresAfter($this->ttlConfig['today_prices'] ?? self::DEFAULT_TTL_TODAY_PRICES);
        $this->cache->save($item);

        return $prices;
    }

    /**
     * @return array<Price>
     */
    public function getTomorrowsPrices(string $homeId, PriceResolution $resolution = PriceResolution::HOURLY): array
    {
        $key  = $this->buildKey('tomorrow_prices', $homeId . '_' . $resolution->value . '_' . $this->getMarketDate());
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<Price> $prices */
            $prices = $item->get();

            return $prices;
        }

        $prices = $this->inner->getTomorrowsPrices($homeId, $resolution);
        $item->set($prices);

        // If tomorrow's prices are not yet published, recheck after a short interval (e.g. 5 minutes)
        $ttl = [] === $prices
            ? 300
            : ($this->ttlConfig['tomorrow_prices'] ?? self::DEFAULT_TTL_TOMORROW_PRICES);

        $item->expiresAfter($ttl);
        $this->cache->save($item);

        return $prices;
    }

    /**
     * @return array<ConsumptionNode>
     */
    public function getConsumption(string $homeId, EnergyResolution $resolution, int $lastCount): array
    {
        $hourBucket = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d-H');
        $key        = $this->buildKey('consumption', $homeId . '_' . $resolution->value . '_' . $lastCount . '_' . $hourBucket);
        $item       = $this->cache->getItem($key);

        if ($item->isHit()) {
            /** @var array<ConsumptionNode> $consumption */
            $consumption = $item->get();

            return $consumption;
        }

        $consumption = $this->inner->getConsumption($homeId, $resolution, $lastCount);
        $item->set($consumption);
        $item->expiresAfter($this->ttlConfig['consumption'] ?? self::DEFAULT_TTL_CONSUMPTION);
        $this->cache->save($item);

        return $consumption;
    }

    public function sendPushNotification(string $title, string $message, ?AppScreen $screen = null): SendPushNotificationResult
    {
        return $this->inner->sendPushNotification($title, $message, $screen);
    }

    public function updateHome(UpdateHomeInput $input): ?Home
    {
        $home = $this->inner->updateHome($input);

        // Invalidate cached home, homes list, and viewer
        $this->cache->deleteItems([
            $this->buildKey('home', $input->homeId),
            $this->cachePrefix . 'homes',
            $this->cachePrefix . 'viewer',
        ]);

        return $home;
    }

    public function getInnerService(): TibberServiceInterface
    {
        return $this->inner;
    }

    private function getMarketDate(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    }

    private function buildKey(string $type, string $discriminator): string
    {
        $key = sprintf('%s%s_%s', $this->cachePrefix, $type, substr(md5($discriminator), 0, 16));

        if (strlen($key) > 64) {
            return substr($this->cachePrefix, 0, 32) . $type . '_' . substr(md5($discriminator), 0, 16);
        }

        return $key;
    }
}
