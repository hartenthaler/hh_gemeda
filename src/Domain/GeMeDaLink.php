<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Domain;

final class GeMeDaLink
{
    public function __construct(
        public readonly string $provider,
        public readonly string $externalId,
        public readonly ?string $externalUrl = null,
        public readonly ?string $labelSnapshot = null,
    ) {
    }
}
