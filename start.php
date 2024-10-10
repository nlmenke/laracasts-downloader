<?php
/**
 * App start point.
 */

use League\Flysystem\Adapter\Local as Adapter;
use League\Flysystem\Filesystem;

require_once 'bootstrap.php';

// dependencies
$client = new GuzzleHttp\Client(['base_url' => LARACASTS_BASE_URL]);
$filesystem = new Filesystem(new Adapter(BASE_FOLDER));
$bench = new Ubench();

// app
$app = new App\Downloader($client, $filesystem, $bench);

try {
    $app->start($options);
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
