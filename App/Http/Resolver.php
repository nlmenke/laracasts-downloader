<?php
/**
 * Http Functions.
 */

namespace App\Http;

use App\Html\Parser;
use App\Utils\Utils;
use App\Vimeo\VimeoDownloader;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Query;
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
     * Reattempt download on connection fail.
     *
     * @var int
     */
    private $retryDownload = false;

    /**
     * @param Client $client
     * @param Ubench $bench
     * @param bool   $retryDownload
     *
     * @return void
     */
    public function __construct(Client $client, Ubench $bench, bool $retryDownload = false)
    {
        $this->client = $client;
        $this->cookies = new CookieJar();
        $this->bench = $bench;
        $this->retryDownload = $retryDownload;
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
            $filepath = $this->getFilename($seriesSlug, $number, $name);

            Utils::writeln(
                sprintf(
                    'Download started: %s... Saving in %s',
                    $number . ' - ' . $name,
                    SERIES_FOLDER . '/' . $seriesSlug
                )
            );

            $source = getenv('DOWNLOAD_SOURCE');

            if (!$source or $source === 'laracasts') {
                $downloadLink = $this->getLaracastsLink($seriesSlug, $episode['number']);

                return $this->downloadVideo($downloadLink, $filepath);
            } else {
                $vimeoDownloader = new VimeoDownloader();

                return $vimeoDownloader->download($episode['vimeo_id'], $filepath);
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
     * @param string $seriesSlug
     * @param string $number
     * @param string $episodeName
     *
     * @return string
     */
    private function getFilename(
        string $seriesSlug,
        string $number,
        string $episodeName
    ): string {
        return BASE_FOLDER
            . DIRECTORY_SEPARATOR
            . SERIES_FOLDER
            . DIRECTORY_SEPARATOR
            . $seriesSlug
            . DIRECTORY_SEPARATOR
            . $number
            . '-'
            . Utils::parseEpisodeName($episodeName)
            . '.mp4';
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
