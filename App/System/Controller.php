<?php
/**
 * System Controller.
 */

namespace App\System;

use App\Utils\Utils;
use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;

/**
 * Class Controller.
 *
 * @package App\System
 */
class Controller
{
    /**
     * Flysystem lib.
     *
     * @var Filesystem
     */
    private $system;

    /**
     * @param Filesystem $system
     *
     * @return void
     */
    public function __construct(Filesystem $system)
    {
        $this->system = $system;
    }

    /**
     * Create folder if not exists.
     *
     * @param string $folder
     *
     * @return void
     */
    public function createFolderIfNotExists(string $folder)
    {
        if ($this->system->has($folder) === false) {
            $this->system->createDir($folder);
        }
    }

    /**
     * Create series folder if not exists.
     *
     * @param string $seriesSlug
     *
     * @return void
     */
    public function createSeriesFolderIfNotExists(string $seriesSlug)
    {
        $this->createFolderIfNotExists(SERIES_FOLDER . '/' . $seriesSlug);
    }

    /**
     * Get cached items.
     *
     * @return array
     */
    public function getCache(): array
    {
        $file = 'cache.php';

        return $this->system->has($file) ?
            require $this->system->getAdapter()->getPathPrefix() . $file :
            [];
    }

    /**
     * Get the series.
     *
     * @param bool $skip
     *
     * @throws FileNotFoundException
     *
     * @return array
     */
    public function getSeries(bool $skip = false): array
    {
        $list = $this->system->listContents(SERIES_FOLDER, true);
        $array = [];

        foreach ($list as $entry) {
            if ($entry['type'] != 'file') {
                continue;
            }

            // skip folder, we only want the files
            if (substr($entry['filename'], 0, 2) == '._') {
                continue;
            }

            $series = substr($entry['dirname'], strlen(SERIES_FOLDER) + 1);
            $episode = (int)substr($entry['filename'], 0, strpos($entry['filename'], '-'));

            $array[$series][] = $episode;
        }

        // TODO: #Issue# returns array with index 0
        if ($skip) {
            foreach ($this->getSkippedSeries() as $skipSeries => $episodes) {
                if (!isset($array[$skipSeries])) {
                    $array[$skipSeries] = $episodes;
                    continue;
                }

                $array[$skipSeries] = array_filter(
                    array_unique(
                        array_merge($array[$skipSeries], $episodes)
                    )
                );
            }
        }

        return $array;
    }

    /**
     * Create cache file.
     *
     * @param array $data
     *
     * @throws FileExistsException
     * @throws FileNotFoundException
     *
     * @return void
     */
    public function setCache(array $data)
    {
        $file = 'cache.php';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, '<?php return ' . var_export($data, true) . ';' . PHP_EOL);
    }

    /**
     * Run write commands.
     *
     * @throws FileExistsException
     * @throws FileNotFoundException
     *
     * @return void
     */
    public function writeSkipFiles(): void
    {
        Utils::box('Creating skip files');

        $this->writeSkipSeries();

        Utils::write('Skip files for series created');
    }

    /**
     * Read skip file.
     *
     * @param string $pathToSkipFile
     *
     * @throws FileNotFoundException
     *
     * @return array|mixed
     */
    private function getSkippedData(string $pathToSkipFile)
    {
        if ($this->system->has($pathToSkipFile)) {
            $content = $this->system->read($pathToSkipFile);

            return unserialize($content);
        }

        return [];
    }

    /**
     * Get skipped series.
     *
     * @throws FileNotFoundException
     *
     * @return array
     */
    private function getSkippedSeries(): array
    {
        return $this->getSkippedData(SERIES_FOLDER . '/.skip');
    }

    /**
     * Create skip file for lessons.
     *
     * @throws FileExistsException
     * @throws FileNotFoundException
     *
     * @return void
     */
    private function writeSkipSeries()
    {
        $file = SERIES_FOLDER . '/.skip';

        $series = serialize($this->getSeries(true));

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, $series);
    }
}
