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
 * OAI database backend (read access only).
 */
class Oai_Backend
{
    /** @var Oai_Connection Connection to the database */
    protected $_connection;
    /** @var string Id of the repository (oai_repo.id) */
    protected $_repo;

    /**
     * Constructor.
     *
     * @param Oai_Connection $connection database connection to the repo
     * @param string         $repo       repo id
     */
    public function __construct(
        Oai_Connection $connection,
        $repo
    ) {
        $this->_connection = $connection;
        $this->_repo = $repo;
    }

    /**
     * Return the date at which the backend object was created.
     *
     * @return int
     */
    public function getRunDate()
    {
        return $this->_connection->getDate();
    }

    /*
     * Generic database helpers
     */

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
        return Oai_Connection::inClause($field, $values);
    }

    /**
     * Submit a SQL query.
     *
     * @return PDOStatement result resource
     */
    public function query()
    {
        return $this->_connection->execQuery(func_get_args());
    }

    /*
     * Methods used by the repository to respond to OAI queries
     *
     */

    /**
     * Select supported setspecs a metadata record belongs to.
     *
     * Setspecs are returned unordered.
     *
     * @param string $identifier     identifier of the item to look for
     * @param string $metadataPrefix metadataprefix
     *
     * @return PDOStatement resource result
     */
    public function setSelect($identifier, $metadataPrefix)
    {
        $query = 'SELECT DISTINCT oai_item_set.setSpec'
            .' FROM oai_item_set JOIN oai_set'
            .' ON oai_item_set.repo = oai_set.repo'
            .' AND oai_item_set.setSpec = oai_set.setSpec'
            .' WHERE oai_item_set.repo = ?'
            .' AND oai_item_set.history = 0'
            .' AND oai_item_set.identifier = ?'
            .' AND oai_item_set.metadataPrefix = ?';
        $result = $this->query(
            $query,
            $this->_repo,
            $identifier,
            $metadataPrefix
        );

        return $result;
    }

    /**
     * Select records in the metadata table.
     *
     * Parameters specify the selection criterium. Each criteria may be omitted.
     *
     * 2011-01-20: add "noDeleted" to enforce section "2.5.1 Deleted records"
     * of the specs, that is, a repository that indicates a level of support of
     * 'no' must not reveal a deleted status in any response.
     *
     * 2012-02-21: optimise the query depending on which pieces are
     * predetermined. The resulting query executes faster, which is surprising,
     * as these optimisations are rather obvious.
     *
     * @param bool     $count          do not select columns, just count(*)
     * @param bool     $noDeleted      exclude deleted records
     * @param string   $identifier     identifier
     * @param string   $metadataPrefix metadataprefix
     * @param string   $set            setspec or beginning of setspec
     * @param Oai_Date $oFrom          modified from
     * @param Oai_Date $oUntil         modified until
     * @param int      $index          first index of the record in the result
     * @param int      $limit          maximum number of records in the result
     * @param bool     $withMetadata   also return metadata
     *
     * @return PDOStatement result resource
     */
    public function metadataSelect(
        $count = false,
        $noDeleted = false,
        $identifier = false,
        $metadataPrefix = false,
        $set = false,
        ?Oai_Date $oFrom = null,
        ?Oai_Date $oUntil = null,
        $index = null,
        $limit = null,
        $withMetadata = false
    ) {
        $and = [];
        $arg = [];

        /* FIXME: check DISTINCT */
        if ($count) {
            $what = 'COUNT(DISTINCT oai_item_meta.identifier, oai_item_meta.metadataPrefix)';
        } else {
            $ret = [
                'oai_item_meta.identifier',
                'oai_item_meta.metadataPrefix',
                'datestamp', /* MAX() & GROUP BY */
                'deleted',
            ];
            if ($withMetadata) {
                $ret[] = 'metadata';
            }

            $what = implode(', ', $ret);
        }

        $query = "SELECT $what FROM oai_item_meta";

        /*
         * set membership: inner join with (identifier, metadataPrefix) in the set
         */
        if (false !== $set) {
            $and = [];

            $query .= ' JOIN (SELECT DISTINCT identifier, metadataPrefix FROM oai_item_set';

            $and[] = 'repo = ?';
            $arg[] = $this->_repo;

            $and[] = 'history = 0';

            if (false !== $identifier) {
                $and[] = 'identifier = ?';
                $arg[] = $identifier;
            }

            if (false !== $metadataPrefix) {
                $and[] = 'metadataPrefix = ?';
                $arg[] = $metadataPrefix;
            }

            /*
             * 2011-03-29: hierarchical sets
             */
            $and[] = "LEFT(CONCAT(setSpec,':'), CHAR_LENGTH(?)+1) = CONCAT(?,':')";
            $arg[] = $set;
            $arg[] = $set;

            $query .= ' WHERE '.implode(' AND ', $and);

            /*
             * Added 2011-06-04: sorting is not mandatory, but helpful to compare
             * production and test repositories.
             */
            $query .= ' ORDER BY identifier';

            $query .= ') AS oai_item_set';

            if (false === $identifier || false === $metadataPrefix) {
                $and = [];

                if (false === $identifier) {
                    $and[] = 'oai_item_set.identifier = oai_item_meta.identifier';
                }
                if (false === $metadataPrefix) {
                    $and[] = 'oai_item_set.metadataPrefix = oai_item_meta.metadataPrefix';
                }

                $query .= ' ON '.implode(' AND ', $and);
            }
        }

        $and = [];

        $and[] = 'repo = ?';
        $arg[] = $this->_repo;

        $and[] = 'history = 0';

        if (false !== $identifier) {
            $and[] = 'oai_item_meta.identifier = ?';
            $arg[] = $identifier;
        }

        if (false !== $metadataPrefix) {
            $and[] = 'oai_item_meta.metadataPrefix = ?';
            $arg[] = $metadataPrefix;
        }

        /* Time interval handling. */

        if (isset($oFrom)) {
            $and[] = 'datestamp >= ?';
            $arg[] = $oFrom->toString(Oai_Const::FORMAT_DATETIME);
        }

        if (isset($oUntil)) {
            $and[] = 'datestamp <= ?';
            $arg[] = $oUntil->toString(Oai_Const::FORMAT_DATETIME);
        }

        /* Exclude deleted? */

        if ($noDeleted) {
            $and[] = 'deleted = 0';
        }

        $query .= ' WHERE '.implode(' AND ', $and);

        if (!$count) {
            /*
        		 * Added 2011-06-04: sorting is not mandatory, but helful to compare
        		 * production and test repositories.
        		 *
        		 */
            $query .= ' ORDER BY identifier';

            if (isset($index)) {
                $query .= " LIMIT $index";
                if (isset($limit)) {
                    $query .= ", $limit";
                }
            }
        }

        return $this->query($query, $arg);
    }

    /**
     * Test if an identifier exists.
     *
     * An identifier exists if it has (possibly deleted) metadata.
     *
     * @param bool   $noDeleted  exclude deleted records
     * @param string $identifier the identifier to look for
     *
     * @return bool true if the identifier exists
     */
    public function identifierExists($noDeleted, $identifier)
    {
        $result = $this->metadataSelect(true, $noDeleted, $identifier);

        return $result->fetchColumn() > 0;
    }

    /**
     * Test if a (possibly deleted) metadata record exists for a given identifier.
     *
     * @param bool   $noDeleted      exclude deleted records
     * @param string $identifier     identifier to look for
     * @param string $metadataPrefix metadataprefix
     *
     * @return bool true if the record exist
     */
    public function recordExists($noDeleted, $identifier, $metadataPrefix)
    {
        $result = $this->metadataSelect(true, $noDeleted, $identifier, $metadataPrefix);

        return $result->fetchColumn() > 0;
    }

    /**
     * Test if a metadataprefix is supported.
     *
     * @param string $metadataPrefix metadataprefix to test for
     *
     * @return true if the metadataprefix is supported
     */
    public function metadataPrefixExists($metadataPrefix)
    {
        $query = 'SELECT COUNT(*) FROM oai_meta'
            .' WHERE repo = ? AND metadataPrefix = ?';

        $result = $this->query(
            $query,
            [
                $this->_repo,
                $metadataPrefix,
            ]
        );

        return $result->fetchColumn() > 0;
    }

    /**
     * Test if a setSpec exists.
     *
     * @param string $setSpec setspec to test for
     *
     * @return true if the setspec is supported
     */
    public function setSpecExists($setSpec)
    {
        $query = 'SELECT COUNT(*) FROM oai_set'
            .'  WHERE repo = ? AND setSpec = ?';

        $result = $this->query(
            $query,
            $this->_repo,
            $setSpec
        );

        return $result->fetchColumn() > 0;
    }

    /** Select repository identity data.
     *
     * This method should return a single record.
     *
     * @return PDOStatement resource result
     */
    public function repoSelect()
    {
        $query = 'SELECT * FROM oai_repo WHERE id = ?';

        return $this->query($query, $this->_repo);
    }

    /** Select repository description records, if any.
     *
     * @return PDOStatement resource result
     */
    public function repoDescriptionSelect()
    {
        $query = 'SELECT * FROM oai_repo_description WHERE repo = ? ORDER BY `rank`';

        return $this->query($query, $this->_repo);
    }

    /**
     * Select 'about' data about a metadata record.
     *
     * About records are returned ordered by rank. Not part of the spec.
     *
     * @param string $identifier     identifier of the item to look for
     * @param string $metadataPrefix metadataprefix
     *
     * @return PDOStatement resource result
     */
    public function aboutSelect($identifier, $metadataPrefix)
    {
        $query = 'SELECT DISTINCT about'
            .' FROM oai_item_meta_about'
            .' WHERE repo = ?'
            .' AND history = 0'
            .' AND identifier = ?'
            .' AND metadataPrefix = ?'
            .' ORDER BY `rank`';
        $result = $this->query(
            $query,
            $this->_repo,
            $identifier,
            $metadataPrefix
        );

        return $result;
    }

    /**
     * Select supported metadataPrefix available for an identifier.
     *
     * MetadataPrefix are alphabetically ordered. This is not part of the specs,
     * though.
     *
     * @param string $identifier identifier
     *
     * @return PDOStatement resource result
     */
    public function metadataPrefixSelect($identifier = false)
    {
        if (false === $identifier) {
            $query = 'SELECT DISTINCT metadataPrefix, `schema`, metadataNamespace'
                .' FROM oai_meta WHERE repo = ? ORDER BY metadataPrefix';
            $arg[] = $this->_repo;
        } else {
            $query = 'SELECT DISTINCT oai_item_meta.metadataPrefix,'
                .' oai_meta.metadataNamespace, oai_meta.`schema`'
                .' FROM oai_item_meta JOIN oai_meta'
                .' ON oai_item_meta.repo = oai_meta.repo'
                .' AND oai_item_meta.metadataPrefix = oai_meta.metadataPrefix'
                .' WHERE oai_item_meta.repo = ?'
                .' AND oai_item_meta.history = 0'
                .' AND oai_item_meta.identifier = ?'
                .' ORDER BY oai_item_meta.metadataPrefix';

            $arg[] = $this->_repo;
            $arg[] = $identifier;
        }

        return $this->query($query, $arg);
    }

    /**
     * Return all the supported metadata prefixes in an array.
     *
     * @return string[] Metadata prefixes
     */
    public function metadataPrefixArray()
    {
        $all = [];
        $result = $this->metadataPrefixSelect();

        foreach ($result as $f) {
            $all[] = $f['metadataPrefix'];
        }

        return $all;
    }

    /**
     * Select setSpec information.
     *
     * @param bool $count do not select columns, just count(*)
     * @param int  $index start index in the result, or maximum
     * @param int  $limit maximum number of records in the result
     *
     * @return PDOStatement result resource
     */
    public function setSpecSelect($count = false, $index = null, $limit = null)
    {
        $what = ($count ? 'COUNT(*)' : '*');

        $query = "SELECT {$what} FROM oai_set WHERE repo = ?";

        if (!$count) {
            $query .= ' ORDER BY `rank`, setName';
        }

        if (isset($index)) {
            $query .= " LIMIT $index";
            if (isset($limit)) {
                $query .= ", $limit";
            }
        }

        return $this->query($query, $this->_repo);
    }

    /**
     * Select setDescription information.
     *
     * @param string $setSpec setspec for which we request the descriptions
     *
     * @return PDOStatement result resource
     */
    public function setDescriptionSelect($setSpec)
    {
        $query = 'SELECT * FROM oai_set_description'
            .' WHERE repo = ? AND setSpec = ? ORDER BY `rank`';

        return $this->query($query, $this->_repo, $setSpec);
    }

    /**
     * Return all the supported setSpecs in an array.
     *
     * @return string[] Supported setSpecs
     */
    public function setSpecArray()
    {
        $all = [];
        $result = $this->setSpecSelect();

        foreach ($result as $f) {
            $all[] = $f['setSpec'];
        }

        return $all;
    }
}
