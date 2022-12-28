<?php

/**
 * OAI protocol v2: constants.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI constants.
 *
 * This class provides various constants used by OAI 2.0.
 */
class Oai_Const
{
    /**
     * Name spaces, schemas.
     */
    public const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    public const NS_XSD = 'http://www.w3.org/2001/XMLSchema';
    public const NS_OAI = 'http://www.openarchives.org/OAI/2.0/';
    public const XS_OAI = 'http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd';

    /**
     * Support levels for deletions.
     */
    public const DEL_SUPPORT_NO = 'no';
    public const DEL_SUPPORT_TRANSIENT = 'transient';
    public const DEL_SUPPORT_PERSISTENT = 'persistent';

    /**
     * Supported date formats.
     */
    public const FORMAT_DATE = 'YYYY-MM-DD';
    public const FORMAT_DATETIME = 'YYYY-MM-DD hh:mm:ss';
    public const FORMAT_DATETIME_TZ = 'YYYY-MM-DDThh:mm:ssZ';

    /**
     * Error codes.
     */
    public const ERROR_BAD_ARGUMENT = 'badArgument';
    public const ERROR_BAD_TOKEN = 'badResumptionToken';
    public const ERROR_BAD_VERB = 'badVerb';
    public const ERROR_CANNOT_DISSEMINATE = 'cannotDisseminateFormat';
    public const ERROR_UNKNOWN_ID = 'idDoesNotExist';
    public const ERROR_NO_RECORDS = 'noRecordsMatch';
    public const ERROR_NO_METADATA = 'noMetadataFormats';
    public const ERROR_NO_SETS = 'noSetHierarchy';

    /**
     * Arguments and verbs.
     */
    public const ARG_VERB = 'verb';
    public const ARG_METADATA_PREFIX = 'metadataPrefix';
    public const ARG_SET = 'set';
    public const ARG_IDENTIFIER = 'identifier';
    public const ARG_FROM = 'from';
    public const ARG_UNTIL = 'until';
    public const ARG_TOKEN = 'resumptionToken';

    public const VERB_IDENTIFY = 'Identify';
    public const VERB_LISTMETADATAFORMATS = 'ListMetadataFormats';
    public const VERB_LISTSETS = 'ListSets';
    public const VERB_LISTIDENTIFIERS = 'ListIdentifiers';
    public const VERB_LISTRECORDS = 'ListRecords';
    public const VERB_GETRECORD = 'GetRecord';
}
