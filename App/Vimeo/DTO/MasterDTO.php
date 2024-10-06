<?php
/**
 * Vimeo Master DTO.
 */

namespace App\Vimeo\DTO;

use GuzzleHttp\Psr7;
use Psr\Http\Message\UriInterface;

/**
 * Class MasterDTO.
 *
 * @package App\Vimeo\DTO
 */
class MasterDTO
{
    /**
     * @var array
     */
    private $audios;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $clipId;

    /**
     * @var string
     */
    private $masterUrl;

    /**
     * @var array
     */
    private $videos;

    /**
     * @return array
     */
    public function getAudio(): array
    {
        $audios = $this->getAudios();

        usort($audios, function ($a, $b) {
            return $a['bitrate'] <=> $b['bitrate'];
        });

        return end($audios);
    }

    /**
     * @return array
     */
    public function getAudios(): array
    {
        return array_map(function ($audio) {
            $audio['extension'] = '.m4a';

            return $audio;
        }, $this->audios);
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return string
     */
    public function getClipId(): string
    {
        return $this->clipId;
    }

    /**
     * @return UriInterface
     */
    public function getMasterUrl(): UriInterface
    {
        return Psr7\Utils::uriFor($this->masterUrl);
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

        if (!is_null($id)) {
            $ids = array_column($videos, 'id');
            $key = array_search($id, $ids);

            if ($key !== false) {
                return $videos[$key];
            }
        }

        usort($videos, function ($a, $b) {
            return $a['height'] <=> $b['height'];
        });

        return end($videos);
    }

    /**
     * @return array
     */
    public function getVideos(): array
    {
        return array_map(function ($video) {
            $video['extension'] = '.m4v';

            return $video;
        }, $this->videos);
    }

    /**
     * Make final URL from combination of absolute and relate ones.
     *
     * @param UriInterface|string $url
     *
     * @return string
     */
    public function resolveUrl($url): string
    {
        return (string)Psr7\UriResolver::resolve(
            $this->getMasterUrl(),
            Psr7\Utils::uriFor($this->getBaseUrl() . $url)
        );
    }

    /**
     * @param array $audios
     *
     * @return self
     */
    public function setAudios(array $audios): MasterDTO
    {
        $this->audios = $audios;

        return $this;
    }

    /**
     * @param string $baseUrl
     *
     * @return self
     */
    public function setBaseUrl(string $baseUrl): MasterDTO
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @param string $clipId
     *
     * @return self
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
     * @return self
     */
    public function setVideos(array $videos): MasterDTO
    {
        $this->videos = $videos;

        return $this;
    }
}
