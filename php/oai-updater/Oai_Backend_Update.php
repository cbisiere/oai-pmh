<?php

/**
 * OAI database backend (with write access).
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * OAI backend.
 *
 * This class is used to access to the OAI backend database in write mode.
 */
class Oai_Backend_Update extends Oai_Backend
{
    /** @var bool Should history of metadata and set membership be maintained */
    public $_save_history = false;

    /**
     * Logged info.
     *
     * @var int    Unique identifier for the current update run record
     *             (oai_update_log.id)
     * @var string Status of the current update (committed, roll backed...)
     *             (oai_update_log.status)
     * @var string Warnings for the current update
     *             (oai_update_log.warning)
     * @var int    Number of metadata record deleted during the current update
     *             (oai_update_log.meta_deleted)
     * @var int    Number of metadata record inserted during the current update
     *             (oai_update_log.meta_inserted)
     * @var int    Number of metadata record modified during the current update
     *             (oai_update_log.meta_touched)
     * @var int    Number of set record deleted during the current update
     *             (oai_update_log.set_deleted)
     * @var int    Number of set record inserted during the current update
     *             (oai_update_log.set_inserted)
     */
    private $_update_serial_number;
    private $_update_status;
    private $_update_warning;
    private $_update_meta_deleted = 0;
    private $_update_meta_inserted = 0;
    private $_update_meta_touched = 0;
    private $_update_set_deleted = 0;
    private $_update_set_inserted = 0;

    /**
     * Return true if an error code is for duplicate entry.
     *
     * See:
     * https://dev.mysql.com/doc/mysql-errors/5.7/en/server-error-reference.html
     *
     * @param int $errno error code
     *
     * @return bool true if the error code means duplicate entry
     */
    public static function errIsDuplicate($errno)
    {
        return (1062 == $errno) || (1586 == $errno);
    }

    /*
     * Table oai_update_log: trace and accounting
     */

    /**
     * Begin an update task: create a new update record, and return its
     * record id.
     *
     * @param string $task description of the task
     *
     * @return int
     *
     * @throws Exception when the record cannot be created
     */
    private function _newUpdateLogRecord($task)
    {
        $query = 'INSERT INTO oai_update_log'
            .' (repo, task, date_start) VALUES(?,?,?)';
        $this->query(
            $query,
            $this->_repo,
            $task,
            $this->getRunDate()
        );

        $id = $this->_connection->lastInsertId('id');
        if (0 === $id) {
            throw new Exception('Cannot get inserted id from oai_update_log');
        }

        return $id;
    }

    /**
     * End an update task: update the task record.
     *
     * @param Exception $e exception (if any) that terminate the update process
     */
    private function _setUpdateLogRecord($e = null)
    {
        $let[] = 'date_end = NOW()';

        $let[] = 'status = ?';
        $arg[] = $this->_update_status;

        $let[] = 'error = ?';
        $arg[] = (isset($e) ? 1 : 0);

        $let[] = 'warning = ?';
        $arg[] = $this->_update_warning;

        $let[] = 'errno = ?';
        $arg[] = (isset($e) ? $e->getCode() : null);

        $let[] = 'errmsg = ?';
        $arg[] = (isset($e) ? $e->getMessage() : null);

        $let[] = 'meta_deleted = ?';
        $arg[] = $this->_update_meta_deleted;

        $let[] = 'meta_inserted = ?';
        $arg[] = $this->_update_meta_inserted;

        $let[] = 'meta_touched = ?';
        $arg[] = $this->_update_meta_touched;

        $let[] = 'set_deleted = ?';
        $arg[] = $this->_update_set_deleted;

        $let[] = 'set_inserted = ?';
        $arg[] = $this->_update_set_inserted;

        $set = implode(', ', $let);

        $query = "UPDATE oai_update_log SET $set WHERE id = ?";
        $arg[] = $this->_update_serial_number;

        $this->query($query, $arg);
    }

    /*
     * Start / end an update process
     */

    /**
     * Initiate an update of the database.
     *
     * Before starting to update metadata, we lock the tables,
     * and create a new update record.
     *
     * @param string $task description of the task
     */
    public function beginUpdate($task)
    {
        /* create an update record and store its id for later use in endUpdate() */
        $this->_update_serial_number = $this->_newUpdateLogRecord($task);

        /* start a transaction */
        $this->_connection->beginTransaction();
    }

