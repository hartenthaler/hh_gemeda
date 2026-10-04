<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Shared\Internationalization;

/**
 * Load module catalogues on webtrees 2.2 and 2.3.
 */
final class TranslationLoader
{
    /** @return array<string, string> */
    public static function load(string $languageDirectory, string $language): array
    {
        $directory = rtrim($languageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $poFile = $directory . $language . '.po';
        $moFile = $directory . $language . '.mo';

        $modern = 'Fisharebest\\Webtrees\\I18N\\Translation';

        if (class_exists($modern)) {
            $file = is_file($poFile) ? $poFile : (is_file($moFile) ? $moFile : null);

            if ($file !== null) {
                $stream = fopen($file, 'rb');

                if ($stream !== false) {
                    try {
                        $translation = str_ends_with($file, '.po')
                            ? $modern::fromPoStream($stream)
                            : $modern::fromMoStream($stream);

                        return $translation->toArray();
                    } finally {
                        fclose($stream);
                    }
                }
            }
        }

        $legacy = 'Fisharebest\\Localization\\Translation';

        if (class_exists($legacy)) {
            if (is_file($poFile)) {
                return (new $legacy($poFile))->asArray();
            }

            if (is_file($moFile)) {
                return (new $legacy($moFile))->asArray();
            }
        }

        return [];
    }
}
