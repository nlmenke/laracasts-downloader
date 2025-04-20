<?php

/**
 * Utilities.
 */

declare(strict_types=1);

namespace App\Utils;

/**
 * Class Utils.
 */
class Utils
{
    /**
     * Echos text in a nice box.
     *
     * @param string $text
     */
    public static function box(string $text): void
    {
        echo self::newLine();
        echo '====================================' . self::newLine();
        echo $text . self::newLine();
        echo '====================================' . self::newLine();
    }

    /**
     * Removes special chars that windows does not support.
     *
     * @param string $name
     *
     * @return string|null
     */
    public static function cleanNameForWindows(string $name): ?string
    {
        return preg_replace('/[^A-Za-z0-9`~!@#$%^&()\-_+=[\]{};\',.\/ ]/', '', $name);
    }

    /**
     * Compare two arrays and returns the diff array.
     *
     * @param array $onlineListArray
     * @param array $localListArray
     *
     * @return array
     */
    public static function compareLocalAndOnlineSeries(array $onlineListArray, array $localListArray): array
    {
        $seriesCollection = new SeriesCollection([]);

        foreach ($onlineListArray as $seriesSlug => $series) {
            if (array_key_exists($seriesSlug, $localListArray)) {
                if ($series['episode_count'] == count($localListArray[$seriesSlug])) {
                    continue;
                }

                $episodes = $series['episodes'];
                $series['episodes'] = [];

                foreach ($episodes as $episode) {
                    if (! in_array($episode['number'], $localListArray[$seriesSlug])) {
                        $series['episodes'][] = $episode;
                    }
                }
            }

            $seriesCollection->add($series);
        }

        return $seriesCollection->get();
    }

    /**
     * Counts the episodes from the array.
     *
     * @param array $array
     *
     * @return int
     */
    public static function countEpisodes(array $array): int
    {
        $total = 0;

        foreach ($array as $series) {
            $total += count($series['episodes']);
        }

        return $total;
    }

    /**
     * Converts bytes to precision.
     *
     * @param int $bytes
     * @param int $precision
     *
     * @return string
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Calculate a percentage.
     *
     * @param int $cur
     * @param int $total
     *
     * @return float|int
     */
    public static function getPercentage(int $cur, int $total): float|int
    {
        // hide warning division by zero
        if ($total === 0) {
            return 0;
        }

        return round(@($cur / $total * 100));
    }

    /**
     * New line supporting cli or browser.
     *
     * @return string
     */
    public static function newLine(): string
    {
        if (php_sapi_name() == 'cli') {
            return "\n";
        }

        return '<br>';
    }

    /**
     * @param int      $downloadedBytes
     * @param int|null $totalBytes
     *
     * @return void
     */
    public static function showProgressBar(
        int $downloadedBytes,
        ?int $totalBytes = null
    ): void {
        if (php_sapi_name() == 'cli') {
            printf(
                "> Downloaded %s of %s (%d%%)\r",
                Utils::formatBytes($downloadedBytes),
                Utils::formatBytes($totalBytes),
                Utils::getPercentage($downloadedBytes, $totalBytes)
            );
        }
    }

    /**
     * Echos a message.
     *
     * @param string $text
     *
     * @return void
     */
    public static function write(string $text): void
    {
        echo '> ' . $text . self::newLine();
    }

    /**
     * Echos a message in a new line.
     *
     * @param string $text
     *
     * @return void
     */
    public static function writeln(string $text): void
    {
        echo self::newLine();
        echo '> ' . $text . self::newLine();
    }
}
