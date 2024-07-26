<?php

/**
 * OAI metadata: Dublin Core.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Meta_DC.
 *
 * This class handle OAI_DC metadata.
 */
class Meta_DC extends Meta
{
    /**
     * Set the metadata by transforming a MODS document.
     *
     * See:
     * https://www.loc.gov/standards/mods/v3/MODS3-8_DC_XSLT1-0.xsl
     *
     * @param Meta_MODS $oMetaMods the MODS document to transform
     */
    public function xslMods(Meta_MODS $oMetaMods)
    {
        $xmlMods = $oMetaMods->getXml();

        if (isset($xmlMods)) {
            $xdc = $xmlMods->transform('./xsl/MODS3-8_DC_XSLT1-0.xsl');
            $this->setXml($xdc);
        }
    }
}
