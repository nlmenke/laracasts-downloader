<?php
/**
 * Dom Parser.
 */

namespace App\Html;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Parser.
 *
 * @package App\Html
 */
class Parser
{
    /**
     * @param string $html
     *
     * @return array
     */
    public static function extractLarabitsSeries(string $html): array
    {
        $html = str_replace('\/', '/', html_entity_decode($html));

        preg_match_all('/\/series\/([a-z-]+-larabits)/', $html, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Extracts the required data for each series.
     *
     * @param array $series
     *
     * @return array
     */
    public static function extractSeriesData(array $series): array
    {
        return [
            'slug' => $series['slug'],
            'path' => LARACASTS_BASE_URL . $series['path'],
            'episode_count' => $series['episodeCount'],
            'is_complete' => $series['complete'],
        ];
    }

    /**
     * @param string $html
     *
     * @return string
     */
    public static function getCsrfToken(string $html): string
    {
        preg_match('/"csrfToken": \'(\S+)\'/', $html, $matches);

        return $matches[1];
    }

    /**
     * @param string $episodeHtml
     *
     * @return string
     */
    public static function getEpisodeDownloadLink(string $episodeHtml): string
    {
        $data = self::getData($episodeHtml);

        return $data['props']['downloadLink'];
    }

    /**
     * Returns a full list of episodes for given series.
     *
     * @param string        $episodeHtml
     * @param array<number> $filteredEpisodes
     *
     * @return array
     */
    public static function getEpisodesData(string $episodeHtml, array $filteredEpisodes = []): array
    {
        $episodes = [];

        $data = self::getData($episodeHtml);

        $chapters = $data['props']['series']['chapters'];

        foreach ($chapters as $chapter) {
            foreach ($chapter['episodes'] as $episode) {
                // TODO: It's not the parser responsibility to filter episodes
                if (!empty($filteredEpisodes) && !in_array($episode['position'], $filteredEpisodes)) {
                    continue;
                }

                // vimeoId is null for upcoming episodes
                if (!$episode['vimeoId']) {
                    continue;
                }

                $episodes[] = [
                    'title' => $episode['title'],
                    'vimeo_id' => $episode['vimeoId'],
                    'number' => $episode['position'],
                ];
            }
        }

        return $episodes;
    }

    /**
     * @param string $seriesHtml
     *
     * @return array
     */
    public static function getSeriesData(string $seriesHtml): array
    {
        $data = self::getData($seriesHtml);

        return self::extractSeriesData($data['props']['series']);
    }

    /**
     * Returns a full list of series for given topic.
     *
     * @param string $html
     *
     * @return array
     */
    public static function getSeriesDataFromTopic(string $html): array
    {
        $data = self::getData($html);

        $series = $data['props']['topic']['series'] ?? [];

        return array_combine(
            array_column($series, 'slug'),
            array_map(function ($series) {
                return self::extractSeriesData($series);
            }, $series)
        );
    }

    /**
     * Returns a list of topics data.
     *
     * @param string $html
     *
     * @return array
     */
    public static function getTopicsData(string $html): array
    {
        $data = self::getData($html);

        return array_map(function ($topic) {
            return [
                'slug' => str_replace(LARACASTS_BASE_URL . '/topics/', '', $topic['path']),
                'path' => $topic['path'],
                'episode_count' => $topic['episode_count'],
                'series_count' => $topic['series_count'],
            ];
        }, $data['props']['topics']);
    }

    /**
     * @param string $html
     *
     * @return array
     */
    public static function getUserData(string $html): array
    {
        $data = self::getData($html);

        $props = $data['props'];

        return [
            'error' => empty($props['errors']) ? null : $props['errors']['auth'],
            'signedIn' => $props['auth']['signedIn'],
            'data' => $props['auth']['user'],
        ];
    }

    /**
     * Returns decoded version of data-page attribute.
     *
     * @param string $html
     *
     * @return array
     */
    private static function getData(string $html): array
    {
        $parser = new Crawler($html);

        $data = $parser->filter('#app')->attr('data-page');

        return json_decode($data, true);
    }
}
