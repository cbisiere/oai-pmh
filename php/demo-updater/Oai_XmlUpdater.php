<?php

/**
 * OAI repository updater using libxml functions.
 *
 * PHP version 7.1+
 *
 * @author   Christophe Bisière <christophe.bisiere@gmail.com>
 * @license  http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL, version 3
 */

/**
 * Class to update a repository using libxml functions, catching xml errors and
 * warnings.
 *
 * Throw exceptions when the backend cannot be initialized, or the updating process
 * cannot start or terminate nicely.
 */
abstract class Oai_XmlUpdater extends Oai_Updater
{
    /**
     * Throw an exception or emit warnings in case of pending XML errors.
     *
     * @param string $context context message
     * @param bool   $throw   true if an exception must be raised, false if
     *                        warnings must be emitted
     *
     * @throws Exception when an error is pending and $throw is true
     */
    protected function clearXmlErrors($context = null, $throw = false)
    {
        foreach (libxml_get_errors() as $error) {
            switch ($error->level) {
                case LIBXML_ERR_WARNING:
                    $type = 'Warning';
                    break;
                case LIBXML_ERR_ERROR:
                    $type = 'Error';
                    break;
                case LIBXML_ERR_FATAL:
                    $type = 'Fatal Error';
                    break;
                default:
                    $type = 'Unknown Error';
                    break;
            }

            $msg = "[{$context}] libxml {$type}: {$error->message}";

            if ($throw) {
                throw new Exception($msg);
            } else {
                $this->_warning .= "$msg\n";
            }
        }
        libxml_clear_errors();
    }

    /**
     * Build metadata for a source object, as a string.
     *
     * @param mixed  $f              the source object
     * @param string $metadataPrefix requested metadata
     *
     * @return string requested metadata, or null if no such metadata are
     *                available for this source object
     */
    protected function metadata($f, $metadataPrefix)
    {
        $metadata = null;
        $id = $this->id($f);
        $identifier = $this->identifier($id);

        /*
         * Error handling when building metadata: we catch
         * libxml errors and exceptions as well.
         */

        /*
         * Start capturing XML errors
         */
        $state = libxml_use_internal_errors(true);

        /*
         * Build metadata, emit a warning if it raises an exception
         */
        try {
            $xml = $this->xmlMetadata($f, $metadataPrefix);

            if (is_object($xml)) {
                $metadata = $xml->asXML();

                /* NOTE: useless, as an exception should have been raised */
                if (false === $metadata) {
                    $metadata = null;
                }
            }
        } catch (Exception $e) {
        }

        $this->clearXmlErrors("identifier=\"{$identifier}\" metadataPrefix=\"{$metadataPrefix}\"");
        libxml_use_internal_errors($state);

        return $metadata;
    }

    /**
     * Build metadata for a source object, as an XML element.
     *
     * @param mixed  $f              the source object
     * @param string $metadataPrefix requested metadata
     *
     * @return SimpleXMLElement requested metadata, or null if no such metadata are
     *                          available for this source object
     */
    abstract protected function xmlMetadata($f, $metadataPrefix);
}
