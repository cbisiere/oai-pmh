<?php

/**
 * Exec OAI requests.
 *
 * PHP version 5.4+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */
define('START', 1);
require_once '../config/config.php';

define('REPO_ID', 'demo');

/* OAI request on repository 1 */
Oai::execRequest(
    OAI_DB_HOSTNAME,
    OAI_DB_USERNAME,
    OAI_DB_PASSWORD,
    OAI_DB_DATABASE,
    REPO_ID,
    'xsl/oai2.xsl'
);
