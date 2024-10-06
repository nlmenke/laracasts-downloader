<?php
/**
 * Composer autoloader.
 */

require 'vendor/autoload.php';

// options
$options = [];

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$timezone = getenv('TIMEZONE');

date_default_timezone_set($timezone);

// login
$options['password'] = getenv('PASSWORD');
$options['email'] = getenv('EMAIL');
// paths
$options['local_path'] = getenv('LOCAL_PATH');
$options['lessons_folder'] = getenv('LESSONS_FOLDER');
$options['series_folder'] = getenv('SERIES_FOLDER');

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
