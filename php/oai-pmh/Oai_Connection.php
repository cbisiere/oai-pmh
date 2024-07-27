<?php

/**
 * OAI protocol v2: OAI backend.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI backend.
 *
 * OAI connection to the backend database.
 */
class Oai_Connection extends PDO
{
    /** @var float Start time of the connection in seconds */
    private $_mtime;
    /** @var string Start time of the connection, in backend database format */
    private $_now;

    /**
     * Constructor.
     *
     * @param string $hostname host name to connect to
     * @param string $username user name
     * @param string $password password
     * @param string $database database to select
     */
    public function __construct(
        $hostname,
        $username,
        $password,
        $database
    ) {
        /*
         * From:
         * http://www.openarchives.org/OAI/openarchivesprotocol.html#XMLResponse
         *
         *     Encoding of the XML must use the UTF-8 representation of Unicode.
         *
         */

        $dsn = "mysql:host={$hostname};dbname={$database};charset=utf8";
        parent::__construct($dsn, $username, $password);

        /*
         * From:
         * http://www.openarchives.org/OAI/openarchivesprotocol.html#Dates
         *
         *     Dates and times are uniformly encoded using ISO8601 and are
         *     expressed in UTC throughout the protocol.
         */

        $this->execQuery("SET time_zone = '+0:00'");
        date_default_timezone_set('UTC');

        $this->_mtime = microtime(true);
        $this->_now = gmdate('Y-m-d H:i:s', intval($this->_mtime));
    }

    /**
     * Return the date at which the connection object was created.
     *
     * Note: the result must be parsable by Oai_date
     *
     * @return int
     */
    public function getDate()
    {
        return $this->_now;
    }

    /**
     * Return the duration in seconds between now and the connection date.
     *
     * @return float
     */
    public function getDuration()
    {
        return microtime(true) - $this->_mtime;
    }

    /**
     * Return a SQL "in" clause.
     *
     * @param string  $field  name of the field
     * @param mixed[] $values values
     *
     * @return string the clause
     */
    public static function inClause($field, $values)
    {
        $n = count($values);

        if ($n > 1) {
            $format = '('
                .implode(',', array_fill(1, $n, '?'))
                .')';
            $clause = "$field IN $format";
        } else {
            $clause = "$field = ?";
        }

        return $clause;
    }

    /**
     * Submit a SQL query.
     *
     * This method accepts a variable number of parameters. If a parameter is an
     * array, each element is considered as a separate parameter.
     *
     * Example: ($query, $par1, array($par2, $par3), $par4)) is equivalent to
     * ($query, $par1, $par2, $par3, $par4))
     *
     * @return PDOStatement result resource
     *
     * @throws Exception when the query cannot be parsed or executed
     */
    public function execQuery()
    {
        $args = Oai_Utils::flatten(func_get_args());
        $sql = array_shift($args);

        if (0 == count($args)) {
            $statement = parent::query($sql);
        } else {
            $statement = parent::prepare($sql);
            if (false !== $statement) {
                $statement->execute($args);
            }
        }

        if (false === $statement) {
            throw new Exception('SQL error: '.$this->error().': '.$sql, $this->errno());
        }

        return $statement;
    }
}
