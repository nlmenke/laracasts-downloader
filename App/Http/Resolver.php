<?php

/**
 * Http Functions.
 */

declare(strict_types=1);

namespace App\Http;

use App\Html\Parser;
use App\System\Controller as SystemController;
use App\Utils\Utils;
use App\Vimeo\VimeoDownloader;
use DOMDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Ubench;

/**
 * Class Resolver.
 */
class Resolver
{
    /**
     * Guzzle cookie.
     *
     * @var CookieJar
     */
    private readonly CookieJar $cookies;

    /**
     * @var SystemController
     */
    private SystemController $system;

    /**
     * @param Client $client
     * @param Ubench $bench
     *
     * @return void
     */
    public function __construct(
        private readonly Client $client,
        private readonly Ubench $bench
    ) {
        $this->cookies = new CookieJar;

        $this->system = new SystemController(new Filesystem(new LocalFilesystemAdapter(BASE_FOLDER)));
    }

    /**
     * Creates an NFO file for the series.
     *
     * @see https://kodi.wiki/view/NFO_files/TV_shows
     *
     * @param array  $series
     * @param string $seriesFolder
     *
     * @return void
     */
    public function createSeriesNfoFile(array $series, string $seriesFolder): void
    {
        if (file_exists($seriesFolder . DIRECTORY_SEPARATOR . 'tvshow.nfo')) {
            return;
        }

        $premiered = date_create_from_format('F j, Y', $series['episodes'][0]['published']);

        $difficulty = '';
        if ($series['difficulty_level'] !== null) {
            $difficulty = "<tag>{$series['difficulty_level']}</tag>";
        }

        $xml = <<<xml
<tvshow>
    <title>{$series['title']}</title>
    <plot>{$series['body']}</plot>
    <year>{$premiered->format('Y')}</year>
    <premiered>{$premiered->format('Y-m-d')}</premiered>
    <genre>Tutorial</genre>
    {$difficulty}
    <tag>{$series['taxonomy']}</tag>
    <tag>{$series['topic']}</tag>
    <studio>Laracasts</studio>
    <language>en</language>
    <mpaa>NR</mpaa>
    <actor>
        <name>{$series['author']['name']}</name>
        <role>Instructor</role>
        <thumb>{$series['author']['image']}</thumb>
    </actor>
</tvshow>
xml;

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        $dom->save($seriesFolder . DIRECTORY_SEPARATOR . 'tvshow.nfo', LIBXML_NOEMPTYTAG);
    }

