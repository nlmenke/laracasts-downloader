<?php

/**
 * App start point.
 */

declare(strict_types=1);

use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter as Adapter;

require_once 'bootstrap.php';

// dependencies
$client = new GuzzleHttp\Client(['base_uri' => LARACASTS_BASE_URL]);
$filesystem = new Filesystem(new Adapter(BASE_FOLDER));
$bench = new Ubench;

// app
$app = new App\Downloader($client, $filesystem, $bench);

try {
    $app->start($options);
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
} catch (FilesystemException|GuzzleException $e) {
}
