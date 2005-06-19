<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command/Sync/SyncElement.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Sync/ContentSyncElement.php,v 1.12 2004/07/02 19:24:44 chuck Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Command_Sync_ContentSyncElement extends Horde_SyncML_Command_Sync_SyncElement {

    /**
     * The content: vcard data, etc.
     */
    var $_content;

    /**
     * Local to server: our Horde guid.
     */
    var $_locURI;

    var $_targetURI;
    var $_contentType;

    function setSourceURI($uri)
    {
        $this->_locURI = $uri;
    }

    function getSourceURI()
    {
        return $this->_locURI;
    }

    function setTargetURI($uri)
    {
        $this->_targetURI = $uri;
    }

    function getTargetURI()
    {
        return $this->_targetURI;
    }

    function setContentType($c)
    {
        $this->_contentType = $c;
    }

    function getContentType()
    {
        return $this->_contentType;
    }

    function getContent()
    {
        return $this->_content;
    }

    function setContent($content)
    {
        $this->_content = $content;
    }

    function endElement($uri, $element)
    {
        switch ($this->_xmlStack) {
        case 2:
            if ($element == 'Data') {
                $this->_content = trim($this->_chars);
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function outputCommand($currentCmdID, &$output, $command)
    {
        $state = $_SESSION['SyncML.state'];

        $attrs = array();
        $output->startElement($state->getURI(), $command, $attrs);

        $output->startElement($state->getURI(), 'CmdID', $attrs);
        $chars = $currentCmdID;
        $output->characters($chars);
        $output->endElement($state->getURI(), 'CmdID');

        if (isset($this->_contentType)) {
            $output->startElement($state->getURI(), 'Meta', $attrs);
            $output->startElement($state->getURIMeta(), 'Type', $attrs);
            $output->characters($this->_contentType);
            $output->endElement($state->getURIMeta(), 'Type');
            $output->endElement($state->getURI(), 'Meta');
        }

        if (isset($this->_content)
            || isset($this->_locURI) || isset($this->targetURI)) {
            $output->startElement($state->getURI(), 'Item', $attrs);
            // send only when sending adds
            if ($this->_locURI != null && strtolower($command) == 'add') {
                $output->startElement($state->getURI(), 'Source', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $chars = substr($this->_locURI,0,39);
                $output->characters($chars);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Source');
            }

            if ($this->_targetURI != null) {
                $output->startElement($state->getURI(), 'Target', $attrs);
                $output->startElement($state->getURI(), 'LocURI', $attrs);
                $chars = $this->_targetURI;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'LocURI');
                $output->endElement($state->getURI(), 'Target');
            }
            if (isset($this->_content)) {
                $output->startElement($state->getURI(), 'Data', $attrs);
                $chars = $this->_content;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'Data');
            }
            $output->endElement($state->getURI(), 'Item');
        }

        $output->endElement($state->getURI(), $command);

        $currentCmdID++;

        return $currentCmdID;
    }

}
