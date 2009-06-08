<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';
include_once 'Horde/SyncML/Command/Results.php';

/**
 * The Horde_SyncML_Command_Get class.
 *
 * $Horde: framework/SyncML/SyncML/Command/Get.php,v 1.14 2004/07/02 19:24:44 chuck Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Command_Get extends Horde_SyncML_Command {

    function output($currentCmdID, &$output)
    {
        $state = $_SESSION['SyncML.state'];

        $ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';

        $status = new Horde_SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Get');
        $status->setCmdRef($this->_cmdID);
        $status->setTargetRef($ref);
        $currentCmdID = $status->output($currentCmdID, $output);

        if ($state->isAuthorized()) {
            $attrs = array();
            $output->startElement($state->getURI(), 'Results', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $chars = $currentCmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'MsgRef', $attrs);
            $chars = $state->getMsgID();
            $output->characters($chars);
            $output->endElement($state->getURI(), 'MsgRef');

            $output->startElement($state->getURI(), 'CmdRef', $attrs);
            $chars = $this->_cmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdRef');

            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            if (is_a($output, 'XML_WBXML_Encoder')) {
                $output->characters(MIME_SYNCML_DEVICE_INFO_WBXML);
            } else {
                $output->characters(MIME_SYNCML_DEVICE_INFO_XML);
            }

            $output->endElement($state->getURIMeta(), 'Type');
            $output->endElement($state->getURI(), 'Meta');

            $output->startElement($state->getURI(), 'Item', $attrs);
            $output->startElement($state->getURI(), 'Source', $attrs);
            $output->startElement($state->getURI(), 'LocURI', $attrs);
            $output->characters($ref);
            $output->endElement($state->getURI(), 'LocURI');
            $output->endElement($state->getURI(), 'Source');

            $output->startElement($state->getURI(), 'Data', $attrs);

            $output->startElement($state->getURIDevInf() , 'DevInf', $attrs);
            $output->startElement($state->getURIDevInf() , 'VerDTD', $attrs);
            $output->characters(($state->getVersion() == 0) ? '1.0' : '1.1');
            $output->endElement($state->getURIDevInf() , 'VerDTD', $attrs);
            $output->startElement($state->getURIDevInf() , 'Man', $attrs);
            $output->characters('www.egroupware.org');
            $output->endElement($state->getURIDevInf() , 'Man', $attrs);
            $output->startElement($state->getURIDevInf() , 'DevID', $attrs);
            $output->characters($_SERVER['HTTP_HOST']);
            $output->endElement($state->getURIDevInf() , 'DevID', $attrs);
            $output->startElement($state->getURIDevInf() , 'DevTyp', $attrs);
            $output->characters('server');
            $output->endElement($state->getURIDevInf() , 'DevTyp', $attrs);
            $this->_writeDataStore('./notes', 'text/x-vnote', '1.1', $output,
                                   array('text/plain' => '1.0'));
            $this->_writeDataStore('./contacts', 'text/x-vcard', '2.1', $output);
            $this->_writeDataStore('./tasks', 'text/x-vcalendar', '1.0', $output);
            $this->_writeDataStore('./calendar', 'text/x-vcalendar', '1.0', $output);
            $this->_writeDataStore('./caltasks', 'text/x-vcalendar', '1.0', $output);
            $output->endElement($state->getURIDevInf() , 'DevInf', $attrs);

            $output->endElement($state->getURI(), 'Data');
            $output->endElement($state->getURI(), 'Item');
            $output->endElement($state->getURI(), 'Results');

            $currentCmdID++;
        }

        return $currentCmdID;
    }

    /**
     * Writes DevInf data for one DataStore.
     *
     * @param string $sourceref: data for SourceRef element.
     * @param string $mimetype: data for &lt;(R|T)x-Pref&gt;&lt;CTType&gt;
     * @param string $version: data for &lt;(R|T)x-Pref&gt;&lt;VerCT&gt;
     * @param string &$output contenthandler that will received the output.
     * @param array $additionaltypes: array of additional types for Tx and Rx;
     *              format array('text/vcard' => '2.0')
     */
    function _writeDataStore($sourceref, $mimetype, $version, &$output,
                             $additionaltypes = false)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        $output->startElement($state->getURIDevInf() , 'DataStore', $attrs);
        $output->startElement($state->getURIDevInf() , 'SourceRef', $attrs);
        $output->characters($sourceref);
        $output->endElement($state->getURIDevInf() , 'SourceRef', $attrs);

        $output->startElement($state->getURIDevInf() , 'Rx-Pref', $attrs);
        $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->characters($mimetype);
        $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->characters($version);
        $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->endElement($state->getURIDevInf() , 'Rx-Pref', $attrs);

        if (is_array($additionaltypes)) {
            foreach ($additionaltypes as $ct => $ctver){
                $output->startElement($state->getURIDevInf() , 'Rx', $attrs);
                $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->characters($ct);
                $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->characters($ctver);
                $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->endElement($state->getURIDevInf() , 'Rx', $attrs);
            }
        }

        $output->startElement($state->getURIDevInf() , 'Tx-Pref', $attrs);
        $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->characters($mimetype);
        $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
        $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->characters($version);
        $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
        $output->endElement($state->getURIDevInf() , 'Tx-Pref', $attrs);

        if (is_array($additionaltypes)) {
            foreach ($additionaltypes as $ct => $ctver){
                $output->startElement($state->getURIDevInf() , 'Tx', $attrs);
                $output->startElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->characters($ct);
                $output->endElement($state->getURIDevInf() , 'CTType', $attrs);
                $output->startElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->characters($ctver);
                $output->endElement($state->getURIDevInf() , 'VerCT', $attrs);
                $output->endElement($state->getURIDevInf() , 'Tx', $attrs);
            }
        }

        $output->startElement($state->getURIDevInf() , 'SyncCap', $attrs);
        $output->startElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->characters('1');
        $output->endElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->startElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->characters('2');
        $output->endElement($state->getURIDevInf() , 'SyncType', $attrs);
        $output->endElement($state->getURIDevInf() , 'SyncCap', $attrs);
        $output->endElement($state->getURIDevInf() , 'DataStore', $attrs);
    }

}