    /**
     * Terminate an update of the database.
     *
     * @param Exception $e exception (if any) that terminate the update process
     */
    public function endUpdate($e = null)
    {
        if (isset($e)) {
            $this->_update_status = ($this->_connection->rollBack() ? 'rolled back' : 'rollback failed');
        } else {
            $this->_update_status = ($this->_connection->commit() ? 'committed' : 'commit failed');
        }

        $this->_setUpdateLogRecord($e);
    }

    /*
     * Methods used to update the repository
     *
     */

    /**
     * Confirm a set membership records.
     *
     * This flag is used to track entries and exits of setSpecs. Resetting this
     * flag must be done once, before the metadata update process.
     *
     * A flag of 1 or greater means that the set membership is confirmed so far.
     *
     * @param array $metadataPrefixArray array of metadataprefixes
     * @param array $setArray            array of setspecs
     * @param array $identifierArray     array of oai identifiers
     */
    public function setSpecInitConfirm($metadataPrefixArray, $setArray, $identifierArray)
    {
        $and[] = 'repo = ?';
        $and[] = 'history = 0';

        $and[] = self::inClause('metadataPrefix', $metadataPrefixArray);
        $and[] = self::inClause('setSpec', $setArray);

        if (count($identifierArray)) {
            $and[] = self::inClause('Identifier', $identifierArray);
        }

        $where = implode(' AND ', $and);

        $query = "UPDATE oai_item_set SET confirmed = 1 WHERE $where";

        $this->query(
            $query,
            $this->_repo,
            $metadataPrefixArray,
            $setArray,
            $identifierArray
        );
    }

    /**
     * Note that a set membership has yet to be confirmed.
     *
     * The field 'confirmed' is used to track entries and exits of setSpecs.
     * A value of 0 means that the set membership has yet to be confirmed to be
     * considered as currently valid.
     *
     * @param string $identifier          target identifier
     * @param array  $metadataPrefixArray array of metadataprefixes
     * @param array  $setArray            array of setspecs
     */
    public function setSpecToBeConfirmed(
        $identifier,
        $metadataPrefixArray,
        $setArray
    ) {
        $and[] = 'repo = ?';
        $and[] = 'history = 0';

        $and[] = 'Identifier = ?';

        $and[] = self::inClause('metadataPrefix', $metadataPrefixArray);
        $and[] = self::inClause('setSpec', $setArray);

        $where = implode(' AND ', $and);

        $query = "UPDATE oai_item_set SET confirmed = 0 WHERE $where";

        $this->query(
            $query,
            $this->_repo,
            $identifier,
            $metadataPrefixArray,
            $setArray
        );
    }

    /**
     * Return true if a set membership for an identifier can be confirmed.
     *
     * The confirmation is a success if the setSpec record exists.
     *
     * Use of a flag rather than a counter is needed to detect that such record
     * exists or not.
     *
     * @param string $identifier     identifier of the setspec record to update
     * @param string $metadataPrefix metadataprefix
     * @param string $setSpec        setspec of the setspec info record
     *
     * @return bool true if the confirmation succeeds
     */
    public function setSpecConfirmOk(
        $identifier,
        $metadataPrefix,
        $setSpec
    ) {
        $query = 'UPDATE oai_item_set'
            .' SET confirmed = confirmed + 1'
            .' WHERE repo = ?'
            .' AND history = 0'
            .' AND identifier = ?'
            .' AND metadataPrefix = ?'
            .' AND setSpec = ?';
        $res = $this->query(
            $query,
            $this->_repo,
            $identifier,
            $metadataPrefix,
            $setSpec
        );

        return $res->rowCount() > 0;
    }

    /**
     * Create a confirmed set membership record for an identifier.
     *
     * @param string $identifier     identifier of the setspec record to update
     * @param string $metadataPrefix metadataprefix
     * @param string $setSpec        setspec of the setspec info record
     */
    public function setSpecCreate(
        $identifier,
        $metadataPrefix,
        $setSpec
    ) {
        $query = 'INSERT oai_item_set'
            .' (repo, history, serial, identifier, metadataPrefix, setSpec,'
            .' confirmed, created)'
            .' VALUES (?, 0, ?, ?, ?, ?, 1, ?)';
        $res = $this->query(
            $query,
            $this->_repo,
            $this->_update_serial_number,
            $identifier,
            $metadataPrefix,
            $setSpec,
            $this->getRunDate()
        );

        /* Accounting */
        $this->_update_set_inserted += $res->rowCount();
    }

