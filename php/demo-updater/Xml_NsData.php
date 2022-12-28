<?php

/**
 * OAI protocol v2: helpers for namespace handling.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Xml_NsData.
 *
 * This class handle the name space attributes of a root element
 */
class Xml_NsData
{
    private $_xsd;					/* xsd prefix */
    private $_xsi;					/* xsi prefix */

    private $_uri = [];		/* namespace uri per prefix */
    private $_schema = [];		/* schema location per namespace uri */

    /**
     * Constructor.
     *
     * @param string $xsdPrefix prefix for XSD elements
     * @param string $xsiPrefix prefix for XSI elements
     */
    public function __construct($xsdPrefix = 'xsd', $xsiPrefix = 'xsi')
    {
        $this->_xsd = $xsdPrefix;
        $this->_xsi = $xsiPrefix;
    }

    /**
     * Add a new namespace to the current set.
     *
     * @param string $prefix         prefix of the namespace
     * @param string $uri            uri of the namespace
     * @param string $schemaLocation location of the namespace's schema, if any
     */
    public function add($prefix, $uri, $schemaLocation = null)
    {
        /* this is the first schema */
        if ((0 == count($this->_schema)) && (isset($schemaLocation))) {
            $this->_uri[$this->_xsd] = Xml_Ns::NS_XSD; /* TODO: do we really need that one? */
            $this->_uri[$this->_xsi] = Xml_Ns::NS_XSI;
        }

        $this->_uri[$prefix] = $uri;

        if (isset($schemaLocation)) {
            $this->_schema[$uri] = $schemaLocation;
        }
    }

    /**
     * Return the object as an array of Xml attributes.
     *
     * @return string[] of Attributes
     */
    public function getAsAttrs()
    {
        $attrs = [];

        /* namespace uris */
        foreach ($this->_uri as $prefix => $uri) {
            $attrs["xmlns:$prefix"] = $uri;
        }

        /* namespace schema locations */
        if (count($this->_schema)) {
            $loc = [];
            foreach ($this->_schema as $uri => $schema) {
                $loc[] = $uri.' '.$schema;
            }
            $attrs["{$this->_xsi}:schemaLocation"] = implode(' ', $loc);
        }

        return $attrs;
    }
}
