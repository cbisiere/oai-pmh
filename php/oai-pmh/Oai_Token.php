<?php

/**
 * OAI protocol v2: OAI resumption tokens.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI resumption tokens.
 *
 * Class to handle OAI resumption tokens.
 *
 * The format of the resumptionToken is not defined by the OAI-PMH. We implement
 * a format that encodes all the necessary information about the previous
 * incomplete list.
 */
class Oai_Token
{
    /** @var float String representation of the token */
    private $_string;
    /** @var bool Is this string representation up to date? */
    private $_dirty = true;

    /*
     * Values encoded into the token:
     *
     */
    /** @var Oai_Date Date stamp of the token */
    private $_oDatestamp;
    /** @var string Index in the token */
    private $_index;
    /** @var string|null Metadata prefix */
    private $_metadataPrefix;
    /** @var Oai_Date|null Date from */
    private $_oFrom;
    /** @var Oai_Date|null Date until */
    private $_oUntil;
    /** @var string|null Set spec */
    private $_setSpec;

    /**
     * Constuctor.
     *
     * @param string|false $strToken value of the token, or false
     *                               if no initial value has to be set
     */
    public function __construct($strToken = false)
    {
        if (false !== $strToken) {
            $this->set($strToken);
        }
    }

    /**
     * Return the token as a string.
     *
     * @return string Value of the token
     */
    public function toString()
    {
        if ($this->_dirty) {
            $this->_string = self::_implode(
                $this->_oDatestamp,
                $this->_index,
                $this->_metadataPrefix,
                $this->_oFrom,
                $this->_oUntil,
                $this->_setSpec
            );
            $this->_dirty = false;
        }

        return $this->_string;
    }

    /**
     * Set the token from a string passed as URL parameter.
     *
     * @param string $strToken The token
     */
    public function set($strToken)
    {
        list($oDatestamp, $index, $metadataPrefix, $oFrom, $oUntil, $setSpec)
            = self::_explode($strToken);

        $this->_oDatestamp = $oDatestamp;
        $this->_index = $index;
        $this->_metadataPrefix = $metadataPrefix;
        $this->_oFrom = $oFrom;
        $this->_oUntil = $oUntil;
        $this->_setSpec = $setSpec;

        $this->_string = $strToken;
        $this->_dirty = false;
    }

    /**
     * Set the datestamp part of the token.
     *
     * @param Oai_Date $oDatestamp The datestamp that is to be set as the
     *                             datestamp part of the token
     */
    public function setDatestamp(Oai_Date $oDatestamp)
    {
        $this->_oDatestamp = $oDatestamp;
        $this->_dirty = true;
    }

    /**
     * Set the index part of the token.
     *
     * @param string $index The index that is to be set as the index part
     *                      of the token
     */
    public function setIndex($index)
    {
        $this->_index = $index;
        $this->_dirty = true;
    }

    /**
     * Set the metadata prefix part of the token.
     *
     * @param string $metadataPrefix The metadata prefix that is to be set as the
     *                               as the metadata prefix part of the token
     */
    public function setMetadataPrefix($metadataPrefix)
    {
        $this->_metadataPrefix = $metadataPrefix;
        $this->_dirty = true;
    }

    /**
     * Set the datefrom part of the token.
     *
     * @param Oai_Date $oFrom The date that is to be set as the datefrom part
     *                        of the token
     */
    public function setFrom(?Oai_Date $oFrom = null)
    {
        $this->_oFrom = $oFrom;
        $this->_dirty = true;
    }

    /**
     * Set the dateuntil part of the token.
     *
     * @param Oai_Date $oUntil The date that is to be set as the dateuntil part
     *                         of the token
     */
    public function setUntil(?Oai_Date $oUntil = null)
    {
        $this->_oUntil = $oUntil;
        $this->_dirty = true;
    }

    /**
     * Set the setspec part of the token.
     *
     * @param string $setSpec The setspec that is to be set as the setspec part
     *                        of the token
     */
    public function setSetSpec($setSpec)
    {
        $this->_setSpec = $setSpec;
        $this->_dirty = true;
    }

    /*
     * Get
     */

    /**
     * Get the datestamp part of the token.
     *
     * @return Oai_Date The datestamp part of the token
     */
    public function getDatestamp()
    {
        return $this->_oDatestamp;
    }

    /**
     * Get the index part of the token.
     *
     * @return string The index part of the token
     */
    public function getIndex()
    {
        return $this->_index;
    }

