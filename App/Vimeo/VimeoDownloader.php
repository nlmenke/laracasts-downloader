<?php
/**
 * Vimeo Downloader.
 */

namespace App\Vimeo;

use App\Utils\Utils;
use GuzzleHttp\Client;

/**
 * Class VimeoDownloader.
 *
 * @package App\Vimeo
 */
class VimeoDownloader
{
    /**
     * @var Client
     */
    public $client;

    /**
     * @var VimeoRepository
     */
    private $repository;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->client = new Client();

        $this->repository = new VimeoRepository($this->client);
    }

    /**
     * @param int    $vimeoId
     * @param string $filepath
     *
     * @return bool
     */
    public function download(int $vimeoId, string $filepath): bool
    {
        if (file_exists($filepath)) {
            return true;
        }

        $video = $this->repository->get($vimeoId);

        $master = $this->repository->getMaster($video);

        $sources = [];
        $sources[] = $master->getVideoById($video->getVideoIdByQuality());
        $sources[] = $master->getAudio();

        $filenames = [];

        foreach ($sources as $source) {
            $filename = $master->getClipId() . $source['extension'];

            $this->downloadSource(
                $master->resolveUrl($source['base_url']),
                $source,
                $filename
            );
            $filenames[] = $filename;
        }

        return $this->mergeSources($filenames[0], $filenames[1], $filepath);
    }

    /**
     * @param array  $segmentUrls
     * @param string $filepath
     * @param array  $sizes
     *
     * @return void
     */
    private function downloadSegments(
        array $segmentUrls,
        string $filepath,
        array $sizes
    ): void {
        $type = strpos($filepath, 'm4v') !== false ? 'video' : 'audio';
        Utils::writeln("Downloading $type...");

        $downloadedBytes = 0;

        $totalBytes = array_sum($sizes);

        foreach ($segmentUrls as $index => $segmentUrl) {
            $request = $this->client->createRequest('GET', $segmentUrl, [
                'save_to' => fopen($filepath, 'a'),
            ]);

            Utils::showProgressBar($request, $downloadedBytes, $totalBytes);

            $this->client->send($request);

            $downloadedBytes += $sizes[$index];
        }
    }

    /**
     * @param string $baseUrl
     * @param array  $sourceData
     * @param string $filepath
     *
     * @return void
     */
    private function downloadSource(
        string $baseUrl,
        array $sourceData,
        string $filepath
    ): void {
        file_put_contents($filepath, base64_decode($sourceData['init_segment'], true));

        $segmentURLs = array_map(function ($segment) use ($baseUrl) {
            return $baseUrl . $segment['url'];
        }, $sourceData['segments']);

        $sizes = array_column($sourceData['segments'], 'size');

        $this->downloadSegments($segmentURLs, $filepath, $sizes);
    }

    /**
     * @param string $videoPath
     * @param string $audioPath
     * @param string $outputPath
     *
     * @return bool
     */
    private function mergeSources(
        string $videoPath,
        string $audioPath,
        string $outputPath
    ): bool {
        $code = 0;
        $output = [];

        $outputPath = str_replace(['$'], ['\$'], $outputPath);

        if (PHP_OS == 'WINNT') {
            $command = "ffmpeg -i \"$videoPath\" -i \"$audioPath\" -vcodec copy -acodec copy -strict -2 \"$outputPath\" 2> nul";
        } else {
            $command = "ffmpeg -i \"$videoPath\" -i \"$audioPath\" -vcodec copy -acodec copy -strict -2 \"$outputPath\" >/dev/null 2>&1";
        }

        exec($command, $output, $code);

        if ($code == 0) {
            unlink($videoPath);
            unlink($audioPath);

            return true;
        }

        return false;
    }
}
