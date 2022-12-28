<?php

/**
 * OAI protocol v2: various helpers.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI Utils.
 *
 * This class provides various useful functions.
 */
class Oai_Utils
{
    /**
     * Return an array of encoding methods supported by the http server,
     * excluding 'identity'.
     *
     * array('gzip','deflate','sdch')
     *
     * @return string[] Encoding methods supported by the http server
     */
    public static function supported_encoding()
    {
        if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            return [];
        }

        return array_diff(array_map('trim', explode(',', $_SERVER['HTTP_ACCEPT_ENCODING'])), ['identity']);
    }

    /**
     * Recursively flatten an array.
     *
     * array(1, array(array(2,3), 4), 5) -> array(1, 2, 3, 4, 5)
     *
     * @param mixed[] $e element to flatten
     *
     * @return mixed[] flattened array
     */
    public static function flatten($e)
    {
        if (!is_array($e)) {
            return [$e];
        }

        $a = [];
        foreach ($e as $v) {
            $a = array_merge($a, self::flatten($v));
        }

        return $a;
    }
}
