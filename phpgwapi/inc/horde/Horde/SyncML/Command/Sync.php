<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';
include_once 'Horde/SyncML/Command/Sync/SyncElement.php';
include_once 'Horde/SyncML/Sync/TwoWaySync.php';
include_once 'Horde/SyncML/Sync/SlowSync.php';
include_once 'Horde/SyncML/Sync/OneWayFromServerSync.php';
include_once 'Horde/SyncML/Sync/RefreshFromServerSync.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Sync.php,v 1.17 2004/07/03 15:21:14 chuck Exp $
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
class Horde_SyncML_Command_Sync extends Horde_Syncml_Command {

	var $_isInSource;
	var $_currentSyncElement;
	var $_syncElements = array();
	
	function output($currentCmdID, &$output) {
	
		$state = &$_SESSION['SyncML.state'];
		
		$attrs = array();
		
		Horde::logMessage('SyncML: $this->_targetURI = ' . $this->_targetURI, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		
		$status = new Horde_SyncML_Command_Status(RESPONSE_OK, 'Sync');
		
		// $status->setState($state);
		
		$status->setCmdRef($this->_cmdID);
		
		if ($this->_targetURI != null) {
			$status->setTargetRef((isset($this->_targetURIParameters) ? $this->_targetURI.'?/'.$this->_targetURIParameters : $this->_targetURI));
		}
		
		if ($this->_sourceURI != null) {
			$status->setSourceRef($this->_sourceURI);
		}
		
		$currentCmdID = $status->output($currentCmdID, $output);
		
		if($sync = $state->getSync($this->_targetURI)) {
			$currentCmdID = $sync->startSync($currentCmdID, $output);
			
			foreach ($this->_syncElements as $element) {
				$currentCmdID = $sync->nextSyncCommand($currentCmdID, $element, $output);
			}
		}

		return $currentCmdID;
	}
	
	function getTargetURI() {
		return $this->_targetURI;
	}

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch ($this->_xmlStack) {
        case 2:
            if ($element == 'Replace' || $element == 'Add' || $element == 'Delete') {
                $this->_currentSyncElement = &Horde_SyncML_Command_Sync_SyncElement::factory($element);
                // $this->_currentSyncElement->setVersion($this->_version);
                // $this->_currentSyncElement->setCmdRef($this->_cmdID);
                // $this->_currentSyncElement->setMsgID($this->_msgID);
            } elseif ($element == 'Target') {
                $this->_isInSource = false;
            } else {
                $this->_isInSource = true;
            }
            break;
        }

        if (isset($this->_currentSyncElement)) {
            $this->_currentSyncElement->startElement($uri, $element, $attrs);
        }
    }
    
    // We create a seperate Sync Element for the Sync Data sent
    // from the Server to the client as we want to process the
    // client sync information before.

    function syncToClient($currentCmdID, &$output)
    {
        Horde::logMessage('SyncML: starting sync to client', __FILE__, __LINE__, PEAR_LOG_DEBUG);

        $state = $_SESSION['SyncML.state'];
        if($state->getSyncStatus() >= CLIENT_SYNC_ACKNOWLEDGED && $state->getSyncStatus() < SERVER_SYNC_FINNISHED)
        {
	        $deviceInfo = $state->getClientDeviceInfo();
		$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);      
	        $targets = $state->getTargets();
	        Horde::logMessage('SyncML: starting sync to client '.$targets[0], __FILE__, __LINE__, PEAR_LOG_DEBUG);
	        $attrs = array();

		foreach($targets as $target)
		{
			$sync = $state->getSync($target);
			
			// make sure that the state reflects what is currently being done
			$state->_currentSourceURI = $sync->_sourceLocURI;
			$state->_currentTargetURI = $sync->_targetLocURI;
        	
			$output->startElement($state->getURI(), 'Sync', $attrs);
			$output->startElement($state->getURI(), 'CmdID', $attrs);
			$output->characters($currentCmdID);
			$currentCmdID++;
			$output->endElement($state->getURI(), 'CmdID');
	
			$output->startElement($state->getURI(), 'Target', $attrs);
			$output->startElement($state->getURI(), 'LocURI', $attrs);
			$chars = $sync->_sourceLocURI;
			$output->characters($chars);
			$output->endElement($state->getURI(), 'LocURI');
			$output->endElement($state->getURI(), 'Target');
	
			$output->startElement($state->getURI(), 'Source', $attrs);
			$output->startElement($state->getURI(), 'LocURI', $attrs);
			#$chars = $sync->_targetLocURI;
			$chars = (isset($sync->_targetLocURIParameters) ? $sync->_targetLocURI.'?/'.$sync->_targetLocURIParameters : $sync->_targetLocURI);
			$output->characters($chars);
			$output->endElement($state->getURI(), 'LocURI');
			$output->endElement($state->getURI(), 'Source');
		
			if(!$sync->_syncDataLoaded)
			{
				$numberOfItems = $sync->loadData();
				if($deviceInfo['supportNumberOfChanges'])
				{
					$output->startElement($state->getURI(), 'NumberOfChanged', $attrs);
					$output->characters($numberOfItems);
					$output->endElement($state->getURI(), 'NumberOfChanged');
				}
			}
		
			$currentCmdID = $sync->endSync($currentCmdID, $output);
		
			$output->endElement($state->getURI(), 'Sync');
			
			break;
		}
	
		// no syncs left
		if($state->getTargets() === FALSE)
			$state->setSyncStatus(SERVER_SYNC_FINNISHED);
	
		Horde::logMessage('SyncML: syncStatus(server_sync_finnished) '. $state->getSyncStatus, __FILE__, __LINE__, PEAR_LOG_DEBUG);
	}	

	return $currentCmdID;
    }

    function endElement($uri, $element)
    {
        if (isset($this->_currentSyncElement)) {
            $this->_currentSyncElement->endElement($uri, $element);
        }

        switch ($this->_xmlStack) {
        case 2:
            if ($element == 'Replace' || $element == 'Add' || $element == 'Delete') {
                $this->_syncElements[] = $this->_currentSyncElement;
                unset($this->_currentSyncElement);
            }
            break;

        case 3:
      	    $state = & $_SESSION['SyncML.state'];
      	    
            if ($element == 'LocURI' && !isset($this->_currentSyncElement)) {
                if ($this->_isInSource) {
                    $this->_sourceURI = trim($this->_chars);
                    $state->_currentSourceURI = $this->_sourceURI;
                } else {
                    $this->_targetURI = trim($this->_chars);

                    $targetURIData = explode('?/',trim($this->_chars));

		    $this->_targetURI = $targetURIData[0];
		    $state->_currentTargetURI = $this->_targetURI;
		    
		    if(isset($targetURIData[1]))
		    {
		    	$this->_targetURIParameters = $targetURIData[1];
		    	$state->_currentTargetURIParameters = $this->_targetURIParameters;
		    }
                    
                }
            }
            break;
        }

        parent::endElement($uri, $element);
        
    }

    function characters($str)
    {
        if (isset($this->_currentSyncElement)) {
            $this->_currentSyncElement->characters($str);
        } else {
            if (isset($this->_chars)) {
                $this->_chars = $this->_chars . $str;
            } else {
                $this->_chars = $str;
            }
        }
    }

}