    /**
     * Delete unconfirmed set records.
     *
     * SetSpec records which have not been confirmed during the set membership
     * scan process correspond to metadata records exiting from some of their set.
     *
     * If _save_history is true, we set the 'history' flag of these SetSpec
     * instead of deleting them. Maintaining such flags is not required for OAI-PMH
     * compliance, but allows to keep track of set membership events.
     *
     * @param array $metadataPrefixArray array of metadata prefixes
     * @param array $setArray            array of setspecs
     * @param array $identifierArray     array of oai identifiers
     */
    public function setSpecDeleteUnconfirmed($metadataPrefixArray, $setArray, $identifierArray)
    {
        $and[] = 'repo = ?';
        $and[] = 'history = 0';
        $and[] = 'confirmed = 0';
        $and[] = self::inClause('metadataPrefix', $metadataPrefixArray);
        $and[] = self::inClause('setSpec', $setArray);

        if (count($identifierArray)) {
            $and[] = self::inClause('Identifier', $identifierArray);
        }

        $where = implode(' AND ', $and);

        if ($this->_save_history) {
            $query = "UPDATE oai_item_set SET history = 1 WHERE $where";
        } else {
            $query = "DELETE FROM oai_item_set WHERE $where";
        }

        $res = $this->query(
            $query,
            $this->_repo,
            $metadataPrefixArray,
            $setArray,
            $identifierArray
        );

        /* Accounting */
        $this->_update_set_deleted += $res->rowCount();
    }

    /**
     * Update the OAI 'datestamp' field of non-deleted metadata records that
     * experienced a change of setSpec. As a result, these records will be
     * shown as recently modified by OAI-PMH.
     *
     * OAI-PMH spec says that the datestamp of a deleted metadata record is the
     * date at which the record was deleted. Thus, we must not touch those
     * records when they experience a change of setSpec.
     *
     * When the set membership scan process is over, changes of setSpec, that is,
     * entries and exits, are detected as follows:
     *
     * - a new setSpec record has been created (entry)
     * - a setSpec record has not been confirmed (exit)
     *
     * @param array $metadataPrefixArray array of metadata prefixes
     * @param array $setArray            array of setspecs
     * @param array $identifierArray     array of oai identifiers
     */
    public function setSpecTouchMeta($metadataPrefixArray, $setArray, $identifierArray)
    {
        $and[] = 'repo = ?';
        $and[] = 'history = 0';
        $and[] = self::inClause('metadataPrefix', $metadataPrefixArray);
        $and[] = self::inClause('setSpec', $setArray);

        if (count($identifierArray)) {
            $and[] = self::inClause('Identifier', $identifierArray);
        }
        $and[] = '(serial = ? OR confirmed = 0)';

        $where = implode(' AND ', $and);

        $query = 'UPDATE oai_item_meta JOIN ('
            .'SELECT repo, identifier, metadataPrefix FROM oai_item_set'
            ." WHERE $where"
            .') AS oai_item_set'
            .' ON'
            .' oai_item_meta.repo = oai_item_set.repo'
            .' AND oai_item_meta.identifier = oai_item_set.identifier'
            .' AND oai_item_meta.metadataPrefix = oai_item_set.metadataPrefix'
            .' SET datestamp = ?'
            .' WHERE history = 0'
            .' AND deleted = 0';

        $res = $this->query(
            $query,
            $this->_repo,
            $metadataPrefixArray,
            $setArray,
            $identifierArray,
            $this->_update_serial_number,
            $this->getRunDate()
        );

        /* Accounting */
        $this->_update_meta_touched += $res->rowCount();
    }

    /**
     * Delete a metadata record, as well as associated 'about' data if any.
     *
     * Note: SQL triggers ensure 'about' data are properly deleted.
     *
     * @param string $identifier     identifier of the item to look for
     * @param string $metadataPrefix metadataprefix
     */
    public function metadataDelete($identifier, $metadataPrefix)
    {
        if ($this->_save_history) {
            /*
            *  FIXME: in some rare cases, we end up with two records with
            *  the same serial number, one with history=0, one with history=1.
            */
            $query = 'UPDATE oai_item_meta'
                .' SET history = 1'
                .' WHERE repo = ?'
                .' AND history = 0'
                .' AND identifier = ?'
                .' AND metadataPrefix = ?';
        } else {
            $query = 'DELETE FROM oai_item_meta'
                .' WHERE repo = ?'
                .' AND history = 0'
                .' AND identifier = ?'
                .' AND metadataPrefix = ?';
        }

        $this->query(
            $query,
            $this->_repo,
            $identifier,
            $metadataPrefix
        );
    }

