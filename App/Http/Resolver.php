<?php
/**
 * Http Functions.
 */

namespace App\Http;

use App\Html\Parser;
use App\System\Controller as SystemController;
use App\Utils\Utils;
use App\Vimeo\VimeoDownloader;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Query;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Filesystem;
use Ubench;

/**
 * Class Resolver.
 *
 * @package App\Http
 */
class Resolver
{
    /**
     * Ubench lib.
     *
     * @var Ubench
     */
    private $bench;

    /**
     * Guzzle client.
     *
     * @var Client
     */
    private $client;

    /**
     * Guzzle cookie.
     *
     * @var CookieJar
     */
    private $cookies;

    /**
     * @var SystemController
     */
    private $system;

    /**
     * @param Client $client
     * @param Ubench $bench
     *
     * @return void
     */
    public function __construct(Client $client, Ubench $bench)
    {
        $this->client = $client;
        $this->cookies = new CookieJar();
        $this->bench = $bench;

        $this->system = new SystemController(new Filesystem(new LocalAdapter(BASE_FOLDER)));
    }

    /**
     * Downloads the episode of the series.
     *
     * @param string $seriesSlug
     * @param array  $episode
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

            $source = getenv('DOWNLOAD_SOURCE');

            if (!$source or $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($seriesSlug, $episode['number']);

                return $this->downloadVideo($downloadLink, $filepath . '.mp4');
            } else {
                $vimeoDownloader = new VimeoDownloader();

                return $vimeoDownloader->download($episode['vimeo_id'], $filepath . '.mp4');
            }
        } catch (RequestException $e) {
            Utils::write($e->getMessage());

            return false;
        }
    }

    /**
     * Returns CSRF token.
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
            array_filter($this->cookies->toArray(), function ($cookie) {
                return $cookie['Name'] === 'XSRF-TOKEN';
            })
        );

        return urldecode($token['Value']);
    }

    /**
     * Returns the HTML content of a URL.
     *
     * @param string $url
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
     * @return array
     */
    public function login(string $email, string $password): array
    {
        $token = $this->getCsrfToken();

        $response = $this->client
            ->post(LARACASTS_POST_LOGIN_PATH, [
                'cookies' => $this->cookies,
                'headers' => [
                    "X-XSRF-TOKEN" => $token,
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
     * Helper to download the video.
     *
     * @param string $downloadUrl
     * @param string $saveTo
     *
     * @return bool
     */
    private function downloadVideo(string $downloadUrl, string $saveTo): bool
    {
        $this->bench->start();

        $link = $this->prepareDownloadLink($downloadUrl);

        try {
            $downloadedBytes = file_exists($saveTo) ? filesize($saveTo) : 0;
            $req = $this->client->createRequest('GET', $link['url'], [
                'query' => Query::fromString($link['query'], false),
                'save_to' => fopen($saveTo, 'a'),
            ]);

            Utils::showProgressBar($req, $downloadedBytes);

            $this->client->send($req);
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
     * @return string
     */
    private function getFilename(array $episode): string {
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
        if ($episode['chapter']['number'] !== null) {
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
     * @return string
     */
    private function getLaracastsLink(string $seriesSlug, int $episodeNumber): string
    {
        $episodeHtml = $this->getHtml("series/$seriesSlug/episodes/$episodeNumber");

        return Parser::getEpisodeDownloadLink($episodeHtml);
    }

    /**
     * Helper to get the Location header.
     *
     * @param string $url
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

        return $response->getHeader('Location');
    }

    /**
     * @param string $url
     *
     * @return array
     */
    private function prepareDownloadLink(string $url): array
    {
        $url = $this->getRedirectUrl($url);
        $parts = parse_url($url);

        return [
            'query' => $parts['query'],
            'url' => $parts['scheme'] . '://' . $parts['host'] . $parts['path'],
        ];
    }
}
