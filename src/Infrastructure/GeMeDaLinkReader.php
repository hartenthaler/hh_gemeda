<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use Fisharebest\Webtrees\Individual;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaLink;

/**
 * Reads already stored GeMeDa-related EXID blocks without contacting the API.
 * Claim creation and API enrichment are deliberately not part of this scaffold.
 */
final class GeMeDaLinkReader
{
    /** @return list<GeMeDaLink> */
    public function read(Individual $individual): array
    {
        $lines = preg_split('/\R/u', $individual->gedcom()) ?: [];
        $links = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^1 (?:EXID|_EXID)\s+(.+)$/u', $line, $match) !== 1) {
                continue;
            }

            $type = null;
            for ($child = $index + 1; $child < count($lines); $child++) {
                if (preg_match('/^2 TYPE\s+(.+)$/u', $lines[$child], $typeMatch) === 1) {
                    $type = trim($typeMatch[1]);
                    break;
                }

                if (preg_match('/^1 /u', $lines[$child]) === 1) {
                    break;
                }
            }

            if ($type === null || !$this->isGeMeDaType($type)) {
                continue;
            }

            $links[] = new GeMeDaLink(strtolower($type), trim($match[1]));
        }

        return $links;
    }

    private function isGeMeDaType(string $type): bool
    {
        return in_array(strtolower(trim($type)), ['gemeda', 'gedbas', 'ofb', 'greifx'], true);
    }
}
