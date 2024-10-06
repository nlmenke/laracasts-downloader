<?php
/**
 * Vimeo Repository.
 */

namespace App\Vimeo;

use App\Vimeo\DTO\MasterDTO;
use App\Vimeo\DTO\VideoDTO;
use GuzzleHttp\Client;

/**
 * Class VimeoRepository.
 *
 * @package App\Vimeo
 */
class VimeoRepository
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     *
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param int $vimeoId
     *
     * @return VideoDTO
     */
    public function get(int $vimeoId): VideoDTO
    {
        $content = $this->client
            ->get("https://player.vimeo.com/video/$vimeoId", [
                'headers' => [
                    'Referer' => 'https://laracasts.com/',
                ],
            ])
            ->getBody()
            ->getContents();

        preg_match('/"streams":(\[{.+?}])/', $content, $streams);

        preg_match('/"(?:google_skyfire|akfire_interconnect_quic)":({.+?})/', $content, $cdns);

        return (new VideoDTO())
            ->setMasterUrl(json_decode($cdns[1], true)['url'])
            ->setStreams(json_decode($streams[1], true));
    }

    /**
     * @param VideoDTO $video
     *
     * @return MasterDTO
     */
    public function getMaster(VideoDTO $video): MasterDTO
    {
        $content = $this->client->get($video->getMasterUrl())
            ->getBody()
            ->getContents();

        $data = json_decode($content, true);

        return (new MasterDTO())
            ->setMasterUrl($video->getMasterUrl())
            ->setBaseUrl($data['base_url'])
            ->setClipId($data['clip_id'])
            ->setAudios($data['audio'])
            ->setVideos($data['video']);
    }
}
