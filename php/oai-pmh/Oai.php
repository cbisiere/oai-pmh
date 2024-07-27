<?php

/**
 * OAI protocol v2.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 *
 * @see      http://www.openarchives.org/OAI/openarchivesprotocol.html OAI Protocol Version 2.0
 */

/**
 * OAI protocol v2: main class.
 *
 * Typical usage:
 *
 * function execOaiRequest($repo)
 * {
 * 	Oai::execRequest(
 * 		OAI_DB_HOSTNAME,
 * 		OAI_DB_USERNAME,
 * 		OAI_DB_PASSWORD,
 * 		OAI_DB_DATABASE,
 * 		$repo,
 * 		"xsl/oai2.xsl"
 * 	);
 * }
 *
 *
 * Version history (major changes and important bug fixes only):
 *
 * 2009-07-12: initial version
 * 2009-07-26: add token support
 * 2009-07-28: abstract backend
 * 2010-03-18: add XSL stylesheet support
 * 2011-01-20: add deleted support level 'no'
 * 2012-05-08: log requests
 * 2012-11-05: support repo description
 * 2015-06-18: get the base url from the database
 * 2018-04-22: get the token duration from the database
 */
class Oai
{
    /** @var Oai_Connection Connection to the database */
    private $_connection;
    /** @var Oai_Backend Data backend */
    private $_backend;
    /** @var Oai_Logger Access logger */
    private $_logger;

    /**
     * Arguments passed through the URL.
     */
    /** @var string[] Arguments */
    private $_args = [];
    /** @var Oai_Token|null Continuation token */
    private $_arg_token;
    /** @var Oai_Date|null From date */
    private $_arg_from;
    /** @var Oai_Date|null Until date */
    private $_arg_until;

    /**
     * Repository info.
     */
    /** @var string Repository id (oai_repo.id) */
    private $_repo;
    /** @var string Base URL of the repository */
    private $_repo_baseurl;
    /** @var string Supported date format */
    private $_repo_date_format;
    /** @var string Level of support for deletions */
    private $_repo_deletion_support;
    /** @var int|null Maximum size of an incomplete list */
    private $_repo_list_size;
    /** @var int|null Duration of the token, in sec. */
    private $_repo_token_duration;

    /** @var string|null XSL stylesheet to use for the OAI response */
    private $_stylesheet;
    /** @var DOMDocument|null OAI response */
    private $_response;
    /** @var DOMElement|null OAI response date XML element */
    private $_response_date;
    /** @var DOMElement|null OAI response root XML element */
    private $_root;

    /** @var bool Ignore deleted records */
    private $_no_deleted = false;

    /**
     * Constructor.
     *
     * @param string $hostname   host name to connect to
     * @param string $username   user name
     * @param string $password   password
     * @param string $database   database to select
     * @param string $repo       repo id
     * @param string $stylesheet a XSL style sheet attached to the OAI response
     *
     * @throws Exception when the size is not a positive integer, when the date
     *                   format is not supported, or the deletion support is
     *                   unknown
     */
    private function __construct(
        $hostname,
        $username,
        $password,
        $database,
        $repo,
        $stylesheet = null
    ) {
        $this->_repo = $repo;

        $this->_connection = new Oai_Connection(
            $hostname,
            $username,
            $password,
            $database
        );

        $this->_backend = new Oai_Backend(
            $this->_connection,
            $this->_repo
        );

        $this->_logger = new Oai_Logger(
            $this->_connection,
            $this->_repo
        );

        $this->_stylesheet = $stylesheet;

        /*
         * Get repository info from the database
         */

        $f = $this->_getRepoData();

        /*
         * max list size
         */

        $limit = $f['maxListSize'];
        if (isset($limit)) {
            if ((!ctype_digit((string) $limit)) || ($limit <= 0)) {
                throw new Exception('Corrupted data: maximum size of incomplete lists must be a positive integer');
            }
            $this->_repo_list_size = $limit;
        }

        /*
         * granularity
         */

        $format = $f['granularity'];
        if ((Oai_Const::FORMAT_DATE !== $format)
            && (Oai_Const::FORMAT_DATETIME_TZ !== $format)
        ) {
            throw new Exception('Corrupted data: date format not supported by the protocol');
        }
        $this->_repo_date_format = $format;

        /*
         * support for deletions
         */

        $deletion = $f['deletedRecord'];

        if ((Oai_Const::DEL_SUPPORT_NO !== $deletion)
            && (Oai_Const::DEL_SUPPORT_TRANSIENT !== $deletion)
            && (Oai_Const::DEL_SUPPORT_PERSISTENT !== $deletion)
        ) {
            throw new Exception('Corrupted data: deletion support mode is unknown');
        }
        $this->_repo_deletion_support = $deletion;
        $this->_no_deleted = (Oai_Const::DEL_SUPPORT_NO == $deletion);

        /*
         * base URL
         */

        $this->_repo_baseurl = $f['baseURL'];

        /*
         * max lifespan of a token, in seconds (or null if tokens never expire)
         */

        $this->_repo_token_duration = $f['tokenDuration'];
    }

