<?php

/**
 * Demo repository updater.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Class to update the demo repository.
 *
 * This class expects to find a xml document in a specific location (cf. class
 * constants) containing a modsCollection of all the metadata that must be
 * available from the OAI repository.
 *
 * An easy way to create this document would be to export all the target bibtex
 * references into a single text file, and use bib2xml to transform this file into
 * a modsCollection, as follows:
 *
 * bib2xml -i utf8 demo_data.bib > demo_data.xml
 *
 * This class throws exceptions when the backend cannot be initialized, the updating
 * process cannot start or terminate nicely, the source xml document does not exist
 * or does not contain what we expect.
 */
class Oai_DemoUpdater extends Oai_XmlUpdater
{
    /*
     * Metadata prefixes
     */

    public const OAI_METADATA_PREFIX_MODS = 'mods';
    public const OAI_METADATA_PREFIX_OAI_DC = 'oai_dc';

    /*
     * Data source
     */

    public const XML_SOURCE_FILENAME = '../install/demo_data.xml';

    /**
     * Build an OAI identifier from a local identifier.
     *
     * A local identifier is the primary key in our database.
     *
     * @param mixed $id identifier in the source database
     *
     * @return string OAI identifier in the repository
     */
    protected function identifier($id)
    {
        return 'oai:demo.org:'.$id;
    }

    /**
     * Return the primary key of a source object.
     *
     * A source object is an XML 'mods' element, whose root
     * has an attribute 'ID'.
     *
     * @param mixed $f the source object
     *
     * @return mixed identifier in the source database
     */
    protected function id($f)
    {
        $attrs = $f->getAttributes();

        return $attrs['ID'];
    }

    /**
     * Return the datestamp of a source object. The datestamp is the modification
     * date of the source object, including modifications that may change its
     * set memberships.
     *
     * Since source data does not provide such information, we set it to the
     * start date of the update run.
     *
     * @param mixed $f the source object
     *
     * @return string datestamp in database native format
     */
    protected function datestamp($f)
    {
        return $this->_backend->getRunDate();
    }

    /**
     * Return true if a source object is deleted.
     *
     * Since source data does not provide such information,
     * all source objects are considered to be _not_ deleted.
     *
     * @param mixed $f the source object
     *
     * @return bool true if the source object is deleted, false otherwise
     */
    protected function deleted($f)
    {
        return false;
    }

    /**
     * Build metadata for a source object, as an XML element.
     *
     * A source object is an XML 'mods' element, without
     * schema location attributes and related namespaces.
     *
     * @param mixed  $f              the source object
     * @param string $metadataPrefix requested metadata
     *
     * @return Meta requested metadata, or null if no such metadata are
     *              available for this source object
     */
    protected function xmlMetadata($f, $metadataPrefix)
    {
        switch ($metadataPrefix) {
            case self::OAI_METADATA_PREFIX_OAI_DC:
                /* Create MODS metadata and transform to DC */

                $oMetaMods = new Meta_MODS(true, false);
                $oMetaMods->appendChildren($f);

                $oMeta = new Meta_DC();
                $oMeta->xslMods($oMetaMods);
                break;

            case self::OAI_METADATA_PREFIX_MODS:
                $oMeta = new Meta_MODS(true, false);
                $oMeta->appendChildren($f);
                break;

            default:
                return null;
        }

        $xml = $oMeta->getXml();

        return $xml;
    }

    /**
     * Return 'about' data for a given record, as an array of strings, or false.
     *
     * @param mixed  $f              the source object
     * @param string $metadataPrefix requested metadata
     *
     * @return mixed array of strings, each one containing valid xml data, or
     *               false if the record has no 'about' data
     */
    protected function about($f, $metadataPrefix)
    {
        $origin = 'another.demo.org';

        $about = [];

        /* provenance */
        if (self::OAI_METADATA_PREFIX_OAI_DC == $metadataPrefix
            || self::OAI_METADATA_PREFIX_MODS == $metadataPrefix) {
            $identifier = 'oai:'.$origin.':'.$this->id($f);

            if (self::OAI_METADATA_PREFIX_OAI_DC == $metadataPrefix) {
                $namespace = 'http://www.openarchives.org/OAI/2.0/oai_dc/';
            } else {
                $namespace = 'http://www.loc.gov/mods/v3';
            }
            $about[] = <<<EOT
<?xml version="1.0"?>
<provenance xmlns="http://www.openarchives.org/OAI/2.0/provenance" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/provenance http://www.openarchives.org/OAI/2.0/provenance.xsd">
    <originDescription harvestDate="2024-07-25T14:10:02Z" altered="true">
        <baseURL>http://{$origin}</baseURL>
        <identifier>{$identifier}</identifier>
        <datestamp>2002-01-01</datestamp>
        <metadataNamespace>{$namespace}</metadataNamespace>
    </originDescription>
</provenance>
EOT;
        }

        /* rights */
        $about[] = <<<EOT
<?xml version="1.0"?>
<rights xmlns="http://www.openarchives.org/OAI/2.0/rights/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/rights/ http://www.openarchives.org/OAI/2.0/rights.xsd">
    <rightsReference ref="http://creativecommons.org/licenses/by-nd/2.0/rdf"/>
</rights>
EOT;

        return $about;
    }

