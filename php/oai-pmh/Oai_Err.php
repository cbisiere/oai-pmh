<?php

/**
 * OAI protocol v2: error codes.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI Error codes.
 *
 * This class handles constants and default messages for OAI 2.0 Error codes.
 */
class Oai_Err
{
    /**
     * Is this code a known OAI error code?
     *
     * See class' private constants.
     *
     * @param string $code error code
     *
     * @return bool true if the error code is a valid error code
     */
    public static function isKnown($code)
    {
        return in_array(
            $code,
            [
                Oai_Const::ERROR_BAD_ARGUMENT,
                Oai_Const::ERROR_BAD_TOKEN,
                Oai_Const::ERROR_BAD_VERB,
                Oai_Const::ERROR_CANNOT_DISSEMINATE,
                Oai_Const::ERROR_NO_METADATA,
                Oai_Const::ERROR_NO_RECORDS,
                Oai_Const::ERROR_NO_SETS,
                Oai_Const::ERROR_UNKNOWN_ID,
            ]
        );
    }

    /**
     * Default message for an OAI error code, or false if no message is defined
     * for this code.
     *
     * @param string $code error code
     *
     * @return string|bool error string or false is no error message is defined for
     *                     the code
     */
    public static function getDefaultMessage($code)
    {
        $message = [
            Oai_Const::ERROR_BAD_ARGUMENT => 'The request includes illegal arguments,'
                    .' is missing required arguments, includes'
                    .' a repeated argument, or values for arguments'
                    .' have an illegal syntax.',
            Oai_Const::ERROR_BAD_TOKEN => 'The value of the resumptionToken argument is invalid or expired',
            Oai_Const::ERROR_BAD_VERB => 'Illegal OAI verb',
            Oai_Const::ERROR_CANNOT_DISSEMINATE => 'The value of the metadataPrefix argument is not'
                    .' supported by the item identified'
                    .' by the value of the identifier argument',
            Oai_Const::ERROR_UNKNOWN_ID => 'The value of the identifier argument is unknown'
                    .' or illegal in this repository',
            Oai_Const::ERROR_NO_RECORDS => 'The combination of the values of the from, until,'
                    .' and set arguments results in an empty list',
            Oai_Const::ERROR_NO_METADATA => 'There are no metadata formats available for the specified item',
            Oai_Const::ERROR_NO_SETS => 'This repository does not support sets',
        ];

        return isset($message[$code]) ? $message[$code] : false;
    }
}
