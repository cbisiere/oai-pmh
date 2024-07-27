<?php

/**
 * OAI protocol v2: OAI logger.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI logger.
 *
 * Use table: oai_access_log
 */
class Oai_Logger
{
    /** @var Oai_Connection Connection to the database */
    private $_connection;
    /** @var string Id of the repository (oai_repo.id) */
    private $_repo;
    /** @var int Unique identifier for the current access log record (oai_access_log.id) */
    private $_log;

    /**
     * Constructor.
     *
     * @param Oai_Connection $connection Database connection to the repo
     * @param string         $repo       Repo id
     */
    public function __construct(
        Oai_Connection $connection,
        $repo
    ) {
        $this->_connection = $connection;
        $this->_repo = $repo;
    }

    /**
     * Submit a SQL query.
     *
     * @return PDOStatement result resource
     */
    private function _query()
    {
        return $this->_connection->execQuery(func_get_args());
    }

    /**
     * Begin to log a new request: create a log record, and return its
     * record id.
     *
     * @return int
     *
     * @throws Exception when the record cannot be created
     */
    private function _beginRequestTask()
    {
        $var[] = 'repo';
        $fmt[] = '?';
        $arg[] = $this->_repo;

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $var[] = 'address';
            $fmt[] = '?';
            $arg[] = $_SERVER['REMOTE_ADDR'];
        }

        /* For this to work, "Hostname Lookups" must be On */
        if (isset($_SERVER['REMOTE_HOST'])) {
            $var[] = 'host';
            $fmt[] = '?';
            $arg[] = $_SERVER['REMOTE_HOST'];
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            $var[] = 'referer';
            $fmt[] = '?';
            $arg[] = $_SERVER['HTTP_REFERER'];
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $var[] = 'agent';
            $fmt[] = '?';
            $arg[] = $_SERVER['HTTP_USER_AGENT'];
        }

        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
            $https = (isset($_SERVER['HTTPS'])
                && (!empty($_SERVER['HTTPS']))
                && ('off' !== $_SERVER['HTTPS']));

            $uri = ($https ? 'https://' : 'http://')
            .$_SERVER['HTTP_HOST']
            .$_SERVER['REQUEST_URI'];

