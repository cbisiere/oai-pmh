<?php

/**
 * TSE OAI repository cache updater.
 *
 * PHP version 7.1+
 *
 * Implementation notes:
 *
 * Datestamp of source objects
 * -----------------------------
 *
 * Touching the source datestamps whenever an object is modified, is deleted,
 * enters or exits sets, is crucial. If the datestamp of a source object
 * is not updated properly, the corresponding metadata records will not be
 * updated, and may not show up in selective harvesting using a 'from' date.
 *
 * Datestamp of metadata records
 * -----------------------------
 *
 * The datestamp of a metadata record is the datestamp of the record itself, not
 * the datestamp of the source data.
 *
 * Entry and exit from a set
 * -----------------------------
 *
 * OAI-PMH says nothing about metadata records dynamically entering or
 * leaving sets. Thus, we are free to set our own policy for set changes of
 * deleted and non-deleted records, provided we comply with the OAI-PMH rule
 * stating that the datestamp of a deleted record is the date at which
 * the record was deleted.
 *
 * Our policy is as follows:
 *
 * - Set memberships of deleted and non-deleted record records are always
 *   updated. Update for deleted record is mandatory since we have to handle
 *   cases where a publication has both deleted and non-deleted metadata
 *   records. Metadata datestamps are not touched, though.
 *
 * - Metadata datestamp is touched when a non-deleted record enters or
 *   leaves a set. As a consequence, these records will be caught by
 *   selective harvesting.
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Abstract class to update cached data.
 *
 * Throw exceptions when the backend cannot be initialized, or the updating process
 * cannot start or terminate normally.
 */
abstract class Oai_Updater
{
    /** @var Oai_Connection Connection to the database */
    private $_connection;
    /** @var Oai_Backend_Update Data backend */
    protected $_backend;

    /** @var string Repository id (oai_repo.id) */
    private $_repo;

    /* timestamp interval of the source objects for which an update is requested */
    /** @var string iso 'from' timestamp date */
    private $_from;
    /** @var string iso 'to' timestamp date */
    private $_to;

    /* data that must be updated */
    /** @var array array of target metadata prefixes */
    private $_metadataPrefixArray;
    /** @var array array of setspecs */
    private $_setSpecArray;
    /** @var array array of primary keys in the database */
    private $_identifierArray;
    /** @var array array of OAI identifiers */
    private $_oaiIdentifierArray;

    /**
     * Constructor.
     *
     * @param string $hostname     host name to connect to
     * @param string $username     user name
     * @param string $password     password
     * @param string $database     database to select
     * @param string $repo         repo id
     * @param bool   $save_history backup records before update
     */
    public function __construct(
        $hostname,
        $username,
        $password,
        $database,
        $repo,
        $save_history
    ) {
        $this->_repo = $repo;

        $this->_connection = new Oai_Connection(
            $hostname,
            $username,
            $password,
            $database
        );

        $this->_backend = new Oai_Backend_Update(
            $this->_connection,
            $this->_repo
        );
        $this->_backend->_save_history = $save_history;
    }

    /*
     * Abstract methods
     */

    /**
     * Build an OAI identifier from a local identifier.
     *
     * A local identifier is the primary key in our database.
     *
     * @param mixed $id identifier in the source database
     *
     * @return string OAI identifier in the repository
     */
    abstract protected function identifier($id);

    /**
     * Return the primary key of a source object.
     *
     * @param mixed $f the source object
     *
     * @return mixed identifier in the source database
     */
    abstract protected function id($f);

    /**
     * Return the datestamp of a source object. The datestamp is the modification
     * date of the source object, including modifications that may change its
     * set memberships.
     *
     * @param mixed $f the source object
     *
     * @return string datestamp in database native format
     */
    abstract protected function datestamp($f);

    /**
     * Return true if a source object is deleted.
     *
     * @param mixed $f the source object
     *
     * @return bool true if the source object is deleted, false otherwise
     */
    abstract protected function deleted($f);

    /**
     * Build metadata for a source object, as a string.
     *
     * @param mixed  $f              the source object
     * @param string $metadataPrefix requested metadata
     *
     * @return string requested metadata, or null if no such metadata are
     *                available for this source object
     */
    abstract protected function metadata($f, $metadataPrefix);

    /**
     * Return 'about' data for a given record, as an array of strings, or false.
     *
     * @param mixed  $f              the source object
     * @param string $metadataPrefix requested metadata
     *
     * @return mixed array of strings, each one containing valid xml data, or
     *               false if the record has no 'about' data
     */
    abstract protected function about($f, $metadataPrefix);

    /**
     * Fetch a source object from an iterable list of source objects.
     *
     * @param mixed $r the iterable list of source objects
     *
     * @return mixed the fetched object or false if there are no more objects
     */
    abstract protected function nextObject(&$r);

