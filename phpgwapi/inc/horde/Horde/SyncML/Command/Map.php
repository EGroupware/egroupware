<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

/**
 * The Horde_SyncML_Map class provides a SyncML implementation of
 * the Map command as defined in SyncML Representation Protocol,
 * version 1.0.1 5.5.8.
 *
 * $Horde: framework/SyncML/SyncML/Command/Map.php,v 1.1 2004/07/02 19:24:44 chuck Exp $
 *
 * Copyright 2004 Karsten Fourmont <fourmont@gmx.de>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Karsten Fourmont <fourmont@gmx.de>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Command_Map extends Horde_SyncML_Command {

    /**
     * @var string $_sourceURI
     */
    var $_sourceLocURI;

    /**
     * @var string $_targetURI
     */
    var $_targetLocURI;

    /**
     * Use in xml tag.
     */
    var $_isInSource;

    var $_mapTarget;
    var $_mapSource;

    function output($currentCmdID, &$output)
    {
        $attrs = array();

        $state = $_SESSION['SyncML.state'];

        $status = new Horde_SyncML_Command_Status($state->isAuthorized() ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS, 'Map');
        $status->setCmdRef($this->_cmdID);
        if ($this->_sourceLocURI != null) {
            $status->setSourceRef($this->_sourceLocURI);
        }
        if ($this->_targetLocURI != null) {
            $status->setTargetRef($this->_targetLocURI);
        }

        $currentCmdID = $status->output($currentCmdID, $output);

        return $currentCmdID;
    }

    /**
     * Setter for property sourceURI.
     *
     * @param string $sourceURI  New value of property sourceURI.
     */
    function setSourceLocURI($sourceURI)
    {
        $this->_sourceURI = $sourceURI;
    }

    function getTargetLocURI()
    {
        return $this->_targetURI;
    }

    /**
     * Setter for property targetURI.
     *
     * @param string $targetURI  New value of property targetURI.
     */
    function setTargetURI($targetURI)
    {
        $this->_targetURI = $targetURI;
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch ($this->_xmlStack) {
        case 2:
            if ($element == 'Target') {
                $this->_isInSource = false;
            }
            if ($element == 'Source') {
                $this->_isInSource = true;
            }
            if ($element == 'MapItem') {
                unset($this->_mapTarget);
                unset($this->_mapSource);
            }
            break;

        case 3:
            if ($element == 'Target') {
                $this->_isInSource = false;
            }
            if ($element == 'Source') {
                $this->_isInSource = true;
            }
            break;
        }
    }

    function endElement($uri, $element)
    {
        switch ($this->_xmlStack) {
        case 1:
            $state = $_SESSION['SyncML.state'];
            $sync = $state->getSync($this->_targetLocURI);

            if (!$sync) {
            }

            $_SESSION['SyncML.state'] = $state;
            break;

        case 2:
            if ($element == 'MapItem') {
                $state = $_SESSION['SyncML.state'];
                $sync = $state->getSync($this->_targetLocURI);
                if (!$state->isAuthorized()) {
                    Horde::logMessage('SyncML: Not Authorized in the middle of MapItem!', __FILE__, __LINE__, PEAR_LOG_ERR);
                } else {
                    Horde::logMessage("SyncML: creating Map for source=" .
                                      $this->_mapSource . " and target=" . $this->_mapTarget, __FILE__, __LINE__, PEAR_LOG_DEBUG);
                    // Overwrite existing data by removing it first:
                    $ts = $state->getServerAnchorNext($this->_targetLocURI);
                    $r = $state->setUID($this->_targetLocURI, $this->_mapSource, $this->_mapTarget, $ts);
                    if (is_a($r, 'PEAR_Error')) {
                        Horde::logMessage('SyncML: PEAR Error: ' . $r->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
                        return false;
                    }
                }
            }
            break;

        case 3:
            if ($element == 'LocURI') {
                if ($this->_isInSource) {
                    $this->_sourceLocURI = trim($this->_chars);
                } else {
                    $targetLocURIData = explode('?/',trim($this->_chars));

		    $this->_targetLocURI = $targetLocURIData[0];
		    
		    if(isset($targetLocURIData[1]))
		    {
		    	$this->_targetLocURIParameters = $targetLocURIData[1];
		    }
                }
            }
            break;

        case 4:
            if ($element == 'LocURI') {
                if ($this->_isInSource) {
                    $this->_mapSource = trim($this->_chars);
                } else {
                    $this->_mapTarget = trim($this->_chars);
                }
            }
            break;
        }

        parent::endElement($uri, $element);
    }

}
