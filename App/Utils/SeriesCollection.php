<?php
/**
 * Series Collection.
 */

namespace App\Utils;

/**
 * class SeriesCollection.
 *
 * @package App\Utils
 */
class SeriesCollection
{
    /**
     * @var array
     */
    private $series;

    /**
     * @param array $series
     *
     * @return void
     */
    public function __construct(array $series)
    {
        $this->series = $series;
    }

    /**
     * @param array $series
     *
     * @return void
     */
    public function add(array $series)
    {
        $this->series[$series['slug']] = $series;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->series);
    }

    /**
     * @return bool
     */
    public function exists(): bool
    {
        return !empty($this->series);
    }

    /**
     * @return mixed|null
     */
    public function first()
    {
        return $this->exists() ? $this->series[0] : null;
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return $this->series;
    }

    /**
     * @param string $key
     * @param bool   $actual
     *
     * @return int
     */
    public function sum(string $key, bool $actual): int
    {
        $sum = 0;

        foreach ($this->series as $series) {
            if ($actual) {
                $sum += count($series[str_replace('_count', '', $key) . 's']);
            } else {
                $sum += intval($series[$key]);
            }
        }

        return $sum;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return $this
     */
    public function where(string $key, string $value): SeriesCollection
    {
        $seriesList = [];

        foreach ($this->series as $series) {
            if ($series[$key] == $value) {
                $seriesList[] = $series;
            }
        }

        return new SeriesCollection($seriesList);
    }
}
