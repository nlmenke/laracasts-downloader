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
            'title' => rtrim($series['title']),
            'body' => rtrim(strip_tags(str_replace('</p><p>', "\n\n", $series['body']))),
            'thumbnail' => $series['thumbnail'],
            'episode_count' => $series['episodeCount'],
            'is_complete' => $series['complete'],
            'difficulty_level' => $series['difficulty_level'],
            'taxonomy' => $series['taxonomy']['name'],
            'author' => [
                'name' => $series['author']['profile']['full_name'],
                'image' => $series['author']['avatar'],
            ],
        ];
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

        $series = $data['props']['series'];
        $chapters = $series['chapters'];

        $seriesYear = date_create_from_format('F j, Y', $chapters[0]['episodes'][0]['dateSegments']['published'])->format('Y');

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
                    'title' => rtrim($episode['title']),
                    'vimeo_id' => $episode['vimeoId'],
                    'number' => $episode['position'],
                    'desc' => rtrim(strip_tags(str_replace('</p><p>', "\n\n", $episode['body'] ?? $episode['summary'] ?? $episode['excerpt']))),
                    'published' => $episode['dateSegments']['published'],
                    'series' => [
                        'title' => rtrim($series['title']),
                        'desc' => trim(strip_tags(str_replace('</p><p>', "\n\n", $series['body']))),
                        'thumb' => $series['thumbnail'],
                        'collections' => array_unique([$series['difficultyLevel'], $series['taxonomy']['name']]),
                        'author' => [
                            'name' => $series['author']['profile']['full_name'],
                            'image' => $series['author']['avatar'],
                        ],
                        'year' => $seriesYear,
                    ],
                    'chapters' => [
                        'number' => $chapter['number'],
                        'heading' => $chapter['heading'],
                    ],
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
                'name' => $topic['name'],
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
