<?php

/**
 * Local settings: constants to connect to the OAI repo.
 *
 * PHP version 7.1+
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

define('OAI_DEBUG', 0);

/*
 * OAI database settings
 */

define('OAI_DB_HOSTNAME', 'localhost');
define('OAI_DB_USERNAME', 'oai_user');
define('OAI_DB_PASSWORD', 'demo');
define('OAI_DB_DATABASE', 'oai_repo');

/* path to the php source folders, relative to this config file */
define('PHP_ROOT_PATH', realpath(dirname(__FILE__).'/../php'));
/* subdirectories to look into for source files */
define('PHP_DIRS', ['oai-pmh', 'oai-updater', 'demo-updater']);

/*
 * Class autoload
 */

(PHP_ROOT_PATH !== false)
    or exit('OAI-PMH config error: source directory does not exist');

/* try to locate and load a class */
function load_class($class_name)
{
    $found = false;
    foreach (PHP_DIRS as $dir) {
        $path = PHP_ROOT_PATH.'/'.$dir.'/'.$class_name.'.php';
        if (is_readable($path)) {
            require $path;
            $found = true;
            break;
        }
    }
    $found or exit('OAI-PMH config error: source file not found'.$class_name);
}
spl_autoload_register('load_class');