    /**
     * Downloads the episode of the series.
     *
     * @param string $seriesSlug
     * @param array  $episode
     *
     * @throws FilesystemException
     * @throws GuzzleException
     *
     * @return bool
     */
    public function downloadEpisode(string $seriesSlug, array $episode): bool
    {
        try {
            $number = sprintf('%02d', $episode['number']);
            $name = $episode['title'];
            $filepath = $this->getFilename($episode);

            if (file_exists($filepath . '.mp4')) {
                return true;
            }

            Utils::writeln(
                sprintf(
                    'Download started: %s...',
                    $number . ' - ' . $name
                )
            );

            $source = $_ENV['DOWNLOAD_SOURCE'];

            if (! $source or $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($seriesSlug, $episode['number']);

                $isDownloaded = $this->downloadVideo($downloadLink, $filepath . '.mp4');
            } else {
                $vimeoDownloader = new VimeoDownloader;

                $isDownloaded = $vimeoDownloader->download($episode['vimeo_id'], $filepath . '.mp4');
            }

            $this->createEpisodeNfoFile($episode, $filepath);

            return $isDownloaded;
        } catch (RequestException $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * Downloads the series poster.
     *
     * @param array  $series
     * @param string $seriesFolder
     *
     * @return void
     */
    public function downloadPoster(array $series, string $seriesFolder): void
    {
        if (isset($series['thumbnail'])) {
            $fileInfo = pathinfo($series['thumbnail']);

            if (! file_exists('poster.' . $fileInfo['extension'])) {
                $poster = @file_get_contents($series['thumbnail']);

                if ($poster === false) {
                    if (strpos($series['thumbnail'], '//', 8) !== false) {
                        $series['thumbnail'] = preg_replace('/(?<!:)\/\//', '/', $series['thumbnail']);
                    }

                    if (preg_match('/\/(gif|jpe?g|png|svg|webp)\//', $series['thumbnail'])) {
                        $series['thumbnail'] = preg_replace('/\/(gif|jpe?g|png|svg|webp)\//', '/', $series['thumbnail']);
                    }

                    $poster = file_get_contents($series['thumbnail']);
                }

                file_put_contents($seriesFolder . DIRECTORY_SEPARATOR . 'poster.' . $fileInfo['extension'], $poster);
            }
        }
    }

    /**
     * Returns CSRF token.
     *
     * @throws GuzzleException
     *
     * @return string
     */
    public function getCsrfToken(): string
    {
        $this->client
            ->get(LARACASTS_BASE_URL, [
                'cookies' => $this->cookies,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Referer' => LARACASTS_BASE_URL,
                ],
                'verify' => false,
            ]);

        $token = current(
            array_filter($this->cookies->toArray(), fn ($cookie): bool => $cookie['Name'] === 'XSRF-TOKEN')
        );

        return urldecode((string)$token['Value']);
    }

    /**
     * Returns the HTML content of a URL.
     *
     * @param string $url
     *
     * @throws GuzzleException
     *
     * @return string
     */
    public function getHtml(string $url): string
    {
        return $this->client
            ->get($url, [
                'cookies' => $this->cookies,
                'verify' => false,
            ])
            ->getBody()
            ->getContents();
    }

    /**
     * Returns the HTML of the topics page.
     *
     * @throws GuzzleException
     *
     * @return string
     */
    public function getTopicsHtml(): string
    {
        return $this->client
            ->get(LARACASTS_BASE_URL . '/' . LARACASTS_TOPICS_PATH, [
                'cookies' => $this->cookies,
                'verify' => false,
            ])
            ->getBody()
            ->getContents();
    }

    /**
     * Attempts to authenticate the user.
     *
     * @param string $email
     * @param string $password
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function login(string $email, string $password): array
    {
        $token = $this->getCsrfToken();

        $response = $this->client
            ->post(LARACASTS_POST_LOGIN_PATH, [
                'cookies' => $this->cookies,
                'headers' => [
                    'X-XSRF-TOKEN' => $token,
                    'Content-Type' => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer' => LARACASTS_BASE_URL,
                ],
                'body' => json_encode([
                    'email' => $email,
                    'password' => $password,
                    'remember' => 1,
                ]),
                'verify' => false,
            ]);

        $html = $response->getBody()->getContents();

        return Parser::getUserData($html);
    }

    /**
     * Creates an NFO file for the episode.
     *
     * @see https://kodi.wiki/view/NFO_files/Episodes
     *
     * @param array  $episode
     * @param string $filepath
     *
     * @return void
     */
    private function createEpisodeNfoFile(array $episode, string $filepath): void
    {
        $publishedDate = date_create_from_format('F j, Y', $episode['published']);

        $xml = <<<xml
<episodedetails>
    <uniqueid type="vimeo" default="true">{$episode['vimeo_id']}</uniqueid>
    <season>{$episode['chapter']['number']}</season>
    <episode>{$episode['number']}</episode>
    <title>{$episode['title']}</title>
    <plot>{$episode['desc']}</plot>
    <aired>{$publishedDate->format('Y-m-d')}</aired>
    <writer>{$episode['series']['author']['name']}</writer>
    <director>{$episode['series']['author']['name']}</director>
    <mpaa>NR</mpaa>
    <actor>
        <name>{$episode['series']['author']['name']}</name>
        <role>Instructor</role>
        <thumb>{$episode['series']['author']['image']}</thumb>
    </actor>
</episodedetails>
xml;

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        $dom->save($filepath . '.nfo', LIBXML_NOEMPTYTAG);
    }

    /**
     * Helper to download the video.
     *
     * @param string $downloadUrl
     * @param string $saveTo
     *
     * @throws GuzzleException
     *
     * @return bool
     */
    private function downloadVideo(string $downloadUrl, string $saveTo): bool
    {
        $this->bench->start();

        $link = $this->prepareDownloadLink($downloadUrl);

        try {
            $this->client->request('GET', $link['url'], [
                'query' => $link['query'],
                'sink' => fopen($saveTo, 'a'),
                'progress' => fn ($downloadTotal, $downloadedBytes) => Utils::showProgressBar($downloadedBytes, $downloadTotal),
            ]);
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;

            return false;
        }

        $this->bench->end();

        Utils::write(
            sprintf(
                'Elapsed time: %s, Memory: %s',
                $this->bench->getTime(),
                $this->bench->getMemoryUsage()
            )
        );

        return true;
    }

    /**
     * @param array $episode
     *
     * @throws FilesystemException
     *
     * @return string
     */
    private function getFilename(array $episode): string
    {
        $series = $episode['series'];
        $seriesTitleYear = Utils::cleanNameForWindows($series['title']) . ' (' . $series['year'] . ')';

        $filename = BASE_FOLDER
            . DIRECTORY_SEPARATOR
            . SERIES_FOLDER
            . DIRECTORY_SEPARATOR
            . $seriesTitleYear
            . DIRECTORY_SEPARATOR;

        // separate chapters into season folders
        $chapterNumber = '01';
        if (isset($episode['chapter']['number'])) {
            $chapterNumber = sprintf('%02d', $episode['chapter']['number']);
        }

        $chapterFolder = 'Season ' . $chapterNumber;

        $this->system->createFolderIfNotExists(
            SERIES_FOLDER
            . DIRECTORY_SEPARATOR
            . $seriesTitleYear
            . DIRECTORY_SEPARATOR
            . $chapterFolder
        );

        $episodeNumber = sprintf('%02d', $episode['number']);

        $filename .= $chapterFolder
            . DIRECTORY_SEPARATOR
            . $seriesTitleYear
            . ' - s' . $chapterNumber . 'e' . $episodeNumber
            . ' - ' . Utils::cleanNameForWindows($episode['title']);

        return $filename;
    }

    /**
     * Get the Laracasts download link for the episode.
     *
     * @param string $seriesSlug
     * @param int    $episodeNumber
     *
     * @throws GuzzleException
     *
     * @return string
     */
    private function getLaracastsLink(string $seriesSlug, int $episodeNumber): string
    {
        $episodeHtml = $this->getHtml(LARACASTS_SERIES_PATH . '/' . $seriesSlug . '/episodes/' . $episodeNumber);

        return Parser::getEpisodeDownloadLink($episodeHtml);
    }

    /**
     * Helper to get the Location header.
     *
     * @param string $url
     *
     * @throws GuzzleException
     *
     * @return string
     */
    private function getRedirectUrl(string $url): string
    {
        $response = $this->client->get($url, [
            'cookies' => $this->cookies,
            'allow_redirects' => false,
            'verify' => false,
        ]);

        return $response->getHeader('Location')[0] ?? '';
    }

    /**
     * @param string $url
     *
     * @throws GuzzleException
     *
     * @return array
     */
    private function prepareDownloadLink(string $url): array
    {
        $parts = parse_url($this->getRedirectUrl($url));

        return [
            'query' => $parts['query'],
            'url' => $parts['scheme'] . '://' . $parts['host'] . $parts['path'],
        ];
    }
}