    /**
     * Fetch a source object from a set of source objects.
     *
     * Since a set of objects is an array of xml elements, we return
     * the current element in the array and advance the array cursor.
     *
     * @param mixed $r the iterable list of source objects
     *
     * @return mixed the fetched object or false if there are no more objects
     */
    protected function nextObject(&$r)
    {
        $key = key($r);
        $val = current($r);
        next($r);

        return is_null($key) ? false : $val;
    }

    /**
     * Exec a query to select source objects in a given set, and return the
     * corresponding array of query results or false.
     *
     * Since the source data does not provide timestamp or deletion
     * status, the corresponding parameters are ignored. Also, since
     * this function returns the same objects whatever the value of
     * $set, it means that all imported records will be assigned to
     * all the sets passed as parameter.
     *
     * TODO: handle $identifierArray
     *
     * @param mixed[] $identifierArray array of local identifiers
     * @param string  $from            iso 'from' timestamp date
     * @param string  $to              iso 'to' timestamp date
     * @param bool    $noDeleted       exclude deleted records
     * @param string  $set             OAI set requested
     *
     * @return array array of source objects
     *
     * @throws Exception when the source file is missing or broken
     */
    protected function objects(
        $identifierArray = [],
        $from = null,
        $to = null,
        $noDeleted = false,
        $set = null
    ) {
        /* Read the content of the source file */
        $data = @file_get_contents(self::XML_SOURCE_FILENAME);
        if (false === $data) {
            throw new Exception('xml file: unable to open '.self::XML_SOURCE_FILENAME);
        }

        /* From now on, we handle all xml errors */
        $state = libxml_use_internal_errors(true);

        /* Create a Xml element from the source data */
        $collection = simplexml_load_string($data, 'Xml_Element');
        $this->clearXmlErrors('xml file '.self::XML_SOURCE_FILENAME, true);

        /* Sanity check: MODS namespace is declared and recognized */
        $rootNamespaces = $collection->getNamespaces();
        if (Meta_MODS::NS_MODS !== $rootNamespaces['']) {
            throw new Exception('xml file: Expected namespace '.Meta_MODS::NS_MODS);
        }

        /* Sanity check: all mods elements have an identifier */
        foreach ($collection->getChildrenArray() as $child) {
            $attrs = $child->getAttributes();

            if (!isset($attrs['ID'])) {
                throw new Exception('xml file: missing ID attribute');
            }
        }

        /* Store mods elements in an array, ready to enumerate */
        $collection->registerXPathNamespace('mods', Meta_MODS::NS_MODS);
        $res = $collection->xpath('/mods:modsCollection/mods:mods');

        $this->clearXmlErrors('xml file '.self::XML_SOURCE_FILENAME, true);

        /* Restore libxml error handing state */
        libxml_use_internal_errors($state);

        /* Returns false or an array containing a single iterable element,
         which is an array of simple xml elements */
        return (null === $res || false === $res) ? false : [$res];
    }

    /**
     * Analyze and execute an update request on the demo repository.
     *
     * @param string $hostname     host name to connect to
     * @param string $username     user name
     * @param string $password     password
     * @param string $database     database
     * @param string $repo         repo id (oai_repo.id)
     * @param bool   $save_history backup records before update
     */
    public static function execRequest(
        $hostname,
        $username,
        $password,
        $database,
        $repo,
        $save_history
    ) {
        try {
            $oUpdater = new Oai_DemoUpdater(
                $hostname,
                $username,
                $password,
                $database,
                $repo,
                $save_history
            );
            $oUpdater->parseAndRun($_GET);
        } catch (Exception $e) {
            exit('Error: '.$e->getMessage());
        }
    }
}
