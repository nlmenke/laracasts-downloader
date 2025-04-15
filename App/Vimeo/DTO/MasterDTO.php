<?php

/**
 * Vimeo Master DTO.
 */

namespace App\Vimeo\DTO;

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\UriInterface;

/**
 * Class MasterDTO.
 */
class MasterDTO
{
    /**
     * @var array|null
     */
    private ?array $audios = null;

    /**
     * @var string|null
     */
    private ?string $baseUrl = null;

    /**
     * @var string|null
     */
    private ?string $clipId = null;

    /**
     * @var string|null
     */
    private ?string $masterUrl = null;

    /**
     * @var array|null
     */
    private ?array $videos = null;

    /**
     * @return array
     */
    public function getAudio(): array
    {
        $audios = $this->getAudios();

        usort($audios, fn ($a, $b): int => $a['bitrate'] <=> $b['bitrate']);

        return end($audios);
    }

    /**
     * @return array
     */
    public function getAudios(): array
    {
        return array_map(function (array $audio) {
            $audio['extension'] = '.m4a';

            return $audio;
        }, $this->audios);
    }

    /**
     * @return string|null
     */
    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @return string|null
     */
    public function getClipId(): ?string
    {
        return $this->clipId;
    }

    /**
     * @return UriInterface
     */
    public function getMasterUrl(): UriInterface
    {
        return Utils::uriFor($this->masterUrl);
    }

    /**
     * Get video by id or the one with the highest quality.
     *
     * @param string|null $id
     *
     * @return array
     */
    public function getVideoById(?string $id): array
    {
        $videos = $this->getVideos();

        if (! is_null($id)) {
            $ids = array_column($videos, 'id');
            $key = array_search($id, $ids);

            if ($key !== false) {
                return $videos[$key];
            }

            // Previously, the Vimeo ID matched the first segment of the UUID.
            // so we keep it for backward compatibility
            $key = array_search(explode('-', $id)[0], $ids);

            if ($key !== false) {
                return $videos[$key];
            }
        }

        usort($videos, fn ($a, $b): int => $a['height'] <=> $b['height']);

        return end($videos);
    }

    /**
     * @return array
     */
    public function getVideos(): array
    {
        return array_map(function (array $video) {
            $video['extension'] = '.m4v';

            return $video;
        }, $this->videos);
    }

    /**
     * Make final URL from combination of absolute and relate ones.
     *
     * @param string $url
     *
     * @return string
     */
    public function resolveUrl(string $url): string
    {
        return (string)UriResolver::resolve(
            $this->getMasterUrl(),
            Utils::uriFor($this->getBaseUrl() . $url)
        );
    }

    /**
     * @param array $audios
     *
     * @return $this
     */
    public function setAudios(array $audios): MasterDTO
    {
        $this->audios = $audios;

        return $this;
    }

    /**
     * @param string $baseUrl
     *
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): MasterDTO
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @param string $clipId
     *
     * @return $this
     */
    public function setClipId(string $clipId): MasterDTO
    {
        $this->clipId = $clipId;

        return $this;
    }

    /**
     * @param string $masterUrl
     *
     * @return $this
     */
    public function setMasterUrl(string $masterUrl): MasterDTO
    {
        $this->masterUrl = $masterUrl;

        return $this;
    }

    /**
     * @param array $videos
     *
     * @return $this
     */
    public function setVideos(array $videos): MasterDTO
    {
        $this->videos = $videos;

        return $this;
    }
}
