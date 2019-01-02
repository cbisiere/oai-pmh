<?php

/**
 * OAI protocol v2: constants.
 *
 * PHP version 5.4+
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
    const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    const NS_XSD = 'http://www.w3.org/2001/XMLSchema';
    const NS_OAI = 'http://www.openarchives.org/OAI/2.0/';
    const XS_OAI = 'http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd';

    /**
     * Support levels for deletions.
     */
    const DEL_SUPPORT_NO = 'no';
    const DEL_SUPPORT_TRANSIENT = 'transient';
    const DEL_SUPPORT_PERSISTENT = 'persistent';

    /**
     * Supported date formats.
     */
    const FORMAT_DATE = 'YYYY-MM-DD';
    const FORMAT_DATETIME = 'YYYY-MM-DD hh:mm:ss';
    const FORMAT_DATETIME_TZ = 'YYYY-MM-DDThh:mm:ssZ';

    /**
     * Error codes.
     */
    const ERROR_BAD_ARGUMENT = 'badArgument';
    const ERROR_BAD_TOKEN = 'badResumptionToken';
    const ERROR_BAD_VERB = 'badVerb';
    const ERROR_CANNOT_DISSEMINATE = 'cannotDisseminateFormat';
    const ERROR_UNKNOWN_ID = 'idDoesNotExist';
    const ERROR_NO_RECORDS = 'noRecordsMatch';
    const ERROR_NO_METADATA = 'noMetadataFormats';
    const ERROR_NO_SETS = 'noSetHierarchy';

    /**
     * Arguments and verbs.
     */
    const ARG_VERB = 'verb';
    const ARG_METADATA_PREFIX = 'metadataPrefix';
    const ARG_SET = 'set';
    const ARG_IDENTIFIER = 'identifier';
    const ARG_FROM = 'from';
    const ARG_UNTIL = 'until';
    const ARG_TOKEN = 'resumptionToken';

    const VERB_IDENTIFY = 'Identify';
    const VERB_LISTMETADATAFORMATS = 'ListMetadataFormats';
    const VERB_LISTSETS = 'ListSets';
    const VERB_LISTIDENTIFIERS = 'ListIdentifiers';
    const VERB_LISTRECORDS = 'ListRecords';
    const VERB_GETRECORD = 'GetRecord';
}
