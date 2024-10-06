<?php
/**
 * Vimeo Video DTO.
 */

namespace App\Vimeo\DTO;

/**
 * Class VideoDTO.
 *
 * @package App\Vimeo\DTO
 */
class VideoDTO
{
    /**
     * @var string
     */
    private $masterUrl;

    /**
     * @var array
     */
    private $streams;

    /**
     * @return string
     */
    public function getMasterUrl(): string
    {
        return $this->masterUrl;
    }

    /**
     * @return array
     */
    public function getStreams(): array
    {
        return $this->streams;
    }

    /**
     * @return string|null
     */
    public function getVideoIdByQuality(): ?string
    {
        $id = null;

        foreach ($this->getStreams() as $stream) {
            if ($stream['quality'] === getenv('VIDEO_QUALITY')) {
                $id = explode('-', $stream['id'])[0];
            }
        }

        return $id;
    }

    /**
     * @param string $masterUrl
     *
     * @return self
     */
    public function setMasterUrl(string $masterUrl): VideoDTO
    {
        $this->masterUrl = $masterUrl;

        return $this;
    }

    /**
     * @param array $streams
     *
     * @return self
     */
    public function setStreams(array $streams): VideoDTO
    {
        $this->streams = $streams;

        return $this;
    }
}