    /**
     * Test if a metadata record exists and is up-to-date relative to the
     * source object it represents.
     *
     * @param string $identifier     identifier of the item to look for
     * @param string $metadataPrefix metadataprefix
     * @param string $datestamp      datestamp of the source object
     *
     * @return bool true if the metadata exists and is up to date
     */
    public function metadataIsUpToDate($identifier, $metadataPrefix, $datestamp)
    {
        $query = 'SELECT COUNT(*) FROM oai_item_meta'
            .' WHERE repo = ?'
            .' AND history = 0'
            .' AND identifier = ?'
            .' AND metadataPrefix = ?'
            .' AND datestamp >= ?';
        $result = $this->query(
            $query,
            $this->_repo,
            $identifier,
            $metadataPrefix,
            $datestamp
        );

        return 1 == $result->fetchColumn();
    }

    /**
     * Return true if a metadata record match a metadata and a delete status.
     *
     * The aim of this test is to discard spurious changes, when the datestamp
     * of source objects are touched without any impact on metadata. This may
     * well happen, since updates of source object know nothing about which
     * pieces of information are embedded in metadata. Besides, there are different
     * metadata formats. This cannot be perfect, though. For some formats,
     * e.g. DIDL-MODS, the source datestamp is part of the metadata itself.
     *
     * @param string $identifier     OAI identifier
     * @param string $metadataPrefix metadataprefix
     * @param bool   $deleted        delete status
     * @param string $metadata       metadata as a string, or NULL if deleted
     */
    public function metadataMatch(
        $identifier,
        $metadataPrefix,
        $deleted,
        $metadata
    ) {
        $query = 'SELECT COUNT(*) FROM oai_item_meta'
            .' WHERE repo = ?'
            .' AND history = 0'
            .' AND identifier = ?'
            .' AND metadataPrefix = ?'
            .' AND deleted = ?';

        $arg = [
            $this->_repo,
            $identifier,
            $metadataPrefix,
            $deleted ? 1 : 0,
        ];

        if (!$deleted) {
            $query .= ' AND metadata = ?';
            $arg[] = $metadata;
        }

        $res = $this->query($query, $arg);

        return $res->fetchColumn() > 0;
    }

    /**
     * Create a 'about' record.
     *
     * @param string $identifier     OAI identifier
     * @param string $metadataPrefix metadataprefix
     * @param string $datestamp      datestamp of the source object
     * @param string $about          about data as string
     * @param int    $rank           rank within the same metadata record
     */
    public function aboutCreate(
        $identifier,
        $metadataPrefix,
        $datestamp,
        $about,
        $rank
    ) {
        $query = 'INSERT oai_item_meta_about'
        .' (`repo`,`serial`,`identifier`,`metadataPrefix`,`datestamp`,'
        .' `about`,`rank`,`created`)'
        .' VALUES'
        .'(?,?,?,?,?,?,?,?)';

        $this->query($query,
            $this->_repo,
            $this->_update_serial_number,
            $identifier,
            $metadataPrefix,
            $this->getRunDate(),
            $about,
            $rank,
            $this->getRunDate()
        );
    }

    /**
     * Create a metadata record.
     *
     * @param string $identifier     OAI identifier
     * @param string $metadataPrefix metadataprefix
     * @param string $datestamp      datestamp of the source object
     * @param bool   $deleted        delete status
     * @param string $metadata       metadata as string, or null if deleted
     */
    public function metadataCreate(
        $identifier,
        $metadataPrefix,
        $datestamp,
        $deleted,
        $metadata
    ) {
        $var = [
            'repo',
            'serial',
            'identifier',
            'metadataPrefix',
            'datestamp',
            'deleted',
            'created',
        ];
        $fmt = ['?', '?', '?', '?', '?', '?', '?'];

        $arg = [
            $this->_repo,
            $this->_update_serial_number,
            $identifier,
            $metadataPrefix,
            $this->getRunDate(),
            $deleted ? 1 : 0,
            $this->getRunDate(),
        ];

        if (!$deleted) {
            $var[] = 'metadata';
            $fmt[] = '?';
            $arg[] = $metadata;
        }

        $vars = implode(', ', $var);
        $fmts = implode(', ', $fmt);

        $query = "INSERT oai_item_meta ($vars) VALUES ($fmts)";
        $res = $this->query($query, $arg);

        /* Accounting */
        if ($deleted) {
            $this->_update_meta_deleted += $res->rowCount();
        } else {
            $this->_update_meta_inserted += $res->rowCount();
        }
    }
}
