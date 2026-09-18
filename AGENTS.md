# Agent Instructions

## Agent skills

### Issue tracker

Issues and specs live in GitHub Issues. See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical triage roles mapped to matching labels (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context layout (`CONTEXT.md` + `docs/adr/` at the repository root). See `docs/agents/domain.md`.

---

## Engineering Guidelines & Invariants

### 1. Test Gate & Verification
- **Primary Quality Gate**: Always execute `composer qa` before completing any task.
- **Components of QA Gate**:
  1. `composer test:build` (Actor generation)
  2. `composer cs:fix` (PHP-CS-Fixer code style alignment)
  3. `composer test` (Codeception unit tests with `--report` flag)
  4. `composer stan` (PHPStan static analysis at max level)
- **AI Test Reports**: Codeception tests must always run with the `--report` flag. This emits structured AI reports at `tests/_output/ai-report.json` and `tests/_output/ai-report.txt`.
- **Coverage**: Measure test coverage with `composer test:coverage`. Maintain high unit test coverage (>= 90%) across all components.

### 2. Domain Vocabulary ([`CONTEXT.md`](CONTEXT.md))
- Strictly enforce canonical terms across code, docstrings, command descriptions, exception messages, and tests:
  - **Account**: Prohibited synonyms: *User*, *profile*, *connection*.
  - **Home**: Prohibited synonyms: *Property*, *household*, *house*, *premise*.
  - **Meter**: Prohibited synonyms: *Counter*, *clock*.
  - **Price Info** / **Energy Price**: Prohibited synonyms: *Tariff*, *cost structure*, *rates*, *cost*.
  - **Consumption**: Prohibited synonyms: *Usage*, *burn*, *load*.

### 3. Caching & Temporal Invariants ([`CachedTibberService`](src/Service/CachedTibberService.php))
- **Market Timezone**: Daily pricing keys (`today_prices`, `tomorrow_prices`, `price_info`) must always derive date strings from the Central European market timezone (`Europe/Berlin`), never the host system's UTC local time. This prevents serving yesterday's prices during the nocturnal 00:00–02:00 window on UTC servers.
- **PSR-6 Key Lengths**: PSR-6 compliant cache pools only guarantee key lengths up to 64 characters. Composite key discriminators must be compactly hashed (e.g. `substr(md5(...), 0, 16)`) to ensure keys never exceed 64 characters regardless of account name length.
- **Complete Cache Invalidation**: When modifying a home entity via `updateHome()`, always invalidate the specific home key, the `homes` collection key, and the `viewer` key (as `Viewer` aggregates all homes).
- **Interval-Bounded TTL**: The effective TTL for `current_price` and `price_info` must never exceed the remaining seconds in the current price resolution interval (15 min or 60 min).

### 4. CLI Error Handling Standard ([`AccountOptionTrait`](src/Command/AccountOptionTrait.php))
- All bundle commands wrap execution via `AccountOptionTrait::run()` to catch `TibberAuthenticationException`, `TibberRateLimitException`, `TibberApiException`, and registry exceptions.
- CLI commands must exit with `Command::FAILURE` (1) and output a concise `SymfonyStyle::error()` message instead of leaking raw PHP exception stack traces to end users.