    /**
     * Exec a query to select source objects in a given set, and return a
     * corresponding array of iterable lists of source objects, or false.
     *
     * A typical use case for such two-level structure is when the target
     * objects are extracted through several PDO queries. In that case,
     * this method could return an array of result sets, and nextObject
     * would call PDOStatement::fetch().
     *
     * @param mixed[] $identifierArray array of local identifiers
     * @param string  $from            iso 'from' timestamp date
     * @param string  $to              iso 'to' timestamp date
     * @param bool    $noDeleted       exclude deleted records
     * @param string  $set             OAI set requested
     *
     * @return array array of source objects
     */
    abstract protected function objects(
        $identifierArray = [],
        $from = null,
        $to = null,
        $noDeleted = false,
        $set = null
    );

    /*
     * Concrete methods
     */

    /**
     * Update a metadata record.
     *
     * We catch "Duplicate entry" MySQL errors. Such an error occurs when
     * the identifier field in the source database is not a primary key, and
     * returns duplicate source objects for this identifier. In that case,
     * a warning is emitted.
     *
     * @param string $identifier     OAI identifier
     * @param mixed  $f              the source object
     * @param string $metadataPrefix metadataprefix
     * @param string $datestamp      datestamp of the source object
     * @param bool   $deleted        delete status
     * @param string $metadata       metadata as a string
     *
     * @throws Exception pass-through
     */
    private function _metadataUpdateOne(
        $identifier,
        $f,
        $metadataPrefix,
        $datestamp,
        $deleted,
        $metadata
    ) {
        /* Look for a perfect match */
        $match = $this->_backend->metadataMatch(
            $identifier,
            $metadataPrefix,
            $deleted,
            $metadata
        );

        /* No perfect match: a new record must be created */

        if (!$match) {
            try {
                /* Delete (or archive) existing record (if any) */
                $this->_backend->metadataDelete($identifier, $metadataPrefix);

                /* Then, insert new metadata record */
                $this->_backend->metadataCreate(
                    $identifier,
                    $metadataPrefix,
                    $datestamp,
                    $deleted,
                    $metadata
                );

                /* and 'about' data */
                $aboutArr = $this->about($f, $metadataPrefix);
                foreach ($aboutArr as $n => $about) {
                    $this->_backend->aboutCreate(
                        $identifier,
                        $metadataPrefix,
                        $datestamp,
                        $about,
                        $n + 1
                    );
                }
            } catch (Exception $e) {
                /*
                 * Duplicate entry
                 */
                if (Oai_Backend_Update::errIsDuplicate($e->getCode())) {
                    $this->_warning .=
                        "Collision on identifier '{$identifier}'."
                        ." Please check for duplicates on the source database.\n";
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Update a metadata record when it is necessary.
     *
     * Update metadata when necessary, that is, when the metadata
     * record does not exist, or when it exists but is outdated
     * (the datestamp of the source object being more recent that
     * the datestamp of the metadata record).
     *
     * @param string $identifier     OAI identifier
     * @param mixed  $f              the source object
     * @param string $metadataPrefix metadataprefix
     *
     * @throws Exception pass-through
     */
    private function _metadataUpdateOneWhenNecessary(
        $identifier,
        $f,
        $metadataPrefix
    ) {
        $datestamp = $this->datestamp($f);

        $metaOK = $this->_backend->metadataIsUpToDate(
            $identifier,
            $metadataPrefix,
            $datestamp
        );

        if (!$metaOK) {
            /*
             * There are two cases in which a metadata record takes
             * the OAI-PMH status 'deleted':
             *
             * 1) the source object has been deleted,
             *
             * 2) this particular metadata format is no more available
             *    for the source object.
             */
            $metadata = null;

            if (!$this->deleted($f)) {
                $metadata = $this->metadata($f, $metadataPrefix);
            }

            $metadataDeleted = !isset($metadata);

            /*
             * Update the metadata record accordingly
             *
             */
            $this->_metadataUpdateOne(
                $identifier,
                $f,
                $metadataPrefix,
                $datestamp,
                $metadataDeleted,
                $metadata
            );
        }
    }

    /**
     * Update metadata records.
     */
    private function _metadataUpdate()
    {
        /* Select source objects */

        $rs = $this->objects($this->_identifierArray, $this->_from, $this->_to);

        if (!empty($rs)) {
            foreach ($rs as $r) {
                /* Loop through source objects, updating metadata when necessary */
                while ($f = $this->nextObject($r)) {
                    $id = $this->id($f);
                    $identifier = $this->identifier($id);

                    /*
                    * All existing set membership data for this identifier will
                    * have to be confirmed to be considered valid.
                    */
                    if (count($this->_setSpecArray)) {
                        $this->_backend->setSpecToBeConfirmed(
                            $identifier,
                            $this->_metadataPrefixArray,
                            $this->_setSpecArray
                        );
                    }

                    foreach ($this->_metadataPrefixArray as $metadataPrefix) {
                        $this->_metadataUpdateOneWhenNecessary(
                            $identifier,
                            $f,
                            $metadataPrefix
                        );
                    }
                }
            }
        }
    }

    /**
     * Update setSpecs for non-deleted records.
     *
     * Deleted records keep their setSpecs as-is.
     */
    private function _setSpecUpdate()
    {
        /* Touch setSpec records according to set membership */
        foreach ($this->_setSpecArray as $setSpec) {
            $rs = $this->objects($this->_identifierArray, $this->_from, $this->_to, false, $setSpec);

            if (!empty($rs)) {
                foreach ($rs as $r) {
                    while ($f = $this->nextObject($r)) {
                        $id = $this->id($f);
                        $identifier = $this->identifier($id);

                        foreach ($this->_metadataPrefixArray as $metadataPrefix) {
                            /*
                            * If the setSpec record did not exist, it means that
                            * a metadata record entered a setspec. Accordingly,
                            * we create the setspec record, as a confirmed set
                            * membership.
                            */
                            if (!$this->_backend->setSpecConfirmOk(
                                $identifier,
                                $metadataPrefix,
                                $setSpec
                            )) {
                                $this->_backend->setSpecCreate(
                                    $identifier,
                                    $metadataPrefix,
                                    $setSpec
                                );
                            }
                        }
                    }
                }
            }
        }

        /* Update OAI 'datestamp' of metadata records that changed set */
        $this->_backend->setSpecTouchMeta(
            $this->_metadataPrefixArray,
            $this->_setSpecArray,
            $this->_oaiIdentifierArray
        );

        /* Delete unconfirmed target records (i.e. meta leaving sets) */
        $this->_backend->setSpecDeleteUnconfirmed(
            $this->_metadataPrefixArray,
            $this->_setSpecArray,
            $this->_oaiIdentifierArray
        );
    }

    /**
     * Update metadata and set membership data.
     */
    private function _update()
    {
        /*
         * Set "confirmed" flag for existing target setSpec records. Since it
         * does not select dirty records only, it is way too large. This
         * does not matter much, though.
         */
        if (count($this->_setSpecArray)) {
            $this->_backend->setSpecInitConfirm(
                $this->_metadataPrefixArray,
                $this->_setSpecArray,
                $this->_oaiIdentifierArray
            );
        }

        /* Update metadata */
        $this->_metadataUpdate();

        /* Update set membership data */
        if (count($this->_setSpecArray)) {
            $this->_setSpecUpdate();
        }
    }

    /**
     * Update OAI cached data.
     *
     * The cache is generated for deleted and non-deleted objects, in a set
     * of setspecs. Metadata are generated for one or several metadataprefixes.
     *
     * Default values are such that calling run() will update the whole
     * repository.
     *
     * TODO: report back errors to the caller
     *
     * @param string[]    $setSpecArray        Setspecs, or ['all']
     * @param string[]    $metadataPrefixArray Metadataprefixes, or ['all']
     * @param string[]    $identifierArray     Primary keys in the database,
     *                                         or ['all']
     * @param string|null $from                From date, in iso format
     * @param string|null $to                  To date, in iso format
     */
    public function run(
        $setSpecArray = ['all'],
        $metadataPrefixArray = ['all'],
        $identifierArray = ['all'],
        $from = null,
        $to = null
    ) {
        /*
         * Handle special parameter value ['all'] for sets and metas,
         */
        if ($setSpecArray === ['all']) {
            $setSpecArray = $this->setSpecArray();
        }

        if ($metadataPrefixArray === ['all']) {
            $metadataPrefixArray = $this->metadataPrefixArray();
        }

        if ($identifierArray === ['all']) {
            $identifierArray = [];
        }

        /*
         * Set private variable members
         */
        $this->_from = $from;
        $this->_to = $to;
        $this->_metadataPrefixArray = $metadataPrefixArray;
        $this->_setSpecArray = $setSpecArray;
        $this->_identifierArray = $identifierArray;

        /* corresponding OAI identifiers */
        $identifiers = [];
        foreach ($this->_identifierArray as $id) {
            $identifiers[] = $this->identifier($id);
        }
        $this->_oaiIdentifierArray = $identifiers;

        /*
         * Task in human-readable format
         */
        $msg = [];

        if (count($this->_identifierArray)) {
            $msg[] = 'identifier in ('
                .implode(', ', $this->_identifierArray)
                .')';
        }

        if (isset($this->_from) || isset($this->_to)) {
            $s1 = (isset($this->_from) ? $this->_from : '-inf');
            $s2 = (isset($this->_to) ? $this->_to : '+inf');
            $msg[] = "datestamp in [{$s1}, {$s2}]";
        }

        if (count($this->_setSpecArray)) {
            $msg[] = 'set in ('
                .implode(', ', $this->_setSpecArray)
                .')';
        }

        if (count($this->_metadataPrefixArray)) {
            $msg[] = 'metadataPrefix in ('
                .implode(', ', $this->_metadataPrefixArray)
                .')';
        }

        $task = implode(', ', $msg);

        $this->_backend->beginUpdate($task);

        try {
            /*
             * Checks
             */

            if (!count($this->_metadataPrefixArray)) {
                throw new Exception('No metadata prefix specified');
            }

            foreach ($this->_metadataPrefixArray as $metadataPrefix) {
                if (!$this->_backend->metadataPrefixExists($metadataPrefix)) {
                    throw new Exception("Unknown metadata prefix: $metadataPrefix");
                }
            }

            foreach ($this->_setSpecArray as $setSpec) {
                if (!$this->_backend->setSpecExists($setSpec)) {
                    throw new Exception("Unknown set: $setSpec");
                }
            }

            $this->_update();

            $this->_backend->endUpdate();
        } catch (Exception $e) {
            $this->_backend->endUpdate($e);
        }
    }

    /**
     * Parse a date parameter. Accepted values are of the form yyyy, yyyy-mm,
     * yyyy-mm-dd, or relative dates as, e.g., -2Y, -1M, -6D.
     *
     * FIXME: handle syntax errors
     *
     * @param string $param string to parse
     * @param string $type  type of date limit: 'from' or 'to'
     *
     * @return string date in yyy-mm-dd format
     */
    private static function _parseDateParameter($param, $type)
    {
        $year = 0;
        $month = 0;
        $day = 0;

        /*
         * Retrieve date parts, as value or as offset
         */

        /* -1Y */
        if (preg_match("/^-(\d+)Y$/", $param, $m)) {
            $year = 0 - $m[1];

        /* -1M */
        } elseif (preg_match("/^-(\d+)M$/", $param, $m)) {
            $month = 0 - $m[1];

        /* -1D */
        } elseif (preg_match("/^-(\d+)D$/", $param, $m)) {
            $day = 0 - $m[1];
        } else {
            /* 2012 */
            if (preg_match("/^(\d{4})/", $param, $m)) {
                $year = 0 + $m[1];
            }

            /* 2012-01 */
            if (preg_match("/^(\d{4})-(\d{2})/", $param, $m)) {
                $month = 0 + $m[2];
            }

            /* 2012-01-30 */
            if (preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $param, $m)) {
                $day = 0 + $m[3];
            }
        }

        /*
         * Build the fully specified date point (yyyy-mm-dd).
         */

        $oDate = new DateTime();

        if (($year < 0) || ($month < 0) || ($day < 0)) {
            if ($year < 0) {
                $oDate->modify($year.' year');
            }
            if ($month < 0) {
                $oDate->modify($month.' month');
            }
            if ($day < 0) {
                $oDate->modify($day.' day');
            }
        } elseif (($year > 0) || ($month > 0) || ($day > 0)) {
            if (0 == $month) {
                $month = ('from' == $type ? 1 : 12);
            }
            if (0 == $day) {
                $day = ('from' == $type ? 1 : 31);	/* FIXME */
            }
            $oDate->setDate($year, $month, $day);
        }

        return $oDate->format('Y-m-d');
    }

    /**
     * Update OAI cached data, using parameters stored in an array.
     *
     * Parameters usually come from $_GET or $_POST. No parameter are mandatory.
     * Default values are such that an URL without parameters does nothing.
     *
     * 'set'            : list of sets or 'all'
     * 'metadataPrefix' : list of prefixes or 'all'
     * 'from'           : lowest datestamp to consider
     * 'to'             : highest datestamp to consider
     *
     * See _parseDateParameter() for details on 'from' and 'to' parameters.
     *
     * @param array $P parameters (as strings)
     */
    public function parseAndRun($P)
    {
        /*
         * Analyze parameters
         */
        $setArray = [];
        if (isset($P['set'])) {
            $setArray = explode(',', $P['set']);
        }

        $metadataPrefixArray = [];
        if (isset($P['metadataPrefix'])) {
            $metadataPrefixArray = explode(',', $P['metadataPrefix']);
        }

        $identifierArray = [];
        if (isset($P['identifier'])) {
            $identifierArray = explode(',', $P['identifier']);
        }

        $from = null;
        if (isset($P['from'])) {
            $from = self::_parseDateParameter($P['from'], 'from');
        }

        $to = null;
        if (isset($P['to'])) {
            $to = self::_parseDateParameter($P['to'], 'to');
        }

        /*
         * Launch the update process
         */
        $this->run(
            $setArray,
            $metadataPrefixArray,
            $identifierArray,
            $from,
            $to
        );
    }
}
