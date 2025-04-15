<?php

/**
 * Composer autoloader.
 */

declare(strict_types=1);

require 'vendor/autoload.php';

// options
$options = [];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$timezone = $_ENV['TIMEZONE'];

date_default_timezone_set($timezone);

// login
$options['password'] = $_ENV['PASSWORD'];
$options['email'] = $_ENV['EMAIL'];
// paths
$options['local_path'] = $_ENV['LOCAL_PATH'];
$options['lessons_folder'] = $_ENV['LESSONS_FOLDER'];
$options['series_folder'] = $_ENV['SERIES_FOLDER'];

define('BASE_FOLDER', $options['local_path']);
define('LESSONS_FOLDER', $options['lessons_folder']);
define('SERIES_FOLDER', $options['series_folder']);

// laracasts
const LARACASTS_BASE_URL = 'https://laracasts.com';
const LARACASTS_POST_LOGIN_PATH = 'sessions';
const LARACASTS_SERIES_PATH = 'series';
const LARACASTS_TOPICS_PATH = 'browse/all';

// vars
set_time_limit(0);
