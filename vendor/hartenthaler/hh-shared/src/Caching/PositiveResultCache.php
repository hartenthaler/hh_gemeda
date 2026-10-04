<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Shared\Caching;

use Fisharebest\Webtrees\Registry;
use Throwable;

/**
 * Cache values only when the resolver returns a non-null result.
 *
 * This is useful for external checks: successful responses can be reused,
 * while temporary failures are retried on the next request.
 */
final class PositiveResultCache
{
    /**
     * @template T
     * @param callable(): T|null $resolver
     * @return T|null
     */
    public function remember(string $key, callable $resolver, int $ttl = 86400): mixed
    {
        try {
            if (!class_exists(Registry::class)) {
                return $resolver();
            }

            $cache = Registry::cache()->file();
            $cached = $cache->remember(
                'hh_shared_positive:' . $key,
                static function () use ($resolver): array {
                    $value = $resolver();

                    return $value === null
                        ? ['cached' => false]
                        : ['cached' => true, 'value' => $value];
                },
                $ttl,
            );

            if (is_array($cached) && ($cached['cached'] ?? false) === true && array_key_exists('value', $cached)) {
                return $cached['value'];
            }

            // Do not leave a failed result in the shared cache.
            $cache->forget('hh_shared_positive:' . $key);

            return null;
        } catch (Throwable) {
            return $resolver();
        }
    }
}
