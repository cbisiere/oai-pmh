<?php

/**
 * OAI protocol v2: OAI dates.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI dates.
 */
class Oai_Date
{
    /** @var string|null String representation of the date */
    private $_string;
    /** @var string|null Date format (see Oai_Const::FORMAT_*) */
    private $_format;
    /** @var string|null Date part (format 'Y-m-d') */
    private $_datepart;
    /** @var string|null Time part (format 'H:i:s') */
    private $_timepart;

    /**
     * Constructor.
     */
    private function __construct()
    {
    }

    /**
     * Construct from a date string.
     *
     * @param string $date date string in one of the supported formats
     */
    public static function createFromString($date)
    {
        $oDate = new self();
        $oDate->_setFromString($date);

        return $oDate;
    }

    /**
     * Construct from a timestamp.
     *
     * @param int    $timestamp Unix timestamp
     * @param string $format    date format requested
     */
    public static function createFromTimestamp($timestamp, $format)
    {
        $oDate = new self();
        $oDate->_setFromTimestamp($timestamp, $format);

        return $oDate;
    }

    /**
     * Construct a date for the current time, in a supported formats.
     *
     * @param string $format date format requested
     *
     * @return Oai_Date the new date object
     */
    public static function now($format)
    {
        $oDate = new self();
        $oDate->_setFromTimestamp(time(), $format);

        return $oDate;
    }

    /**
     * Get the format of the date.
     *
     * @return string format of the date
     */
    public function getFormat()
    {
        return $this->_format;
    }

    /**
     * Set the date from a unix timestamp.
     *
     * @param int    $timestamp unix timestamp
     * @param string $format    target string date format
     *
     * @throws Exception when the date format is unknown
     */
    private function _setFromTimestamp($timestamp, $format)
    {
        switch ($format) {
            case Oai_Const::FORMAT_DATE:
                $this->_string = gmdate('Y-m-d', $timestamp);
                break;
            case Oai_Const::FORMAT_DATETIME:
                $this->_string = gmdate('Y-m-d H:i:s', $timestamp);
                break;
            case Oai_Const::FORMAT_DATETIME_TZ:
                $this->_string = gmdate("Y-m-d\TH:i:s\Z", $timestamp);
                break;
            default:
                throw new Exception("Invalid date format: $format");
        }
        $this->_format = $format;
        $this->_datepart = gmdate('Y-m-d', $timestamp);
        $this->_timepart = gmdate('H:i:s', $timestamp);
    }

    /**
     * Set the date from a string, auto-detecting the format.
     *
     * @param string $str string containing the date
     *
     * @throws Exception
     */
    private function _setFromString($str)
    {
        $reg_date = "(?<date>(?<Y>[0-9]{4})-(?<m>\d\d)-(?<d>\d\d))";
        $reg_time = "(?<time>(?<H>\d\d):(?<i>\d\d):(?<s>\d\d))";

        if (!preg_match("/^$reg_date((?<T>[T ])$reg_time(?<Z>Z?))?$/", $str, $m)) {
            throw new Exception("Cannot parse date $str");
        }

        /* Date part */

        if (!checkdate($m['m'], $m['d'], $m['Y'])) {
            throw new Exception("Checkdate fails on date $str");
        }

        $this->_datepart = $m['date'];

        /* Time part */

        $has_time = (isset($m['time']));

        if ($has_time) {
            if (('T' == $m['T']) xor ('Z' == $m['Z'])) {
                throw new Exception("Incoherent TZ tags in date: $str");
            }

            if (($m['H'] >= 24) || ($m['i'] >= 60) || ($m['s'] >= 60)) {
                throw new Exception("Invalid time in date: $str");
            }

            $this->_timepart = $m['time'];
            $this->_format = ('T' == $m['T']
                ? Oai_Const::FORMAT_DATETIME_TZ
                : Oai_Const::FORMAT_DATETIME);
        } else {
            $this->_timepart = null;
            $this->_format = Oai_Const::FORMAT_DATE;
        }

        $this->_string = $str;
    }

    /**
     * Get the date in a specified format.
     *
     * @param string|false $format OAI supported date format, or false to use
     *                             the native format of the date
     *
     * @return string formated date
     *
     * @throws Exception
     */
    public function toString($format = false)
    {
        if ((false === $format) || ($format === $this->_format)) {
            return $this->_string;
        }

        $time = (isset($this->_timepart) ? $this->_timepart : '00:00:00');

        switch ($format) {
            case Oai_Const::FORMAT_DATE:
                $string = $this->_datepart;
                break;
            case Oai_Const::FORMAT_DATETIME:
                $string = $this->_datepart.' '.$time;
                break;
            case Oai_Const::FORMAT_DATETIME_TZ:
                $string = $this->_datepart.'T'.$time.'Z';
                break;
            default:
                throw new Exception("Invalid date format: $format");
        }

        return $string;
    }

    /**
     * Get the date as a Unix timestamp.
     *
     * @return int Unix timestamp
     */
    public function toUnixTime()
    {
        return strtotime($this->_string);
    }
}
