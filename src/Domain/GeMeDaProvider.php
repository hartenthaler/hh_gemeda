<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Domain;

/** A provider exposed by the GeMeDa meta-search catalogue. */
final class GeMeDaProvider
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $metaSearchId,
    ) {
    }
}
