<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Domain;

/** A normalized result from the (still evolving) GeMeDa search API. */
final class GeMeDaSearchResult
{
    /**
     * @param list<array{provider:string,external_id:string,type_uri:?string,url:?string,label:?string}> $sources
     * @param list<string> $matchReasons
     */
    public function __construct(
        public readonly string $personHash,
        public readonly string $displayName,
        public readonly array $sources = [],
        public readonly ?float $score = null,
        public readonly array $matchReasons = [],
    ) {
    }
}
