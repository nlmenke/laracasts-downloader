<?php
/**
 * Laracasts Controller.
 */

namespace App\Laracasts;

use App\Html\Parser;
use App\Http\Resolver;
use App\Utils\SeriesCollection;
use App\Utils\Utils;

/**
 * Class Controller.
 *
 * @package App\Laracasts
 */
class Controller
{
    /**
     * @var Resolver
     */
    private $client;

    /**
     * @param Resolver $client
     *
     * @return void
     */
    public function __construct(Resolver $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    public function getFilteredSeries(array $filters): array
    {
        $seriesCollection = new SeriesCollection([]);

        foreach ($filters as $seriesSlug => $filteredEpisodes) {
            $seriesHtml = $this->client->getHtml("series/$seriesSlug");

            $series = Parser::getSeriesData($seriesHtml);

            $episodeHtml = $this->client->getHtml($series['path'] . '/episodes/1');

            $series['episodes'] = Parser::getEpisodesData($episodeHtml, $filteredEpisodes);

            $seriesCollection->add($series);
        }

        return $seriesCollection->get();
    }

    /**
     * Gets all series using scraping.
     *
     * @param array $cachedData
     * @param bool  $cacheOnly
     *
     * @return array
     */
    public function getSeries(array $cachedData, bool $cacheOnly = false): array
    {
        $seriesCollection = new SeriesCollection($cachedData);

        if ($cacheOnly) {
            return $seriesCollection->get();
        }

        $topics = Parser::getTopicsData($this->client->getTopicsHtml());

        foreach ($topics as $topic) {
            // TODO: It's not gonna work fine because each series may have multiple topics
            if ($this->isTopicUpdated($seriesCollection, $topic)) {
                continue;
            }

            Utils::box($topic['slug']);

            $topicHtml = $this->client->getHtml($topic['path']);

            $seriesData = Parser::getSeriesDataFromTopic($topicHtml);

            foreach ($seriesData as $series) {
                if ($this->isSeriesUpdated($seriesCollection, $series)) {
                    continue;
                }

                Utils::writeln("Getting series: {$series['slug']}...");

                $series['topic'] = $topic['slug'];

                $episodeHtml = $this->client->getHtml($series['path'] . '/episodes/1');

                $series['episodes'] = Parser::getEpisodesData($episodeHtml);

                $seriesCollection->add($series);
            }
        }

        Utils::box('Larabits');

        $larabitsHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/bits');

        $bits = Parser::extractLarabitsSeries($larabitsHtml);

        foreach ($bits as $bit) {
            Utils::writeln("Getting series: $bit...");

            $seriesHtml = $this->client->getHtml(LARACASTS_BASE_URL . '/series/' . $bit);

            $series = Parser::getSeriesData($seriesHtml);

            $series['topic'] = 'larabits';

            $episodeHtml = $this->client->getHtml($series['path'] . '/episodes/1');

            $series['episodes'] = Parser::getEpisodesData($episodeHtml);

            $seriesCollection->add($series);
        }

        return $seriesCollection->get();
    }

    /**
     * Determine if specific topic has been changed compared to cached data.
     *
     * @param SeriesCollection $series
     * @param array            $topic
     *
     * @return bool
     * */
    public function isTopicUpdated(SeriesCollection $series, array $topic): bool
    {
        $series = $series->where('topic', $topic['slug']);

        return $series->exists()
            && $topic['series_count'] == $series->count()
            && $topic['episode_count'] == $series->sum('episode_count', true);
    }

    /**
     * Determine if specific series has been changed compared to cached data.
     *
     * @param SeriesCollection $seriesList
     * @param array            $series
     *
     * @return bool
     */
    private function isSeriesUpdated(SeriesCollection $seriesList, array $series): bool
    {
        $target = $seriesList->where('slug', $series['slug'])->first();

        return !is_null($target)
            && (count($target['episodes']) == $series['episode_count']);
    }
}
