<?php

require_once 'Horde/SyncML.php';
#require_once 'Horde/SyncML/State.php';
require_once 'Horde/SyncML/State_egw.php';
require_once 'Horde/SyncML/Command/Status.php';

/**
 * The Horde_RPC_syncml class provides a SyncML implementation of the Horde
 * RPC system.
 *
 * $Horde: framework/RPC/RPC/syncml.php,v 1.27 2006/01/01 21:10:11 jan Exp $
 *
 * Copyright 2003-2006 Chuck Hagenbuch <chuck@horde.org>
 * Copyright 2003-2006 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Anthony Mills <amills@pyramid6.com>
 * @since   Horde 3.0
 * @package Horde_RPC
 */
class Horde_RPC_syncml extends Horde_RPC {

    /**
     * Output ContentHandler used to output XML events.
     *
     * @var object
     */
    var $_output;

    /**
     * @var integer
     */
    var $_xmlStack = 0;

    /**
     * Debug directory, if set will store copies of all packets.
     *
     * @var string
     */
    var $_debugDir = '/tmp/sync';

    /**
     * Default character set.  Only supports UTF-8(ASCII?).
     *
     * @var string
     */
    var $_charset = 'UTF-8';

    /**
     * SyncML handles authentication internally, so bypass the RPC framework
     * auth check by just returning true here.
     */
    function authorize()
    {
        return true;
    }

    /**
     * Sends an RPC request to the server and returns the result.
     *
     * @param string $request  The raw request string.
     *
     * @return string  The XML encoded response from the server.
     */
    function getResponse($request)
    {
        /* Catch any errors/warnings/notices that may get thrown while
         * processing. Don't want to let anything go to the client that's not
         * part of the valid response. */
        ob_start();

        /* Very useful for debugging. Logs WBXML packets to
         * $this->_debugDir. */
        if (!empty($this->_debugDir) && is_dir($this->_debugDir)) {
            $packetNum = @intval(file_get_contents($this->_debugDir . '/syncml.packetnum'));
            if (!isset($packetNum)) {
                $packetNum = 0;
            }

            $f = @fopen($this->_debugDir . '/syncml_client_' . $packetNum . '.xml', 'wb');
            if ($f) {
                fwrite($f, $request);
                fclose($f);
            }
        }

        require_once 'XML/WBXML/ContentHandler.php';
        $this->_output = new XML_WBXML_ContentHandler();

        $this->_parse($request);
        $response = '<?xml version="1.0" encoding="' . $this->_charset . '"?>';
        $response .= $this->_output->getOutput();

        /* Very useful for debugging. */
        if (!empty($this->_debugDir) && is_dir($this->_debugDir)) {
            $f = @fopen($this->_debugDir . '/syncml_server_' . $packetNum . '.xml', 'wb');
            if ($f) {
                fwrite($f, $response);
                fclose($f);
            }

            $fp = @fopen($this->_debugDir . '/syncml.packetnum', 'w');
            if ($fp) {
                fwrite($fp, ++$packetNum);
                fclose($fp);
            }
        }

        /* Clear the output buffer that we started above, and log anything
         * that came up for later debugging. */
        $errorLogging = ob_get_clean();
        if (!empty($errorLogging)) {
            Horde::logMessage('SyncML: caught output=' .
                              $errorLogging, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }

        return $response;
    }

    function _parse($xml)
    {
        /* try to extract charset from XML text */
        if(preg_match('/^\s*<\?xml[^>]*encoding\s*=\s*"([^"]*)"/i',
                $xml, $m)) {
            $this->_charset = $m[1];
        }
        #NLS::setCharset($this->_charset);
        #String::setDefaultCharset($this->_charset);

        /* Create the XML parser and set method references. */
        $this->_parser = xml_parser_create_ns($this->_charset);
        xml_set_object($this->_parser, $this);
        xml_parser_set_option($this->_parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_element_handler($this->_parser, '_startElement', '_endElement');
        xml_set_character_data_handler($this->_parser, '_characters');
        xml_set_processing_instruction_handler($this->_parser, '');
        xml_set_external_entity_ref_handler($this->_parser, '');

        if (!xml_parse($this->_parser, $xml)) {
            return $this->raiseError(sprintf('XML error: %s at line %d',
                                             xml_error_string(xml_get_error_code($this->_parser)),
                                             xml_get_current_line_number($this->_parser)));
        }

        xml_parser_free($this->_parser);
    }

    function _startElement($parser, $tag, $attributes)
    {
        list($uri, $name) = $this->_splitURI($tag);

        $this->startElement($uri, $name, $attributes);
    }

    function _characters($parser, $chars)
    {
        $this->characters($chars);
    }

    function _endElement($parser, $tag)
    {
        list($uri, $name) = $this->_splitURI($tag);

        $this->endElement($uri, $name);
    }

    function _splitURI($tag)
    {
        $parts = explode(':', $tag);
        $name = array_pop($parts);
        $uri = implode(':', $parts);
        return array($uri, $name);
    }

    /**
     * Returns the Content-Type of the response.
     *
     * @return string  The MIME Content-Type of the RPC response.
     */
    function getResponseContentType()
    {
        return 'application/vnd.syncml+xml';
    }

    function startElement($uri, $element, $attrs)
    {
        $this->_xmlStack++;

        switch ($this->_xmlStack) {
        case 1:
            // <SyncML>
            // Defined in SyncML Representation Protocol, version 1.1 5.2.1
            $this->_output->startElement($uri, $element, $attrs);
            break;

        case 2:
            // Either <SyncML><SyncHdr> or <SyncML><SyncBody>
            if (!isset($this->_contentHandler)) {
                // If not defined then create SyncHdr.
                $this->_contentHandler = new Horde_SyncML_SyncmlHdr();
                $this->_contentHandler->setOutput($this->_output);
            }

            $this->_contentHandler->startElement($uri, $element, $attrs);
            break;

        default:
            if (isset($this->_contentHandler)) {
                $this->_contentHandler->startElement($uri, $element, $attrs);
            }
            break;
        }
    }

    function endElement($uri, $element)
    {
        switch ($this->_xmlStack) {
        case 1:
            // </SyncML>
            // Defined in SyncML Representation Protocol, version 1.1 5.2.1
            $this->_output->endElement($uri, $element);
            break;

        case 2:
            // Either </SyncHdr></SyncML> or </SyncBody></SyncML>
            if ($element == 'SyncHdr') {
                // Then we get the state from SyncMLHdr, and create a new
                // SyncMLBody.
                $this->_contentHandler->endElement($uri, $element);

                unset($this->_contentHandler);

                $this->_contentHandler = new Horde_SyncML_SyncmlBody();
                $this->_contentHandler->setOutput($this->_output);
            } else {
                // No longer used.
                $this->_contentHandler->endElement($uri, $element);
                unset($this->_contentHandler);
            }
            break;

        default:
            // </*></SyncHdr></SyncML> or </*></SyncBody></SyncML>
            if (isset($this->_contentHandler)) {
                $this->_contentHandler->endElement($uri, $element);
            }
            break;
        }

        if (isset($this->_chars)) {
            unset($this->_chars);
        }

        $this->_xmlStack--;
    }

    function characters($str)
    {
        if (isset($this->_contentHandler)) {
            $this->_contentHandler->characters($str);
        }
    }

    function raiseError($str)
    {
        return Horde::logMessage($str, __FILE__, __LINE__, PEAR_LOG_ERR);
    }

}
