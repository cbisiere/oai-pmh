<?php

/**
 * Update the OAI repository.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */
define('START', 1);
require_once '../config/config.php';

define('REPO_ID', 'demo');

/* OAI request on the demo repository */
Oai_DemoUpdater::execRequest(
    OAI_DB_HOSTNAME,
    OAI_DB_USERNAME,
    OAI_DB_PASSWORD,
    OAI_DB_DATABASE,
    REPO_ID,
    false
);
