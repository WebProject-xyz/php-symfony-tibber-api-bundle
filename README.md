# Tibber API Bundle for Symfony

[![CI](https://github.com/WebProject-xyz/php-symfony-tibber-api-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/WebProject-xyz/php-symfony-tibber-api-bundle/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-~8.5.0-blue.svg)](https://www.php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E8.1-black.svg)](https://symfony.com/)
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A modern Symfony bundle integrating the [`webproject-xyz/php-tibber-api-client`](https://github.com/WebProject-xyz/php-tibber-api-client) into Symfony 8.1+ applications.

---

## ⚡ Features

- **Modern Architecture**: Built on Symfony 8.1+ `AbstractBundle` with strict PHP 8.5 typing.
- **Multi-Account Support**: Configure single or multiple Tibber accounts with a configurable default.
- **Dependency Injection & Autowiring**:
  - Direct autowiring via `TibberClientInterface` and `TibberServiceInterface` for the default account.
  - Native Symfony 8 `#[Target('accountName')]` and named autowiring aliases (e.g. `TibberServiceInterface $secondaryTibberService`).
  - Runtime access via `TibberClientRegistryInterface` and `TibberServiceRegistryInterface`.
- **Smart Caching Layer**:
  - Optional transparent caching decorator (`CachedTibberService`) using PSR-6 cache pools.
  - Market timezone-aware (`Europe/Berlin`) date keys preventing midnight rollover data corruption on UTC servers.
  - PSR-6 key safety: keys are guaranteed <= 64 characters across all account names.
  - Differentiated TTLs: 24h for static metadata, interval-aligned TTL for current price, dynamic recheck for tomorrow's prices before publication (~13:00 CET), and complete cache eviction on mutations (`home`, `homes`, `viewer`).
- **CLI Commands**:
  - All Tibber commands integrated into `bin/console` with an `--account` (`-a`) option and robust error boundaries (graceful console errors for auth, rate limit, and network exceptions).

---

## 📦 Installation

```bash
composer require webproject-xyz/php-symfony-tibber-api-bundle
```

---

## ⚙️ Configuration

### 1. Minimal Setup (Shorthand)

For single-account applications, place your access token directly under the root key in `config/packages/tibber_api.yaml`:

```yaml
tibber_api:
  access_token: '%env(TIBBER_API_TOKEN)%'
  # Optional:
  # cache_pool: 'cache.app'
```

### 2. Multi-Account Setup

```yaml
tibber_api:
  # Optional: Account to wire to un-targeted TibberServiceInterface (defaults to first account or 'default')
  default_account: primary

  accounts:
    primary:
      access_token: '%env(TIBBER_API_TOKEN_PRIMARY)%'
      cache_pool: 'cache.app'
      cache_ttl:
        current_price: 180   # Override max TTL for current interval
        today_prices: 3600

    secondary:
      access_token: '%env(TIBBER_API_TOKEN_SECONDARY)%'
      # Caching disabled when cache_pool is omitted (default: null)
```

### 3. Full YAML Configuration Reference & Defaults

The following example demonstrates all available options with their respective default values:

```yaml
tibber_api:
  # Default account used when autowiring TibberServiceInterface / TibberClientInterface
  # Default: 'default' or the first account defined under 'accounts'
  default_account: ~

  accounts:
    default:
      # Required: Personal API access token from developer.tibber.com
      access_token: '%env(TIBBER_API_TOKEN)%'

      # Tibber GraphQL endpoint URL
      # Default: 'https://api.tibber.com/v1-beta/gql'
      endpoint: 'https://api.tibber.com/v1-beta/gql'

      # User-Agent header sent with HTTP requests
      # Default: 'WebProject-Tibber-API-Client'
      user_agent: 'WebProject-Tibber-API-Client'

      # PSR-6 Cache pool service ID (e.g. 'cache.app', 'cache.system')
      # When omitted or null, no caching decorator is registered for this account
      # Default: null
      cache_pool: ~

      # Granular Cache Time-To-Live (in seconds)
      # Only active if 'cache_pool' is configured
      cache_ttl:
        # User & registered homes metadata
        # Default: 86400 (24 hours)
        viewer: 86400

        # List of homes registered to the account
        # Default: 86400 (24 hours)
        homes: 86400

        # Single home details & subscription metadata
        # Default: 86400 (24 hours)
        home: 86400

        # Aggregated price info (today, tomorrow, current)
        # Default: 1800 (30 minutes)
        # Note: If tomorrow's prices are not yet available (~before 13:00 CET), TTL is dynamically capped at 300s
        price_info: 1800

        # Array of today's hourly / 15-minute energy prices
        # Default: 3600 (1 hour) - Cache key is date-stamped in Europe/Berlin market time
        today_prices: 3600

        # Array of tomorrow's energy prices
        # Default: 14400 (4 hours) - Cache key is date-stamped in Europe/Berlin market time
        # Note: If tomorrow's prices are not yet published, TTL is automatically reduced to 300s
        tomorrow_prices: 14400

        # Current spot price
        # Default: 300 (5 minutes)
        # Note: Automatically aligned with the current interval boundary (hourly or 15-min)
        # The effective TTL will never exceed the remaining seconds in the current interval.
        current_price: 300

        # Historical consumption data nodes
        # Default: 3600 (1 hour)
        consumption: 3600
```

> [!TIP]
> **Cache Invalidation:** Calling `updateHome()` automatically invalidates the cached `home` entry, the `homes` collection, and the `viewer` metadata.

---

## 🚀 Usage

### 1. Autowiring the Default Account

Inject `TibberServiceInterface` directly:

```php
namespace App\Service;

use WebProject\TibberApiClient\Service\TibberServiceInterface;

class EnergyMonitor
{
    public function __construct(
        private readonly TibberServiceInterface $tibber,
    ) {
    }

    public function checkTodayPrices(string $homeId): array
    {
        return $this->tibber->getTodaysPrices($homeId);
    }
}
```

### 2. Targeting Specific Accounts (`#[Target]`)

In multi-account setups, inject a specific account using Symfony 8's `#[Target]` attribute or parameter naming:

```php
namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Target;
use WebProject\TibberApiClient\Service\TibberServiceInterface;

class MultiHomeManager
{
    public function __construct(
        #[Target('secondary')]
        private readonly TibberServiceInterface $secondaryTibber,
        // Alternatively via parameter name:
        // private readonly TibberServiceInterface $secondaryTibberService,
    ) {
    }
}
```

### 3. Dynamic Runtime Selection via Registry

When account names are resolved dynamically (e.g. from user input or database entities):

```php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use WebProject\Symfony\TibberApiBundle\Registry\TibberServiceRegistryInterface;

class PriceController extends AbstractController
{
    public function __invoke(string $account, string $homeId, TibberServiceRegistryInterface $registry): JsonResponse
    {
        $service = $registry->getService($account);
        $currentPrice = $service->getCurrentPrice($homeId);

        return $this->json($currentPrice);
    }
}
```

---

## 💻 Console Commands

All commands accept the `--account` (`-a`) option to target any configured account:

```bash
# View account information & registered homes
bin/console tibber:viewer --account=primary

# Get today's electricity prices
bin/console tibber:prices:today --home-id=<id>

# Get tomorrow's electricity prices (available ~13:00 CET)
bin/console tibber:prices:tomorrow

# Get current electricity price
bin/console tibber:prices:current

# Query historical consumption
bin/console tibber:consumption --home-id=<id> --resolution=HOURLY --count=24

# Send push notification to Tibber mobile app
bin/console tibber:push "Title" "Message"

# Dump GraphQL introspection schema
bin/console tibber:schema:dump --output=resources/schema.json
```

---

## 🧪 Testing & Quality
 
Run the complete QA test gate:

```bash
composer qa
```

The QA gate comprises:
- **Codeception Unit Suite**: `composer test` (automatically generates AI reports in `tests/_output/ai-report.json` and `tests/_output/ai-report.txt` via `--report`)
- **PHPStan (Level max)**: `composer stan`
- **PHP-CS-Fixer**: `composer cs:check` / `composer cs:fix`

To generate a full code coverage report:

```bash
composer test:coverage
```

---

## 📄 License

This bundle is open-sourced software licensed under the [MIT License](LICENSE).
