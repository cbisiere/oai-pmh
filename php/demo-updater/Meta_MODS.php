<?php
/**
 * OAI metadata: MODS.
 *
 * PHP version 7.1+
 *
 * 2012-05-06: it is now possible to ignore schemaLocation. The rational for this is
 *   that when a MODS document is included in another XML document (e.g. DIDL-MODS),
 *   it may be desirable to have a single schemaLocation attribute in the root of the
 *   main document. This is _not_ mandatory for XML compliance, though. See
 *   http://www.w3.org/TR/xmlschema-1/#schema-loc 4.3.2 §4: "xsi:schemaLocation
 *   and xsi:noNamespaceSchemaLocation [attributes] can occur on any element."
 * 2018-12-31: add MODS version parameter
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 *
 * @see      https://www.loc.gov/standards/mods/
 */

/**
 * Meta_MODS.
 *
 * A class to handle MODS metadata.
 */
class Meta_MODS extends Meta
{
    public const MODS_PREFIX = 'mods';
    public const XLINK_PREFIX = 'xlink';

    public const NS_MODS = 'http://www.loc.gov/mods/v3';
    public const LC_MODS = 'http://www.loc.gov/standards/mods/v3/mods-3-8.xsd';

    /*
     * constructors
    */

    /**
     * Constructor.
     *
     * @param bool   $withSchemaLocation include schemaLocation attributes?
     * @param string $version            MODS version, null for latest
     */
    public function __construct($withSchemaLocation = true, $version = null)
    {
        $oNs = new Xml_NsData();
        self::addNamespaces($oNs, $withSchemaLocation);

        parent::__construct('mods', self::MODS_PREFIX, self::NS_MODS, $version, $oNs);
    }

    /**
     * Add MODS namespaces to a namespace object.
     *
     * @param Xml_NsData $oNs                namespace object
     * @param bool       $withSchemaLocation include schemaLocation attributes?
     */
    public static function addNamespaces(
        Xml_NsData $oNs,
        $withSchemaLocation = true
    ) {
        /* location URL */
        $modsLoc = null;
        if ($withSchemaLocation) {
            $modsLoc = self::LC_MODS;

            /* when required, compute and insert a version tag */
            if (isset($version)) {
                $version_tag = '-'.str_replace('.', '-', $version);      /* '3.8' => '-3-8' */
                $modsLoc = str_replace('.xsd', $version_tag.'.xsd', $modsLoc);
            }
        }

        $modsLoc = ($withSchemaLocation ? self::LC_MODS : null);
        $oNs->add(self::MODS_PREFIX, self::NS_MODS, $modsLoc);

        /* 2013-10-27: declare xlink */
        $xlinkLoc = ($withSchemaLocation ? Xml_Ns::LC_XLINK : null);
        $oNs->add(self::XLINK_PREFIX, Xml_Ns::NS_XLINK, $xlinkLoc);
    }
}
