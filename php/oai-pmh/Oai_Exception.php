<?php

/**
 * OAI protocol v2: OAI Exception.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI Exception.
 *
 * Class to handle OAI exceptions.
 */
class Oai_Exception extends Exception
{
    /*
     * OAI error code, as defined by the protocol (e.g. 'badVerb').
     *
     * We need a new member to store this code, because code member
     * in Exception is a long, not a string.
     */
    /** @var string Error code */
    private $_oaiCode;

    /**
     * Constructor.
     *
     * The constructor allows to specify a customized error message. When no
     * message is specified, the message is set to the default message for the
     * OAI error code, if any.
     *
     * @param string $oaiCode OAI error code
     * @param string $message optional error message
     *
     * @throws Exception
     */
    public function __construct($oaiCode, $message = null)
    {
        if (!Oai_Err::isKnown($oaiCode)) {
            throw new Exception("Undefined OAI error code: $oaiCode");
        }

        /* When no message is given, try to get the default message */
        if (!isset($message)) {
            $message = Oai_Err::getDefaultMessage($oaiCode);

            if (false === $message) {
                $message = '';
            }
        }

        parent::__construct($message);

        $this->_oaiCode = $oaiCode;
    }

    /**
     * Get the OAI error code attached to the exception.
     *
     * @return string OAI error code
     */
    public function getOaiCode()
    {
        return $this->_oaiCode;
    }
}
