<?php

include_once 'Horde/RPC/syncml.php';
include_once 'XML/WBXML/Decoder.php';
include_once 'XML/WBXML/Encoder.php';

/**
 * The Horde_RPC_syncml class provides a SyncML implementation of the Horde
 * RPC system.
 *
 * $Horde: framework/RPC/RPC/syncml_wbxml.php,v 1.11 2004/07/13 03:06:12 chuck Exp $
 *
 * Copyright 2003-2004 Chuck Hagenbuch <chuck@horde.org>, Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Anthony Mills <amills@pyramid6.com>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_RPC
 */
class Horde_RPC_syncml_wbxml extends Horde_RPC_syncml {

    /**
     * Sends an RPC request to the server and returns the result.
     *
     * @param string $request  The raw request string.
     *
     * @return string   The WBXML encoded response from the server (binary).
     */
    function getResponse($request)
    {
        // Catch any errors/warnings/notices that may get thrown while
        // processing. Don't want to let anything go to the client
        // that's not part of the valid response.
        ob_start();

        // Very useful for debugging. Logs WBXML packets to
        // $this->_debugDir.
        if (!empty($this->_debugDir) && is_dir($this->_debugDir)) {
            $today = date('Y-m-d');
            $deviceName = str_replace('/','',$_SERVER["HTTP_USER_AGENT"]);
            if(!is_dir($this->_debugDir .'/'. $today))
            {
            	mkdir($this->_debugDir .'/'. $today);
            }
            
            $debugDir = $this->_debugDir .'/'. $today .'/'. $deviceName;
            if(!is_dir($debugDir))
            {
            	mkdir($debugDir);
            }

            $packetNum = @intval(file_get_contents($debugDir . '/syncml_wbxml.packetnum'));
            if (!isset($packetNum)) {
                $packetNum = 0;
            }

            $fp = fopen($debugDir . '/syncml_client_' . $packetNum . '.wbxml', 'wb');
            fwrite($fp, $request);
            fclose($fp);
        }
        
        $decoder = &new XML_WBXML_Decoder();
        $xmlinput = $decoder->decode($request);
        if (is_a($xmlinput, 'PEAR_Error')) {
            return '';
        }


        $xmloutput = parent::getResponse($xmlinput);

        $encoder = &new XML_WBXML_Encoder();
        $encoder->setVersion($decoder->getVersion());
        $encoder->setCharset($decoder->getCharsetStr());
        $response = $encoder->encode($xmloutput);

        if (!empty($this->_debugDir) && is_dir($this->_debugDir)) {
            $fp = fopen($debugDir . '/syncml_server_' . $packetNum . '.wbxml', 'wb');
            fwrite($fp, $response);
            fclose($fp);

            $fp = fopen($debugDir . '/syncml_wbxml.packetnum', 'w');
            fwrite($fp, ++$packetNum);
            fclose($fp);
        }

        // Clear the output buffer that we started above, and log
        // anything that came up for later debugging.
        $errorLogging = ob_get_clean();
        if (!empty($errorLogging)) {
            Horde::logMessage($errorLogging, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        }

        return $response;
    }

    /**
     * Get the Content-Type of the response.
     *
     * @return string  The MIME Content-Type of the RPC response.
     */
    function getResponseContentType()
    {
        return 'application/vnd.syncml+wbxml';
    }

}
