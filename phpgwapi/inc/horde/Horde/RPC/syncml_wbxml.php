<?php

require_once dirname(__FILE__) . '/syncml.php';
require_once 'XML/WBXML/Decoder.php';
require_once 'XML/WBXML/Encoder.php';

/**
 * The Horde_RPC_syncml_wbxml class provides a SyncML implementation of the
 * Horde RPC system using WBXML encoding.
 *
 * $Horde: framework/RPC/RPC/syncml_wbxml.php,v 1.18 2006/01/01 21:10:11 jan Exp $
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
class Horde_RPC_syncml_wbxml extends Horde_RPC_syncml {

    /**
     * Sends an RPC request to the server and returns the result.
     *
     * @param string $request  The raw request string.
     *
     * @return string  The WBXML encoded response from the server (binary).
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

            $fp = fopen($this->_debugDir . '/syncml_client_' . $packetNum . '.wbxml', 'wb');
            fwrite($fp, $request);
            fclose($fp);
        }


        $decoder = new XML_WBXML_Decoder();
        $this->_output = new XML_WBXML_Encoder();

        $decoder->setContentHandler($this);

        $r = $decoder->decode($request);
        if (is_a($r, 'PEAR_Error')) {
            Horde::logMessage('SyncML: ' .
                              $r->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
        }

        $this->_output->setVersion($decoder->getVersion());
        $this->_output->setCharset($decoder->getCharsetStr());
        $response = $this->_output->getOutput();

        if (is_a($response, 'PEAR_Error')) {
            Horde::logMessage($response, __FILE__, __LINE__, PEAR_LOG_ERR);
            $response = $response->getMessage();
        }

        if (!empty($this->_debugDir) && is_dir($this->_debugDir)) {
            $fp = fopen($this->_debugDir . '/syncml_server_' . $packetNum . '.wbxml', 'wb');
            fwrite($fp, $response);
            fclose($fp);

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
            Horde::logMessage('SyncML: caught output=' . $errorLogging,
            __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }

        return $response;
    }

    /**
     * Returns the Content-Type of the response.
     *
     * @return string  The MIME Content-Type of the RPC response.
     */
    function getResponseContentType()
    {
        return 'application/vnd.syncml+wbxml';
    }

}
