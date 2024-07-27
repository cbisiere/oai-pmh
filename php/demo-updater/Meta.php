<?php
/**
 * OAI protocol v2: Metadata class.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Meta.
 *
 * Class handling metadata.
 */
class Meta
{
    /*
     * Properties
     */

    private $_datestamp;		/* datestamp of the metadata */
    private $_xml;			/* XML_Element */

    /*
     * constructors
     */

    /**
     * Constructor.
     *
     * @param string     $tag     root tag
     * @param string     $prefix  namespace prefix
     * @param string     $ns      namespace uri
     * @param string     $version schema version
     * @param Xml_NsData $oNs     namespace data
     */
    public function __construct(
        $tag = null,
        $prefix = null,
        $ns = null,
        $version = null,
        ?Xml_NsData $oNs = null
    ) {
        if (!isset($tag)) {
            $tag = 'root';
        }

        $attrs = (isset($oNs) ? $oNs->getAsAttrs() : []);
        $qname = (isset($prefix) ? $prefix.':'.$tag : $tag);

        if (isset($version)) {
            $attrs['version'] = $version;
        }
        $root = Xml_Utils::emptyElementAsString($qname, $attrs);

        $this->_xml = new XML_Element($root);
    }

    /*
     * Datestamp
     */

    /**
     * Set the datestamp.
     *
     * @param string $datestamp new datestamp
     */
    public function setDatestamp($datestamp)
    {
        $this->_datestamp = (string) $datestamp;
    }

    /**
     * Update the datestamp, keeping the most recent one.
     *
     * @param string $datestamp datestamp to take into account
     */
    public function updateDatestamp($datestamp)
    {
        if (!isset($this->_datestamp) || ($this->_datestamp < $datestamp)) {
            $this->_datestamp = $datestamp;
        }
    }

    /**
     * Return the datestamp.
     *
     * @return string the datestamp
     */
    public function getDatestamp()
    {
        return $this->_datestamp;
    }

    /*
     * Helpers to talk to the underlying Xml element
     */

    /**
     * Return the Xml object.
     *
     * @return Xml_Element the Xml object
     */
    public function getXml()
    {
        return $this->_xml;
    }

    /**
     * Set the Xml object.
     *
     * @param Xml_Element $xml The Xml object
     */
    public function setXml(Xml_Element $xml)
    {
        $this->_xml = $xml;
    }

    /**
     * Creates a prefix/ns context for the next XPath query.
     *
     * @param string $prefix namespace prefix
     * @param string $ns     namespace uri
     *
     * @return bool
     */
    public function registerXPathNamespace($prefix, $ns)
    {
        return $this->_xml->registerXPathNamespace($prefix, $ns);
    }

    /**
     * addChild with a value, and an optional namespace.
     *
     * The value has to be escaped, esp. because an ampercent in it
     * breaks addChild.
     *
     * @param string $name  name of the element
     * @param string $value value of the element
     * @param string $ns    namespace
     *
     * @return SimpleXMLElement child added to the node
     */
    public function newChild($name, $value, $ns = null)
    {
        return $this->_xml->newChild($name, $value, $ns);
    }

    /**
     * add a CDATA child element.
     *
     * @param string $name  name of the element
     * @param string $cdata CDATA
     * @param string $ns    namespace
     *
     * @return SimpleXMLElement child added to the node
     */
    public function newCdataChild($name, $cdata, $ns = null)
    {
        return $this->_xml->newCdataChild($name, $cdata, $ns);
    }

    /**
     * addChild with no value, and an optional namespace.
     *
     * @param string $name name of the element
     * @param string $ns   namespace
     *
     * @return SimpleXMLElement child added to the node
     */
    public function newEmptyChild($name, $ns = null)
    {
        return $this->_xml->newEmptyChild($name, $ns);
    }

    /**
     * Append a SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml element to append
     */
    public function append(SimpleXMLElement $xml)
    {
        $this->_xml->append($xml);
    }

    /**
     * Append all children of a SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml element whose children must be appended
     */
    public function appendChildren(SimpleXMLElement $xml)
    {
        return $this->_xml->appendChildren($xml);
    }

    /**
     * Return children as an array of SimpleXMLElement.
     *
     * @return array children, as SimpleXMLElement
     */
    public function getChildren()
    {
        return $this->_xml->getChildren($xml);
    }

    /**
     * Return the metadata transformed using a XSL stylesheet.
     *
     * @param string $xslFile       path of the stylesheet
     * @param array  $xslParameters key-value parameters to pass to stylesheet
     *
     * @return Xml_Element transformed metadata, or null in case of failure
     */
    public function transform($xslFile, $xslParameters = [])
    {
        return $this->_xml->transform($xslFile, $xslParameters);
    }

    /**
     * Return a well-formed XML string based on the Meta element.
     *
     * @return mixed the string on success and false on error
     */
    public function asXML()
    {
        return $this->_xml->asXML();
    }

    /**
     * Return a string representation of the metadata.
     *
     * @param bool $header prepend xml header
     *
     * @return string the Xml tree as a string
     */
    public function toString($header = false)
    {
        return $this->_xml->toString($header);
    }
}
