<?php

/**
 * System Controller.
 */

namespace App\System;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;

/**
 * Class Controller.
 */
class Controller
{
    /**
     * @param Filesystem $system
     *
     * @return void
     */
    public function __construct(private readonly Filesystem $system) {}

    /**
     * Create folder if not exists.
     *
     * @param string $folder
     *
     * @throws FilesystemException
     *
     * @return void
     */
    public function createFolderIfNotExists(string $folder): void
    {
        if ($this->system->has($folder) === false) {
            $this->system->createDirectory($folder);
        }
    }

    /**
     * Create series folder if not exists.
     *
     * @param string $seriesSlug
     *
     * @throws FilesystemException
     *
     * @return void
     */
    public function createSeriesFolderIfNotExists(string $seriesSlug): void
    {
        $this->createFolderIfNotExists(SERIES_FOLDER . '/' . $seriesSlug);
    }

    /**
     * Get cached items.
     *
     * @throws FilesystemException
     *
     * @return array
     */
    public function getCache(): array
    {
        $file = 'cache.json';

        return $this->system->fileExists($file) ?
            json_decode($this->system->read($file), true) :
            [];
    }

    /**
     * Get the series.
     *
     * @throws FilesystemException
     *
     * @return array
     */
    public function getSeries(): array
    {
        // we want only files, and we only need their paths
        $paths = $this->system->listContents(SERIES_FOLDER, true)
            ->filter(fn (StorageAttributes $attributes): bool => $attributes->isFile())
            ->sortByPath()
            ->map(fn (StorageAttributes $attributes): string => $attributes->path())
            ->toArray();

        $array = [];
        foreach ($paths as $path) {
            $segments = explode('/', substr((string)$path, strlen(SERIES_FOLDER) + 1));

            // this happens on MAC when "series/.DS_Store" is present
            if (! isset($segments[1])) {
                continue;
            }

            [$series, $episode] = $segments;

            $episodeNumber = (int)substr($episode, 0, strpos($episode, '-'));

            $array[$series][] = $episodeNumber;
        }

        return $array;
    }

    /**
     * Create cache file.
     *
     * @param array $data
     *
     * @throws FilesystemException
     *
     * @return void
     */
    public function setCache(array $data): void
    {
        $file = 'cache.json';

        if ($this->system->has($file)) {
            $this->system->delete($file);
        }

        $this->system->write($file, json_encode($data));
    }
}