    /**
     * Get the metadata prefix part of the token.
     *
     * @return string The metadata prefix part of the token
     */
    public function getMetadataPrefix()
    {
        return $this->_metadataPrefix;
    }

    /**
     * Get the setspec part of the token.
     *
     * @return string The setspec part of the token
     */
    public function getSetSpec()
    {
        return $this->_setSpec;
    }

    /**
     * Get the datefrom part of the token.
     *
     * @return Oai_Date The datefrom part of the token
     */
    public function getFrom()
    {
        return $this->_oFrom;
    }

    /**
     * Get the dateuntil part of the token.
     *
     * @return Oai_Date The dateuntil part of the token
     */
    public function getUntil()
    {
        return $this->_oUntil;
    }

    /*
     * Static helpers
     */

    /**
     * Encode special characters in a string for use in a token.
     *
     * Section 3.5 reads: "Before including a resumptionToken in the URL of a
     * subsequent request, a harvester must encode any special characters in it."
     * Since tokens include time values, ":" must be encoded (3.1.1.3).
     *
     * @param string $v The string to encode
     *
     * @return string The encoded string
     */
    private static function _encodeValue($v)
    {
        return false === $v ? '' : strtr($v, ':', '.');
    }

    /**
     * Decode special characters in a string.
     *
     * This method is just the reverse of _encodeValue().
     *
     * @param string $v The string to decode
     *
     * @return string The decoded string
     */
    private static function _decodeValue($v)
    {
        return '' === $v ? false : strtr($v, '.', ':');
    }

    /**
     * Decode a string containing a token.
     *
     * @param string $strToken The token as a string
     *
     * @return mixed[] Elements of the decoded token
     *
     * @throws Exception
     */
    private static function _explode($strToken)
    {
        $arg = explode('|', $strToken);
        $count = count($arg);

        if (6 != $count) {
            throw new Exception('Error decoding token: wrong number of arguments');
        }

        $arg = array_map('Oai_Token::_decodeValue', $arg);

        list($datestamp, $index, $metadataPrefix, $from, $until, $setSpec) = $arg;

        /* mandatory in any token */
        if (false === $datestamp) {
            throw new Exception('Error decoding token: missing datestamp');
        }

        /* mandatory in any token */
        if (false === $index) {
            throw new Exception('Error decoding token: missing index');
        }

        try {
            $oDatestamp = Oai_Date::createFromString($datestamp);
            $oFrom = (false !== $from ? Oai_Date::createFromString($from) : null);
            $oUntil = (false !== $until ? Oai_Date::createFromString($until) : null);
        } catch (Exception $e) {
            throw new Exception('Error decoding token: invalid date');
        }

        if ((isset($oFrom))
            && (isset($oUntil))
            && ($oFrom->getFormat() != $oUntil->getFormat())
        ) {
            throw new Exception('Error decoding token: dates with different format');
        }

        if (!ctype_digit($index)) {
            throw new Exception('Error decoding token: index is not an integer');
        }

        return [
            $oDatestamp,
            $index,
            $metadataPrefix,
            $oFrom,
            $oUntil,
            $setSpec,
        ];
    }

    /**
     * Encode a token in a string.
     *
     * To encode the different parts, we escape each part, and then concatenate
     * everything using | as a separator.
     *
     * @param Oai_Date $oDatestamp     Datestamp part
     * @param string   $index          Index part
     * @param string   $metadataPrefix Metadataprefix part
     * @param Oai_Date $oFrom          Datefrom part
     * @param Oai_Date $oUntil         Dateuntil part
     * @param string   $setSpec        Setspec part
     *
     * @return string The encoded token
     */
    private static function _implode(
        Oai_Date $oDatestamp,
        $index,
        $metadataPrefix,
        ?Oai_Date $oFrom = null,		/* Needed to allow passing null */
        ?Oai_Date $oUntil = null,	/* Needed to allow passing null */
        $setSpec = ''				/* Useless */
    ) {
        $datestamp = (isset($oDatestamp) ? $oDatestamp->toString() : false);
        $from = (isset($oFrom) ? $oFrom->toString() : false);
        $until = (isset($oUntil) ? $oUntil->toString() : false);

        $arg = [$datestamp, $index, $metadataPrefix, $from, $until, $setSpec];
        $arg = array_map('Oai_Token::_encodeValue', $arg);

        $strToken = implode('|', $arg);

        return $strToken;
    }
}