    /**
     * Return an array containing repository information.
     *
     * @return string[] Repository information
     *
     * @throws Exception when the repository table does not contain exactly one
     *                   record
     */
    private function _getRepoData()
    {
        $result = $this->_backend->repoSelect();
        $f = $result->fetch();

        if (false === $f) {
            throw new Exception('Corrupted data:'." could not find identity data for repository with id {$this->_repo}");
        }

        if (false != $result->fetch()) {
            throw new Exception('Corrupted data:'." multiple identity data found for repository with id {$this->_repo}");
        }

        return $f;
    }

    /**
     * Return the value of an argument of the OAI request, or false if it is not
     * present.
     *
     * @param string $k the name of the argument
     *
     * @return string|false the value of the argument, of false if the argument
     *                      is not specified in the query
     */
    private function _getArg($k)
    {
        return isset($this->_args[$k]) ? $this->_args[$k] : false;
    }

    /**
     * Return the arguments of the OAI request.
     *
     * @return string[] Arguments of the OAI request (arg_name => arg_value)
     */
    private function _getArgSet()
    {
        return $this->_args;
    }

    /**
     * Set the value of an argument of the OAI request.
     *
     * @param string $k the name of the argument to be set
     * @param string $v the value to set for this argument
     *
     * @throws Oai_Exception when the argument is not part of the protocol, or when
     *                       the argument has already been set
     */
    private function _setArg($k, $v)
    {
        $keys = [
            Oai_Const::ARG_FROM,
            Oai_Const::ARG_IDENTIFIER,
            Oai_Const::ARG_METADATA_PREFIX,
            Oai_Const::ARG_SET,
            Oai_Const::ARG_TOKEN,
            Oai_Const::ARG_UNTIL,
            Oai_Const::ARG_VERB,
        ];

        if (!in_array($k, $keys)) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, "Unknown argument: $k");
        }

        /*
         * Check for duplicates argument in the URL
         */
        $haystack = $_SERVER['QUERY_STRING'];
        $needle = $k.'=';

        $i = strpos($haystack, $needle);
        if (false !== strpos($haystack, $needle, $i + strlen($needle))) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, "Argument repeated: $k");
        }

        $this->_args[$k] = trim($v);
    }

    /**
     * Set the OAI request's arguments using the URL of the HTTP request.
     */
    private function _setArgsFromUrl()
    {
        foreach (array_merge($_POST, $_GET) as $k => $v) {
            $this->_setArg($k, $v);
        }
    }

    /**
     * Check that the set of arguments contains a set of required arguments,
     * and no invalid arguments.
     *
     * @param string[] $r Required arguments
     * @param string[] $o Optional arguments
     *
     * @throws Oai_Exception when the OAI request violates this check
     */
    private function _doCheckArgSet($r = [], $o = [])
    {
        $r[] = Oai_Const::ARG_VERB;		/* always mandatory */
        $a = array_merge($r, $o);	/* all arguments */

        /* Look for arguments not allowed */
        foreach ($this->_args as $k => $v) {
            if (!in_array($k, $a)) {
                throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, "Argument not allowed: $k");
            }
        }

        /* Look for missing required arguments */
        foreach ($r as $k) {
            if (false === $this->_getArg($k)) {
                throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, "Missing argument: $k");
            }
        }
    }

    /**
     * Check that flow control is supported by the repository.
     *
     * @throws Oai_Exception when the repository does not support flow control
     */
    private function _doCheckFlowControlIsSupported()
    {
        if (!isset($this->_repo_list_size)) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, 'Argument '.Oai_Const::ARG_TOKEN.' not allowed: flow control is a feature not supported by this repository');
        }
    }

    /**
     * Check for missing or invalid arguments in a request.
     *
     * @throws Oai_Exception when the OAI request violates this check
     */
    private function _checkArgSet()
    {
        /* Check we have a verb */
        if (false === ($verb = $this->_getArg(Oai_Const::ARG_VERB))) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, 'Missing verb');
        }

        /* Check it is not blank */
        if ('' === $verb) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_VERB, 'Verb is empty');
        }

        /* Check arguments, depending on the verb */
        switch ($verb) {
            case Oai_Const::VERB_IDENTIFY:
                $this->_doCheckArgSet();
                break;

            case Oai_Const::VERB_LISTSETS:
                if (false !== $this->_getArg(Oai_Const::ARG_TOKEN)) {
                    $this->_doCheckFlowControlIsSupported();
                }
                $this->_doCheckArgSet([], [Oai_Const::ARG_TOKEN]);
                break;

            case Oai_Const::VERB_LISTMETADATAFORMATS:
                $this->_doCheckArgSet([], [Oai_Const::ARG_IDENTIFIER]);
                break;

            case Oai_Const::VERB_GETRECORD:
                $this->_doCheckArgSet(
                    [Oai_Const::ARG_IDENTIFIER, Oai_Const::ARG_METADATA_PREFIX]
                );
                break;

            case Oai_Const::VERB_LISTRECORDS:
            case Oai_Const::VERB_LISTIDENTIFIERS:
                if (false !== $this->_getArg(Oai_Const::ARG_TOKEN)) {
                    $this->_doCheckFlowControlIsSupported();
                    $this->_doCheckArgSet([Oai_Const::ARG_TOKEN]);
                } else {
                    $this->_doCheckArgSet(
                        [Oai_Const::ARG_METADATA_PREFIX],
                        [Oai_Const::ARG_SET, Oai_Const::ARG_FROM, Oai_Const::ARG_UNTIL]
                    );
                }
                break;

            default:
                throw new Oai_Exception(Oai_Const::ERROR_BAD_VERB, "Unknown verb: $verb");
        }
    }

    /**
     * Check that a date is valid.
     *
     * @param Oai_Date $oDate the date to check
     * @param string   $err   the OAI error code when the check fails
     *
     * @throws Oai_Exception when the date is invalid
     */
    private function _checkDate(Oai_Date $oDate, $err = Oai_Const::ERROR_BAD_ARGUMENT)
    {
        /* Format of the date is allowed */

        $formats = [Oai_Const::FORMAT_DATE, Oai_Const::FORMAT_DATETIME_TZ];
        if (!in_array($oDate->getFormat(), $formats)) {
            throw new Oai_Exception($err, 'Invalid date format');
        }

        /* Granularity is compatible with the finest granularity of the repository */

        if ((Oai_Const::FORMAT_DATE === $this->_repo_date_format)
            && (Oai_Const::FORMAT_DATE !== $oDate->getFormat())
        ) {
            throw new Oai_Exception($err, 'Date format not supported by the repository');
        }
    }

    /**
     * Check from/until dates (when they both exist) are in the same format, and
     * are properly ordered.
     *
     * @param Oai_Date $oFrom  a "from" date
     * @param Oai_Date $oUntil a "until" date
     * @param string   $err    the OAI error code when the check fails
     *
     * @throws Oai_Exception when the date is invalid
     */
    private function _checkDates(
        ?Oai_Date $oFrom = null,
        ?Oai_Date $oUntil = null,
        $err = Oai_Const::ERROR_BAD_ARGUMENT
    ) {
        if ((isset($oFrom)) && (isset($oUntil))) {
            if ($oFrom->getFormat() !== $oUntil->getFormat()) {
                throw new Oai_Exception($err, 'Date range must have the same format');
            }

            if ($oFrom->toUnixTime() > $oUntil->toUnixTime()) {
                throw new Oai_Exception($err, 'Date range has negative length');
            }
        }
    }

    /**
     * Check a metadata prefix.
     *
     * @param string $metadataPrefix the prefix to check
     * @param string $err            the OAI error code when the check fails
     *
     * @throws Oai_Exception when the prefix is not supported
     */
    private function _checkMetadataPrefix(
        $metadataPrefix,
        $err = Oai_Const::ERROR_CANNOT_DISSEMINATE
    ) {
        if (!$this->_backend->metadataPrefixExists($metadataPrefix)) {
            throw new Oai_Exception($err, 'This metadata format is not supported by the repository: '.$metadataPrefix);
        }
    }

    /**
     * Check that an identifier exists in the repository.
     *
     * Note: when the repository supports deletions, an identifier having only
     * deleted metadata records is considered as existing (as, e.g., GetRecord
     * must not return an error).
     *
     * @param string $identifier the identifier to check for
     * @param string $err        the OAI error code when the check fails
     *
     * @throws Oai_Exception when the identifier is unknown
     */
    private function _checkIdentifier($identifier, $err = Oai_Const::ERROR_UNKNOWN_ID)
    {
        if (!$this->_backend->identifierExists($this->_no_deleted, $identifier)) {
            throw new Oai_Exception($err, 'Unknown identifier');
        }
    }

    /**
     * Check that a set is supported by the repository.
     *
     * @param string $setSpec the set to check for
     * @param string $err     the OAI error code when the check fails
     *
     * @throws Oai_Exception when the set is unknown
     */
    private function _checkSetSpec($setSpec, $err = Oai_Const::ERROR_NO_RECORDS)
    {
        if (!$this->_backend->setSpecExists($setSpec)) {
            throw new Oai_Exception($err, 'This set is not supported by the repository');
        }
    }

    /**
     * Return token expiration time in unix time.
     *
     * @param Oai_Token $oToken the token to check
     *
     * @return int Unix timestamp of the expiration date
     */
    private function _tokenExpirationTime(Oai_Token $oToken)
    {
        return $oToken->getDatestamp()->toUnixTime() + $this->_repo_token_duration;
    }

    /**
     * Check if a token has expired.
     *
     * Is a token too old?
     *
     * @param Oai_Token $oToken the token to check
     *
     * @return bool true if the token has expired, false otherwise
     */
    private function _tokenHasExpired(Oai_Token $oToken)
    {
        return time() > $this->_tokenExpirationTime($oToken);
    }

    /**
     * Check a token is valid.
     *
     * @param Oai_Token $oToken the token to check
     * @param string    $err    the OAI error code when the check fails
     *
     * @throws Oai_Exception when the token is invalid
     */
    private function _checkToken(Oai_Token $oToken, $err = Oai_Const::ERROR_BAD_TOKEN)
    {
        $oDatestamp = $oToken->getDatestamp();
        $metadataPrefix = $oToken->getMetadataPrefix();
        $setSpec = $oToken->getSetSpec();
        $oFrom = $oToken->getFrom();
        $oUntil = $oToken->getUntil();

        /* The datestamp must have the same granularity as the repository */
        $this->_checkDate($oDatestamp, $err);
        if ($this->_repo_date_format !== $oDatestamp->getFormat()) {
            throw new Oai_Exception($err, 'The datestamp must have the same granularity as the repository');
        }

        if (false !== $metadataPrefix) {
            $this->_checkMetadataPrefix($metadataPrefix, $err);
        }

        if (isset($oFrom)) {
            $this->_checkDate($oFrom, $err);
        }

        if (isset($oUntil)) {
            $this->_checkDate($oUntil, $err);
        }

        $this->_checkDates($this->_arg_from, $this->_arg_until);

        if (false !== $setSpec) {
            $this->_checkSetSpec($setSpec, $err);
        }

        if ($this->_tokenHasExpired($oToken)) {
            throw new Oai_Exception($err, 'Token has expired.');
        }
    }

    /**
     * Check a verb.
     *
     * @param string $verb the verb to check
     * @param string $err  the OAI error code when the check fails
     *
     * @throws Oai_Exception when the verb is unknown
     */
    private function _checkVerb($verb, $err = Oai_Const::ERROR_BAD_VERB)
    {
        $verbs = [
            Oai_Const::VERB_GETRECORD,
            Oai_Const::VERB_IDENTIFY,
            Oai_Const::VERB_LISTIDENTIFIERS,
            Oai_Const::VERB_LISTMETADATAFORMATS,
            Oai_Const::VERB_LISTRECORDS,
            Oai_Const::VERB_LISTSETS,
        ];

        if (!in_array($verb, $verbs)) {
            throw new Oai_Exception($err, "Unknown verb: $verb");
        }
    }

    /**
     * Parse a date, returning a new Oai_Date object.
     *
     * @param string $str    the date to parse
     * @param string $err    the OAI error code when the check fails
     * @param string $prompt a short sentence indicating why the date is invalid
     *
     * @return Oai_Date the object representing the parsed date
     *
     * @throws Oai_Exception when the date is invalid
     */
    private static function _parseDate(
        $str,
        $err = Oai_Const::ERROR_BAD_ARGUMENT,
        $prompt = 'Invalid date'
    ) {
        try {
            $obj = Oai_Date::createFromString($str);

            return $obj;
        } catch (Exception $e) {
            throw new Oai_Exception($err, "$prompt: {$e->getMessage()}");
        }
    }

    /**
     * Parse a token, returning a new Oai_Token object.
     *
     * @param string $str the token to parse
     *
     * @return Oai_Token the object representing the parsed token
     *
     * @throws Oai_Exception when the token cannot be parsed
     */
    private static function _parseToken($str)
    {
        try {
            $obj = new Oai_Token($str);

            return $obj;
        } catch (Exception $e) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_TOKEN, "Invalid token: {$e->getMessage()}");
        }
    }

    /**
     * Check the syntax of each argument, and set corresponding object members
     * if any.
     *
     * @throws Oai_Exception when an argument is empty
     */
    private function _checkEachArgSyntax()
    {
        foreach ($this->_getArgSet() as $k => $v) {
            if ('' === $v) {
                throw new Oai_Exception(Oai_Const::ERROR_BAD_ARGUMENT, "Argument is empty: $k");
            }

            switch ($k) {
                case Oai_Const::ARG_TOKEN:
                    $oToken = self::_parseToken($v);
                    $this->_checkToken($oToken);

                    $this->_arg_token = $oToken;
                    break;

                case Oai_Const::ARG_FROM:
                    $oDate = self::_parseDate($v);
                    $this->_checkDate($oDate);

                    $this->_arg_from = $oDate;
                    break;

                case Oai_Const::ARG_UNTIL:
                    $oDate = self::_parseDate($v);
                    $this->_checkDate($oDate);

                    $this->_arg_until = $oDate;
                    break;

                case Oai_Const::ARG_VERB:
                    $this->_checkVerb($v);
                    break;

                default:
            }
        }
    }

    /**
     * Check that arguments containing references point to existing data.
     */
    private function _checkEachArgRef()
    {
        foreach ($this->_getArgSet() as $k => $v) {
            switch ($k) {
                case Oai_Const::ARG_METADATA_PREFIX:
                    $this->_checkMetadataPrefix($v);
                    break;

                case Oai_Const::ARG_IDENTIFIER:
                    $this->_checkIdentifier($v);
                    break;

                case Oai_Const::ARG_SET:
                    $this->_checkSetSpec($v);
                    break;

                default:
            }
        }
    }

    /**
     * Perform some extra checks imposed by the protocol.
     *
     * @throws Oai_Exception when a check fails
     */
    private function _extraChecks()
    {
        /*
         * Identifier has at least one (possibly deleted) record for
         * the metadataPrefix
         */

        $identifier = $this->_getArg(Oai_Const::ARG_IDENTIFIER);
        $metadataPrefix = $this->_getArg(Oai_Const::ARG_METADATA_PREFIX);

        if ((false !== $identifier) && (false !== $metadataPrefix)) {
            if (!$this->_backend->recordExists(
                $this->_no_deleted,
                $identifier,
                $metadataPrefix
            )) {
                throw new Oai_Exception(Oai_Const::ERROR_CANNOT_DISSEMINATE, 'No such metadata format for this identifier');
            }
        }

        /*
         * Dates are coherent
         */

        $this->_checkDates($this->_arg_from, $this->_arg_until);

        /*
         * A token for ListIdentifiers and ListRecords must contain a
         * metadataPrefix part.
         */

        $verb = $this->_getArg(Oai_Const::ARG_VERB);
        $token = $this->_getArg(Oai_Const::ARG_TOKEN);

        if ((false !== $token)
            && ((Oai_Const::VERB_LISTIDENTIFIERS == $verb)
                || (Oai_Const::VERB_LISTRECORDS == $verb))
            && (false === $this->_arg_token->getMetadataPrefix())) {
            throw new Oai_Exception(Oai_Const::ERROR_BAD_TOKEN, 'Invalid token for this verb: no metadata information');
        }
    }

    /*
     * DOM helpers to build a response
     */

    /**
     * Create an XML element.
     *
     * @param string $name  name of the element
     * @param string $value value of the element
     *
     * @return DOMElement the XML element created
     */
    private function _createElement($name, $value)
    {
        return $this->_response->createElement($name, $value ?? '');
    }

    /**
     * Add a child to an element.
     *
     * @param DOMElement $xml   the parent element
     * @param string     $name  the name of the child element
     * @param string     $value the value of the child element
     *
     * @return DOMElement the child element created
     */
    private function _addChild($xml, $name, $value = null)
    {
        $element = $this->_createElement($name, $value);
        $xml->appendChild($element);

        return $element;
    }

    /**
     * Add an (untrusted) XML fragment to a node.
     *
     * @param DOMElement $node       the parent node
     * @param string     $xml_string the fragment to attach to the parent node
     */
    private function _addFragment($node, $xml_string)
    {
        /* strip out XML declaration */
        $xml_string = preg_replace("/^\<\?xml[^\?]*\?\>\n*/", '', $xml_string, 1);

        $fragment = $this->_response->createDocumentFragment();

        /* add the fragment, suppressing all errors and warnings */
        $state = libxml_use_internal_errors(true);
        $success = $fragment->appendXML($xml_string);
        libxml_use_internal_errors($state);

        if (!$success) {
            throw new Exception('Corrupted data:'." malformed XML fragment in {$node->tagName}");
        }

        $node->appendChild($fragment);
    }

    /**
     * Add a child 'oai:error' to the response root.
     *
     * @param string $code    OAI error code
     * @param string $message error message
     */
    private function _setError($code, $message)
    {
        $error = $this->_addChild($this->_root, 'oai:error', $message);
        $error->setAttribute('code', $code);
    }

    /**
     * Return the XML root of the OAI response.
     *
     * @return string OAI root element as a string
     */
    private static function _docRoot()
    {
        $ns['xmlns:xsd'] = Oai_Const::NS_XSD;
        $ns['xmlns:xsi'] = Oai_Const::NS_XSI;
        $ns['xmlns:oai'] = Oai_Const::NS_OAI;
        $ns['xsi:schemaLocation'] = Oai_Const::NS_OAI.' '.Oai_Const::XS_OAI;

        return Xml_Utils::emptyElementAsString('oai:OAI-PMH', $ns);
    }

    /**
     * Prepare the OAI response's envelope, or set an error response if an error
     * occurred.
     *
     * @param string|false $oai_error an OAI error code if an error occurred,
     *                                or false otherwise
     */
    private function _prepareResponse($oai_error = false)
    {
        /* Header processing instruction (as required by OAI-PMH) */

        $hpi = Xml_Utils::piAsString(
            'xml',
            [
                'version' => '1.0',
                'encoding' => 'UTF-8',
            ]
        );

        /* XSL stylesheet processing instruction */
        $pi = '';
        if (isset($this->_stylesheet)) {
            $pi = Xml_Utils::piAsString(
                'xml-stylesheet',
                [
                    'type' => 'text/xsl',
                    'href' => $this->_stylesheet,
                ]
            );
        }

        /* Build the document root */
        $this->_response = new DOMDocument('1.0', 'UTF-8');
        $this->_response->loadXML($hpi.$pi.self::_docRoot());
        $this->_root = $this->_response->documentElement;

        /* Response date: we do not set it yet as it must be the date at which the
         * response is sent */
        $this->_response_date = $this->_addChild(
            $this->_root,
            'oai:responseDate'
        );

        /* Base URL of the OAI script */
        $request = $this->_addChild($this->_root, 'oai:request', $this->_repo_baseurl);

        /*
         * http://www.openarchives.org/OAI/openarchivesprotocol.html#XMLResponse
         *
         * In cases where the request that generated this response resulted in
         * a badVerb or badArgument error condition, the repository must return
         * the base URL of the protocol request only. Attributes must not be
         * provided in these cases.
         *
         */

        $errors = [
            Oai_Const::ERROR_BAD_VERB,
            Oai_Const::ERROR_BAD_ARGUMENT,
        ];
        if (!in_array($oai_error, $errors)) {
            foreach ($this->_args as $k => $v) {
                $request->setAttribute($k, $v);
            }
        }
    }

    /**
     * Build the OAI response.
     */
    private function _setResponse()
    {
        $this->_prepareResponse();

        /*
         * Parameters may be altered by a resumptionToken, so we work
         * on copies.
         */

        $verb = $this->_getArg(Oai_Const::ARG_VERB);
        $identifier = $this->_getArg(Oai_Const::ARG_IDENTIFIER);
        $metadataPrefix = $this->_getArg(Oai_Const::ARG_METADATA_PREFIX);
        $setSpec = $this->_getArg(Oai_Const::ARG_SET);

        $oToken = $this->_arg_token;
        $oFrom = $this->_arg_from;
        $oUntil = $this->_arg_until;

        /*
         * Store parameters in the current log record
         */
        $this->_logger->storeRequestParam(
            false !== $verb ? $verb : null,
            false !== $metadataPrefix ? $metadataPrefix : null,
            false !== $setSpec ? $setSpec : null,
            false !== $identifier ? $identifier : null,
            $oFrom,
            $oUntil,
            $oToken
        );

        /*
         * Flow control for list requests
         */

        /* index we are going to use for SELECTs */
        $index = 0;

        if (isset($oToken)) {
            $metadataPrefix = $oToken->getMetadataPrefix();
            $setSpec = $oToken->getSetSpec();
            $oFrom = $oToken->getFrom();
            $oUntil = $oToken->getUntil();

            /* Advance the index */
            $index = $oToken->getIndex() + $this->_repo_list_size;
        }

        /*
         * Response to a verb is always contained inside an element having
         * the verb as name.
         */
        $xverb = $this->_addChild($this->_root, 'oai:'.$verb);

        /*
         * per-verb treatment
         *
         * set $list_size and $complete_list_size when the request is a list request
         */
        switch ($verb) {
            case Oai_Const::VERB_IDENTIFY:
                $f = $this->_getRepoData();

                $this->_addChild($xverb, 'oai:repositoryName', $f['repositoryName']);
                $this->_addChild($xverb, 'oai:baseURL', $this->_repo_baseurl);
                $this->_addChild($xverb, 'oai:protocolVersion', '2.0');

                $emails = explode(',', $f['adminEmails']);
                foreach ($emails as $e) {
                    $e = trim($e);
                    $this->_addChild($xverb, 'oai:adminEmail', $e);
                }

                $this->_addChild(
                    $xverb,
                    'oai:earliestDatestamp',
                    $f['earliestDatestamp']
                );
                $this->_addChild($xverb, 'oai:deletedRecord', $f['deletedRecord']);
                $this->_addChild($xverb, 'oai:granularity', $f['granularity']);

                /* 2017-08-11: compression */

                foreach (Oai_Utils::supported_encoding() as $encoding) {
                    $this->_addChild($xverb, 'oai:compression', $encoding);
                }

                /* 2012-11-04: description records */

                $r = $this->_backend->repoDescriptionSelect();
                foreach ($r as $f) {
                    if (isset($f['description'])) {
                        $description = $this->_addChild($xverb, 'oai:description');
                        $this->_addFragment($description, $f['description']);
                    }
                }

                break;

            case Oai_Const::VERB_LISTMETADATAFORMATS:
                $r = $this->_backend->metadataPrefixSelect($identifier);

                foreach ($r as $f) {
                    $format = $this->_addChild($xverb, 'oai:metadataFormat');
                    $this->_addChild(
                        $format,
                        'oai:metadataPrefix',
                        $f['metadataPrefix']
                    );
                    $this->_addChild($format, 'oai:schema', $f['schema']);
                    $this->_addChild(
                        $format,
                        'oai:metadataNamespace',
                        $f['metadataNamespace']
                    );
                }

                break;

            case Oai_Const::VERB_LISTSETS:
                $r = $this->_backend->setSpecSelect(true);
                $complete_list_size = $r->fetchColumn();

                /* The complete list is empty */
                if (0 == $complete_list_size) {
                    throw new Oai_Exception(Oai_Const::ERROR_NO_SETS, 'No sets are defined for this repository');
                }

                $r = $this->_backend->setSpecSelect(false, $index, $this->_repo_list_size);
                $list_size = 0;

                foreach ($r as $f) {
                    ++$list_size;

                    $set = $this->_addChild($xverb, 'oai:set');
                    $this->_addChild($set, 'oai:setSpec', $f['setSpec']);
                    $this->_addChild($set, 'oai:setName', $f['setName']);

                    /* Descriptions (none, one or more) */
                    $rs = $this->_backend->setDescriptionSelect($f['setSpec']);
                    foreach ($rs as $fs) {
                        $description = $this->_addChild($set, 'oai:setDescription');
                        $this->_addFragment($description, $fs['setDescription']);
                    }
                }

                break;

            case Oai_Const::VERB_LISTRECORDS:
            case Oai_Const::VERB_LISTIDENTIFIERS:
                /* for list requests, we need the complete list size */

                $r = $this->_backend->metadataSelect(
                    true,
                    $this->_no_deleted,
                    false,
                    $metadataPrefix,
                    $setSpec,
                    $oFrom,
                    $oUntil
                );
                $complete_list_size = $r->fetchColumn();

                /* The complete list is empty */
                if (0 == $complete_list_size) {
                    throw new Oai_Exception(Oai_Const::ERROR_NO_RECORDS);
                }

                // no break
            case Oai_Const::VERB_GETRECORD:
                $r = $this->_backend->metadataSelect(
                    false,
                    $this->_no_deleted,
                    $identifier,
                    $metadataPrefix,
                    $setSpec,
                    $oFrom,
                    $oUntil,
                    $index,
                    $this->_repo_list_size,
                    Oai_Const::VERB_LISTRECORDS == $verb || Oai_Const::VERB_GETRECORD == $verb
                );

                $list_size = 0;

                foreach ($r as $f) {
                    ++$list_size;

                    if ((Oai_Const::VERB_LISTRECORDS == $verb)
                        || (Oai_Const::VERB_GETRECORD == $verb)
                    ) {
                        $container = $this->_addChild($xverb, 'oai:record');
                    } else {
                        $container = $xverb;
                    }

                    $header = $this->_addChild($container, 'oai:header');
                    $this->_addChild($header, 'oai:identifier', $f['identifier']);

                    $oDatestamp = self::_parseDate(
                        $f['datestamp'],
                        Oai_Const::ERROR_CANNOT_DISSEMINATE,
                        'Incorrect date in metadata'
                    );
                    $this->_addChild(
                        $header,
                        'oai:datestamp',
                        $oDatestamp->toString($this->_repo_date_format)
                    );

                    /*
                     * List of setSpec for this identifier
                     *
                     * 2011-06-06: enforce the following part of the OAI-PMH spec:
                     *
                     * "The list of setSpec elements should include only the minimum
                     * number of setSpec elements required to specify the set
                     * membership."
                     */

                    /* Retrieve sets, including redundant ones */
                    $allSets = [];

                    $rs = $this->_backend->setSelect($f['identifier'], $metadataPrefix);
                    foreach ($rs as $fs) {
                        $allSets[] = $fs['setSpec'];
                    }

                    /* Filter out redundant sets */
                    rsort($allSets);

                    $sets = [];
                    $prev = [];
                    foreach ($allSets as $oneSet) {
                        $curr = explode(':', $oneSet);

                        if ((count($curr) > count($prev))
                            || (array_slice($prev, 0, count($curr)) !== $curr)
                        ) {
                            $sets[] = $oneSet;
                            $prev = $curr;
                        }
                    }

                    /* Order by setName (not in OAI-PMH standard, though) */
                    sort($sets);

                    /* Generate XML subelements */
                    foreach ($sets as $oneSet) {
                        $this->_addChild($header, 'oai:setSpec', $oneSet);
                    }

                    /*
                     * Deleted
                     */
                    if ($f['deleted']) {
                        $header->setAttribute('status', 'deleted');
                    } elseif ((Oai_Const::VERB_LISTRECORDS == $verb)
                        || (Oai_Const::VERB_GETRECORD == $verb)
                    ) {
                        $metadata = $this->_addChild($container, 'oai:metadata');
                        $this->_addFragment($metadata, $f['metadata']);

                        /* 'about' data for this record */
                        $ra = $this->_backend->aboutSelect($f['identifier'], $metadataPrefix);
                        foreach ($ra as $fa) {
                            $about = $this->_addChild($container, 'oai:about');
                            $this->_addFragment($about, $fa['about']);
                        }
                    }
                }

                /* No such record, or the complete list is empty */
                if (!isset($oToken) && (0 == $list_size)) {
                    throw new Oai_Exception(Oai_Const::ERROR_NO_RECORDS);
                }

                break;
        }

        /*
         * Add a resumption token when required
         *
         * use $list_size and $complete_list_size computed above
         */
        if ((Oai_Const::VERB_LISTSETS == $verb)
            || (Oai_Const::VERB_LISTRECORDS == $verb)
            || (Oai_Const::VERB_LISTIDENTIFIERS == $verb)
        ) {
            /* Default: add no token at all */
            $token = null;

            /* No more records, and we received a token: send a blank
             * termination token
             */
            if (($list_size < $this->_repo_list_size) && (isset($oToken))) {
                $token = '';
            }

            /* We may have more records to send */
            if ($list_size == $this->_repo_list_size) {
                /* No token received: we create a brand new token */
                if (!isset($oToken)) {
                    $oToken = new Oai_Token();
                    $oDate = Oai_Date::now($this->_repo_date_format);
                    $oToken->setDatestamp($oDate);

                    if ((Oai_Const::VERB_LISTRECORDS == $verb)
                        || (Oai_Const::VERB_LISTIDENTIFIERS == $verb)
                    ) {
                        $oToken->setMetadataPrefix($metadataPrefix);
                        $oToken->setFrom($oFrom);
                        $oToken->setUntil($oUntil);
                        $oToken->setSetSpec($setSpec);
                    }
                }

                /* Set the index of the token */
                $oToken->setIndex($index);

                $token = $oToken->toString();
            }

            if (isset($token)) {
                /* Generates the oai:resumptionToken element */

                $cursor = $oToken->getIndex();
                $resumptionToken = $this->_addChild(
                    $xverb,
                    'oai:resumptionToken',
                    $token
                );

                /* attribute: token expiration date in TZ format */
                if (isset($this->_repo_token_duration)) {
                    $oDate = Oai_Date::createFromTimestamp(
                        $this->_tokenExpirationTime($oToken),
                        Oai_Const::FORMAT_DATETIME_TZ
                    );
                    $resumptionToken->setAttribute('expirationDate', $oDate->toString());
                }
                /* attribute: (approximate) size of the complete list */
                $resumptionToken->setAttribute('completeListSize', $complete_list_size);
                /* attribute: cursor of the current answer */
                $resumptionToken->setAttribute('cursor', $cursor);

                /* Store token data in the current log record */
                $this->_logger->storeRequestToken($token, $cursor);
            }
        }
    }

    /**
     * Send a OAI response through HTTP.
     *
     * @param string $response OAI response
     */
    private static function _sendResponse($response)
    {
        ini_set('arg_separator.output', '&amp;');
        header('Content-Type: text/xml; charset=utf-8');

        exit($response);
    }

    /**
     * Analyze the OAI request and send a response.
     *
     * @throws Exception when a (non-OAI) error occurs
     */
    private function _respond()
    {
        try {
            $this->_setArgsFromUrl();
            $this->_checkArgSet();
            $this->_checkEachArgSyntax();
            $this->_checkEachArgRef();
            $this->_extraChecks();

            $this->_setResponse();
        } catch (Oai_Exception $e) {
            $code = $e->getOaiCode();
            $message = $e->getMessage();

            $this->_prepareResponse($code);
            $this->_setError($code, $message);

            $this->_logger->storeRequestError($code, $message);
        }

        /*
         * http://www.openarchives.org/OAI/openarchivesprotocol.html#XMLResponse
         *
         * "responseDate -- a UTCdatetime indicating the time and date that the
         * response was sent."
         *
         * Accordingly, we set the response date just before sending the response
         */
        $oDate = Oai_Date::now($this->_repo_date_format);
        $this->_response_date->nodeValue = $oDate->toString();

        $responseAsString = $this->_response->saveXML();

        $this->_logger->storeRequestResponse($oDate, $responseAsString);
        $this->_logger->endRequest();

        self::_sendResponse($responseAsString);
    }

    /**
     * Analyse an OAI request, try to log it, and send a response.
     *
     * This static method never throws exceptions. Internal errors trigger
     * a "service unavailable" HTTP response.
     *
     * @param string    $hostname   host name to connect to
     * @param string    $username   user name
     * @param string    $password   password
     * @param string    $database   database
     * @param string    $repo       repo id (oai_repo.id)
     * @param string    $stylesheet a XSL style sheet attached to the OAI response
     * @param int|false $limit      the size of incomplete lists, or false if the
     *                              default limit applies
     */
    public static function execRequest(
        $hostname,
        $username,
        $password,
        $database,
        $repo,
        $stylesheet = null,
        $limit = null
    ) {
        try {
            $oai = new Oai(
                $hostname,
                $username,
                $password,
                $database,
                $repo,
                $stylesheet,
                $limit
            );

            $oai->_logger->beginRequest();

            try {
                $oai->_respond();
            } catch (Exception $e) {
                $oai->_logger->endRequest($e);
                throw $e;
            }
        } catch (Exception $e) {
            /*
             * Send a "Service Unavailable" HTTP response.
             *
             * Since this is an internal error, we ask the user to retry
             * after one full day.
             */
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            header('Status: 503 Service Temporarily Unavailable');
            header('Retry-After: 86400');

            exit(OAI_DEBUG == 1 ? $e->getMessage() : '');
        }
    }
}
