<?php

/**
 * OAI protocol v2: various XML helpers.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Xml Utils.
 *
 * This class provides various Xml functions.
 */
class Xml_Utils
{
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const NS_XSD = 'http://www.w3.org/2001/XMLSchema';

    /**
     * Strip out from a UTF-8 string characters that are illegal in XML.
     *
     * http://www.w3.org/TR/REC-xml/#charsets
     *
     * @param string $str         string to clean
     * @param string $replacement replacement string (default to none)
     *
     * @return string clean string
     */
    public static function utfToXml($str, $replacement = '')
    {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', $replacement, $str);
    }

    /**
     * Escape xml special characters.
     *
     * @param string $str string to escape
     *
     * @return string escaped string
     */
    public static function xmlSpecialChars($str)
    {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES | ENT_DISALLOWED, 'UTF-8');
    }

    /**
     * Return an xml empty element.
     *
     * @param string   $name  name of the XML element
     * @param string[] $attrs attributes of the element
     *
     * @return string xml element
     */
    public static function emptyElementAsString($name, $attrs)
    {
        foreach ($attrs as $k => $v) {
            $attrs[$k] = self::xmlSpecialChars($k)
                .'="'.self::xmlSpecialChars($v).'"';
        }

        return '<'.$name.' '.implode(' ', $attrs).'/>';
    }

    /**
     * Return a processing instruction as a string.
     *
     * @param string   $name  name of the processing instruction
     * @param string[] $attrs attributes of the processing instruction
     *
     * @return string processing instruction
     */
    public static function piAsString($name, $attrs)
    {
        foreach ($attrs as $k => $v) {
            $attrs[$k] = self::xmlSpecialChars($k)
                .'="'.self::xmlSpecialChars($v).'"';
        }

        return '<?'.$name.' '.implode(' ', $attrs).'?>';
    }

    /**
     * Return an element to use with new xml element, with proper schema
     * namespaces.
     *
     * @param string   $name  name of the xml element
     * @param string[] $attrs array of attributes
     *
     * @return string element in html
     */
    public static function rootElement($name, $attrs)
    {
        if (isset($attrs['xsi:schemaLocation'])) {
            $attrs['xmlns:xsd'] = self::NS_XSD;
            $attrs['xmlns:xsi'] = self::NS_XSI;
        }

        return self::emptyElementAsString($name, $attrs);
    }
}
