<?php

/**
 * OAI protocol v2: enhanced XML class.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Xml_Element.
 *
 * This class extends SimpleXMLElement, adding extra handy methods.
 */
class Xml_Element extends SimpleXMLElement
{
    /**
     * add a child.
     *
     * Invalid xml chars are stripped out.
     *
     * @param string $name  name of the element
     * @param string $value value of the element
     * @param string $ns    namespace
     *
     * @return SimpleXMLElement|null child added to the node
     */
    public function addChild($name, $value = null, $ns = null): ?SimpleXMLElement
    {
        if (isset($value)) {
            $value = Xml_Utils::utfToXml($value);
        }

        return parent::addChild($name, $value, $ns);
    }

    /**
     * insert CDATA into the current node.
     *
     * Invalid xml chars are stripped out.
     *
     * FIXME: handle "]]>" in $cdata
     *
     * @param string $cdata to insert
     */
    public function addCdata($cdata)
    {
        $cdata = Xml_Utils::utfToXml($cdata);

        $myDom = dom_import_simplexml($this);
        $node = $myDom->ownerDocument;
        $myDom->appendChild($node->createCDATASection($cdata));
    }

    /**
     * add a child element, escaping its content.
     *
     * The value has to be escaped, esp. because an ampersand in it
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
        if (isset($value)) {
            $value = Xml_Utils::xmlSpecialChars($value);
        }

        return $this->addChild($name, $value, $ns);
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
        return $this->newChild($name, null, $ns);
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
        $node = $this->newEmptyChild($name, $ns);
        $node->addCData($cdata);

        return $node;
    }

    /**
     * Append a SimpleXMLElement.
     *
     * FIXME: count($nss) > 1 ?
     *
     * @param SimpleXMLElement $xml element to append
     */
    public function append(SimpleXMLElement $xml)
    {
        $myDom = dom_import_simplexml($this);
        $dom = dom_import_simplexml($xml);

        $dom = $myDom->ownerDocument->importNode($dom, true);
        $myDom->appendChild($dom);

        return;
    }

    /**
     * Replace by a SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml replacement
     */
    public function replace(SimpleXMLElement $xml)
    {
        $myDom = dom_import_simplexml($this);
        $dom = dom_import_simplexml($xml);

        $dom = $myDom->ownerDocument->importNode($dom, true);
        $myDom->parentNode->replaceChild($dom, $myDom);

        return;
    }

    /**
     * Append all children of a SimpleXMLElement.
     *
     * @param SimpleXMLElement $xml element whose children must be appended
     */
    public function appendChildren(SimpleXMLElement $xml)
    {
        foreach ($xml->getChildrenArray() as $child) {
            $this->append($child);
        }
    }

    /**
     * Return children as an array of SimpleXMLElement.
     *
     * @return SimpleXMLElement[] Children
     */
    public function getChildrenArray()
    {
        $children = [];
        foreach ($this->children() as $child) {
            $children[] = $child;
        }

        return $children;
    }

    /**
     * Return attributes as an associative array.
     *
     * @return string[] Attributes
     */
    public function getAttributes()
    {
        $attributes = [];
        foreach ($this->attributes() as $k => $v) {
            $attributes[$k] = (string) $v;
        }

        return $attributes;
    }

    /**
     * Return the Xml element transformed using a XSL stylesheet.
     *
     * TODO: handle any false value returned by setParameter
     *
     * @param string   $xslFile       path of the stylesheet
     * @param string[] $xslParameters key-value parameters to pass to stylesheet
     *
     * @return Xml_Element result of the transformation (may be null), or false
     *                     in case of failure
     */
    public function transform($xslFile, $xslParameters = [])
    {
        $xml = null;
        $xslt = new XSLTProcessor();

        $xsl = simplexml_load_file($xslFile);

        if (false === $xsl || !isset($xsl)) {
            return false;
        }

        /* Setup a DOM interface for the XML element */
        $node = dom_import_simplexml($this);
        $doc = new DOMDocument();
        $doc->appendChild($doc->importNode($node, true));

        /* Load and apply stylesheet */
        $xslt->importStylesheet($xsl);

        /* Apply parameters */
        foreach ($xslParameters as $key => $val) {
            $xslt->setParameter('', $key, $val);
        }

        /* Run the XSLT processor */
        $str = $xslt->transformToXml($doc);

        if (false === $str || is_null($str)) {
            return $str;
        }

        /* Create the XML element */
        $xml = new Xml_Element($str);

        return $xml;
    }

    /**
     * Return a string representation of the Xml tree.
     *
     * @param bool $header prepend xml header
     *
     * @return string the Xml tree as a string
     */
    public function toString($header = false)
    {
        $dom = dom_import_simplexml($this);

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->formatOutput = true;
        $node = $doc->importNode($dom, true);
        $node = $doc->appendChild($node);

        $str = ($header ? $doc->saveXML() : $doc->saveXML($node));

        /* final fix */
        $str = str_replace('&amp;amp;', '&amp;', $str);

        return $str;
    }
}
