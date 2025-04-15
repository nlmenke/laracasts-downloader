<?php

/**
 * Vimeo Video DTO.
 */

namespace App\Vimeo\DTO;

/**
 * Class VideoDTO.
 */
class VideoDTO
{
    /**
     * @var string|null
     */
    private ?string $masterUrl = null;

    /**
     * @var array|null
     */
    private ?array $streams = null;

    /**
     * @return string|null
     */
    public function getMasterUrl(): ?string
    {
        return $this->masterUrl;
    }

    /**
     * @return array|null
     */
    public function getStreams(): ?array
    {
        return $this->streams;
    }

    /**
     * @return string|null
     */
    public function getVideoIdByQuality(): ?string
    {
        foreach ($this->getStreams() as $stream) {
            if ($stream['quality'] === $_ENV['VIDEO_QUALITY']) {
                return $stream['id'];
            }
        }

        return null;
    }

    /**
     * @param string $masterUrl
     *
     * @return $this
     */
    public function setMasterUrl(string $masterUrl): VideoDTO
    {
        $this->masterUrl = $masterUrl;

        return $this;
    }

    /**
     * @param array $streams
     *
     * @return $this
     */
    public function setStreams(array $streams): VideoDTO
    {
        $this->streams = $streams;

        return $this;
    }
}