            $var[] = 'uri';
            $fmt[] = '?';
            $arg[] = $uri;
        }

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $var[] = 'method';
            $fmt[] = '?';
            $arg[] = $_SERVER['REQUEST_METHOD'];
        }

        $var[] = 'date_start';
        $fmt[] = '?';
        $arg[] = $this->_connection->getDate();

        $vars = implode(', ', $var);
        $fmts = implode(', ', $fmt);

        $query = "INSERT oai_access_log ($vars) VALUES ($fmts)";
        $this->_query($query, $arg);

        $id = $this->_connection->lastInsertId('id');
        if (0 === $id) {
            throw new Exception('Cannot get inserted id from oai_access_log');
        }

        return $id;
    }

    /**
     * End a request task: set the date end and message parts of the current
     * request record.
     *
     * @param int    $error  1 if an error occurred, 0 otherwise
     * @param int    $errno  error number, if any
     * @param string $errmsg error message, if any
     */
    private function _endRequestTask($error, $errno = null, $errmsg = null)
    {
        $let[] = 'date_end = NOW()';

        $let[] = 'duration = ?';
        $arg[] = $this->_connection->getDuration();

        $let[] = 'error = ?';
        $arg[] = $error;

        if (isset($errno)) {
            $let[] = 'errno = ?';
            $arg[] = $errno;
        }

        if (isset($errmsg)) {
            $let[] = 'errmsg = ?';
            $arg[] = $errmsg;
        }

        $set = implode(', ', $let);

        $query = "UPDATE oai_access_log SET $set WHERE id = ?";
        $arg[] = $this->_log;

        $this->_query($query, $arg);
    }

    /**
     * Store OAI request parameters.
     *
     * @param string    $verb       OAI verb
     * @param string    $prefix     OAI metadata prefix
     * @param string    $set        OAI set spec
     * @param string    $identifier OAI identifier
     * @param Oai_Date  $oFrom      OAI from date
     * @param Oai_Date  $oUntil     OAI until date
     * @param Oai_Token $oToken     OAI token
     */
    public function storeRequestParam(
        $verb,
        $prefix,
        $set,
        $identifier,
        ?Oai_Date $oFrom = null,
        ?Oai_Date $oUntil = null,
        ?Oai_Token $oToken = null
    ) {
        $let = [];

        if (isset($verb)) {
            $let[] = 'request_verb = ?';
            $arg[] = $verb;
        }

        if (isset($verb)) {
            $let[] = 'request_prefix = ?';
            $arg[] = $prefix;
        }

        if (isset($set)) {
            $let[] = 'request_set = ?';
            $arg[] = $set;
        }

        if (isset($identifier)) {
            $let[] = 'request_identifier = ?';
            $arg[] = $identifier;
        }

        if (isset($oFrom)) {
            $let[] = 'request_from = ?';
            $arg[] = $oFrom->toString(Oai_Const::FORMAT_DATETIME);
        }

        if (isset($oUntil)) {
            $let[] = 'request_until = ?';
            $arg[] = $oUntil->toString(Oai_Const::FORMAT_DATETIME);
        }

        if (isset($oToken)) {
            $let[] = 'request_token = ?';
            $arg[] = $oToken->toString();
        }

        if (count($let) > 0) {
            $set = implode(', ', $let);

            $query = "UPDATE oai_access_log SET $set WHERE id = ?";
            $arg[] = $this->_log;

            $this->_query($query, $arg);
        }
    }

    /**
     * Store the token of a OAI response.
     *
     * @param string $token  Token of the OAI response
     * @param int    $cursor Cursor of the OAI response
     */
    public function storeRequestToken($token, $cursor)
    {
        $let = [];

        if (isset($token)) {
            $let[] = 'response_token = ?';
            $arg[] = $token;
        }

        if (isset($cursor)) {
            $let[] = 'response_cursor = ?';
            $arg[] = $cursor;
        }

        if (count($let) > 0) {
            $set = implode(', ', $let);

            $query = "UPDATE oai_access_log SET $set WHERE id = ?";
            $arg[] = $this->_log;

            $this->_query($query, $arg);
        }
    }

    /**
     * Store the OAI error.
     *
     * @param string $code    OAI error code
     * @param string $message error message
     */
    public function storeRequestError($code, $message)
    {
        $let = [];

        if (isset($code)) {
            $let[] = 'response_error_code = ?';
            $arg[] = $code;
        }

        if (isset($message)) {
            $let[] = 'response_error_message = ?';
            $arg[] = $message;
        }

        if (count($let) > 0) {
            $set = implode(', ', $let);

            $query = "UPDATE oai_access_log SET $set WHERE id = ?";
            $arg[] = $this->_log;

            $this->_query($query, $arg);
        }
    }

    /**
     * Store the OAI response.
     *
     * @param Oai_Date $oDate    OAI response date
     * @param string   $response OAI response
     */
    public function storeRequestResponse(Oai_Date $oDate, $response)
    {
        $let = [];

        if (isset($oDate)) {
            $date = $oDate->toString(Oai_Const::FORMAT_DATETIME);
            $let[] = 'response_date = ?';
            $arg[] = $date;
        }

        if (isset($response)) {
            $let[] = 'response = ?';
            $arg[] = ''; // $response;
        }

        if (count($let) > 0) {
            $set = implode(', ', $let);

            $query = "UPDATE oai_access_log SET $set WHERE id = ?";
            $arg[] = $this->_log;

            $this->_query($query, $arg);
        }
    }

    /*
     * Start / end a request
     */

    /**
     * Initiate an OAI request.
     *
     * Before starting to serve the request, we create a new log record.
     */
    public function beginRequest()
    {
        /* create an update record and store its id for later use in endUpdate() */
        $this->_log = $this->_beginRequestTask();
    }

    /**
     * Terminate an OAI request.
     *
     * @param Exception $e exception (if any) that terminate the request
     */
    public function endRequest($e = null)
    {
        if (isset($e)) {
            $this->_endRequestTask(1, $e->getCode(), $e->getMessage());
        } else {
            $this->_endRequestTask(0);
        }
    }
}
