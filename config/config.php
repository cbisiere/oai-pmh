<?php

/**
 * Local settings: constants to connect to the OAI repo.
 *
 * PHP version 7.0+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/*
 * Check indirect call
 */

if (!defined('START')) {
    exit('Access denied.');
}

/*
 * DEBUG mode: display the exception message when returning a 503 error
 * (warning: may leak technical details)
 */

define('OAI_DEBUG', 1);

/*
 * OAI database settings
 */

define('OAI_DB_HOSTNAME', 'localhost');
define('OAI_DB_USERNAME', 'oai_user');
define('OAI_DB_PASSWORD', 'demo');
define('OAI_DB_DATABASE', 'oai_repo');

/*
 * Path to the php source folder, relative to this config file
 */

define('LIBRARY_PATH', realpath(dirname(__FILE__).'/../php'));

(LIBRARY_PATH !== false)
    or exit('php source directory does not exist: check the config file');

spl_autoload_register(function ($class_name) {
    require LIBRARY_PATH.'/'.$class_name.'.php';
});
