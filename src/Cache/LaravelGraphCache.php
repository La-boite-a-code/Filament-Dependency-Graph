<?php

declare(strict_types=1);

namespace LaBoiteACode\DependencyGraph\Cache;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;
use LaBoiteACode\DependencyGraph\Contracts\GraphCache;
use LaBoiteACode\DependencyGraph\Domain\DTO\ApplicationSnapshot;
use Throwable;

/**
 * Stores snapshots in the configured Laravel cache store.
 *
 * An index of written keys is maintained so flushing only removes package
 * entries and never touches the rest of the application cache. Corrupted
 * payloads are treated as cache misses and removed.
 */
final class LaravelGraphCache implements GraphCache
{
    private const INDEX_KEY = 'filament-dependency-graph:keys';

    public function __construct(
        private readonly Factory $cache,
        private readonly ?string $store = null,
        private readonly ?int $defaultTtlSeconds = null,
    ) {}

    public function has(GraphCacheKey $key): bool
    {
        return $this->get($key) instanceof ApplicationSnapshot;
    }

    public function get(GraphCacheKey $key): ?ApplicationSnapshot
    {
        try {
            $payload = $this->repository()->get($key->value);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $payload */
            return ApplicationSnapshot::fromArray($payload);
        } catch (Throwable) {
            $this->forget($key);

            return null;
        }
    }

    public function put(GraphCacheKey $key, ApplicationSnapshot $snapshot, ?DateInterval $ttl = null): void
    {
        $seconds = $ttl !== null
            ? $this->intervalToSeconds($ttl)
            : $this->defaultTtlSeconds;

        $repository = $this->repository();

        if ($seconds === null) {
            $repository->forever($key->value, $snapshot->toArray());
        } else {
            $repository->put($key->value, $snapshot->toArray(), $seconds);
        }

        $this->rememberKey($key);
    }

    public function forget(GraphCacheKey $key): void
    {
        try {
            $this->repository()->forget($key->value);
        } catch (Throwable) {
            return;
        }
    }

    public function flush(): void
    {
        $repository = $this->repository();

        try {
            $keys = $repository->get(self::INDEX_KEY);

            foreach (is_array($keys) ? $keys : [] as $key) {
                if (is_string($key)) {
                    $repository->forget($key);
                }
            }

            $repository->forget(self::INDEX_KEY);
        } catch (Throwable) {
            return;
        }
    }

    private function rememberKey(GraphCacheKey $key): void
    {
        $repository = $this->repository();

        try {
            $keys = $repository->get(self::INDEX_KEY);
            $keys = is_array($keys) ? $keys : [];

            if (! in_array($key->value, $keys, true)) {
                $keys[] = $key->value;
                $repository->forever(self::INDEX_KEY, $keys);
            }
        } catch (Throwable) {
            return;
        }
    }

    private function repository(): Repository
    {
        return $this->cache->store($this->store);
    }

    private function intervalToSeconds(DateInterval $interval): int
    {
        $reference = new DateTimeImmutable;

        return $reference->add($interval)->getTimestamp() - $reference->getTimestamp();
    }
}
