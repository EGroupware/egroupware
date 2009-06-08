<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

/**
 * The Horde_SyncML_Alert class provides a SyncML implementation of
 * the Alert command as defined in SyncML Representation Protocol,
 * version 1.1 5.5.2.
 *
 * $Horde: framework/SyncML/SyncML/Command/Alert.php,v 1.18 2004/07/03 15:21:14 chuck Exp $
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
class Horde_SyncML_Command_Alert extends Horde_SyncML_Command {

    /**
     * @var integer $_alert
     */
    var $_alert;

    /**
     * @var string $_sourceURI
     */
    var $_sourceLocURI;

    /**
     * @var string $_targetURI
     */
    var $_targetLocURI;

    /**
     * @var string $_metaAnchorNext
     */
    var $_metaAnchorNext;

    /**
     * @var integer $_metaAnchorLast
     */
    var $_metaAnchorLast;

    /**
     * Use in xml tag.
     */
    var $_isInSource;

    /**
     * Creates a new instance of Alert.
     */
    function Horde_SyncML_Command_Alert($alert = null)
    {
        if ($alert != null) {
            $this->_alert = $alert;
        }
    }

    function output($currentCmdID, &$output)
    {
        $attrs = array();

        $state = &$_SESSION['SyncML.state'];

        // Handle unauthorized first.
        if (!$state->isAuthorized()) {
            $status = new Horde_SyncML_Command_Status(RESPONSE_INVALID_CREDENTIALS, 'Alert');
            $status->setCmdRef($this->_cmdID);
            $currentCmdID = $status->output($currentCmdID, $output);
            return $currentCmdID;
        }



	if($this->_alert < ALERT_RESULT_ALERT) {

        	$type = $this->_targetLocURI;

        	// Store client's Next Anchor in State.  After successful sync
        	// this is then written to persistence for negotiation of
        	// further syncs.
        	$state->setClientAnchorNext($type, $this->_metaAnchorNext);

        	$info = $state->getSyncSummary($this->_targetLocURI);
		#Horde::logMessage("SyncML: Anchor match, TwoWaySync sinceee " . $clientlast, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        	if (is_a($info, 'DataTreeObject')) {
        	    $x = $info->get('ClientAnchor');
        	    $clientlast = $x[$type];
        	    $x = $info->get('ServerAnchor');
        	    $state->setServerAnchorLast($type, $x[$type]);
        	} elseif (is_array($info)) {
        	    $clientlast = $info['ClientAnchor'];
        	    $state->setServerAnchorLast($type, $info['ServerAnchor']);
        	} else {
        	    $clientlast = false;
        	    $state->setServerAnchorLast($type, 0);
        	}
        	
        	Horde::logMessage('SyncML: checking anchor targetLocURI: clientlast: ' . $clientlast .' / '. $this->_metaAnchorLast, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        	
        	// Set Server Anchor for this sync to current time.
        	$state->setServerAnchorNext($type,time());
        	if ($clientlast !== false && $clientlast == $this->_metaAnchorLast) {
        	    // Last Sync Anchor matches, TwoWaySync will do.
        	    $code = RESPONSE_OK;
        	    Horde::logMessage("SyncML: Anchor match, TwoWaySync since " . $clientlast, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        	} else {
        	    Horde::logMessage("SyncML: Anchor mismatch, enforcing SlowSync clientlast $clientlast serverlast ".$this->_metaAnchorLast, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        	    // Mismatch, enforce slow sync.  508=RESPONSE_REFRESH_REQUIRED 201=ALERT_SLOW_SYNC
        	    $this->_alert = 201;
        	    $code = 508;  
        	    // create new synctype
		    $sync = &Horde_SyncML_Sync::factory($this->_alert);
		    $sync->_targetLocURI = $this->_targetLocURI;
		    $sync->_sourceLocURI = $this->_sourceLocURI;
		    if(isset($this->_targetLocURIParameters))
		    	$sync->_targetLocURIParameters = $this->_targetLocURIParameters;
		    $state->setSync($this->_targetLocURI, $sync);
		    // PH : no longer delete entire content_map entries for client before SlowSync,
		    //      use content_map to verify if content is mapped correctly
		    //$state->removeAllUID($this->_targetLocURI);
        	}

        	$status = new Horde_SyncML_Command_Status($code, 'Alert');
        	$status->setCmdRef($this->_cmdID);
        	if ($this->_sourceLocURI != null) {
        	    $status->setSourceRef($this->_sourceLocURI);
        	}
        	if ($this->_targetLocURI != null) {
        	    $status->setTargetRef((isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI));
        	}

        	// Mirror Next Anchor from client back to client.
        	if (isset($this->_metaAnchorNext)) {
        	    $status->setItemDataAnchorNext($this->_metaAnchorNext);
        	}

        	// Mirror Last Anchor from client back to client.
        	if (isset($this->_metaAnchorLast)) {
        	    $status->setItemDataAnchorLast($this->_metaAnchorLast);
        	}

        	$currentCmdID = $status->output($currentCmdID, $output);

        	if ($state->isAuthorized()) {
        	    $output->startElement($state->getURI(), 'Alert', $attrs);

        	    $output->startElement($state->getURI(), 'CmdID', $attrs);
        	    $chars = $currentCmdID;
        	    $output->characters($chars);
        	    $output->endElement($state->getURI(), 'CmdID');

        	    $output->startElement($state->getURI(), 'Data', $attrs);
        	    $chars = $this->_alert;
        	    $output->characters($chars);
        	    $output->endElement($state->getURI(), 'Data');

        	    $output->startElement($state->getURI(), 'Item', $attrs);

        	    if ($this->_sourceLocURI != null) {
	                $output->startElement($state->getURI(), 'Target', $attrs);
        	        $output->startElement($state->getURI(), 'LocURI', $attrs);
        	        $chars = $this->_sourceLocURI;
        	        $output->characters($chars);
        	        $output->endElement($state->getURI(), 'LocURI');
        	        $output->endElement($state->getURI(), 'Target');
        	    }
	
        	    if ($this->_targetLocURI != null) {
        	        $output->startElement($state->getURI(), 'Source', $attrs);
        	        $output->startElement($state->getURI(), 'LocURI', $attrs);
        	        $chars = (isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI);
        	        $output->characters($chars);
        	        $output->endElement($state->getURI(), 'LocURI');
        	        $output->endElement($state->getURI(), 'Source');
        	    }

        	    $output->startElement($state->getURI(), 'Meta', $attrs);

        	    $output->startElement($state->getURIMeta(), 'Anchor', $attrs);

        	    $output->startElement($state->getURIMeta(), 'Last', $attrs);
        	    $chars = $state->getServerAnchorLast($type);
        	    $output->characters($chars);
        	    $output->endElement($state->getURIMeta(), 'Last');

        	    $output->startElement($state->getURIMeta(), 'Next', $attrs);
        	    $chars = $state->getServerAnchorNext($type);
        	    $output->characters($chars);
        	    $output->endElement($state->getURIMeta(), 'Next');

        	    $output->endElement($state->getURIMeta(), 'Anchor');
        	    $output->endElement($state->getURI(), 'Meta');
        	    $output->endElement($state->getURI(), 'Item');
        	    $output->endElement($state->getURI(), 'Alert');

        	    // still needed? lars
                    $state->_sendFinal = true;
                    
        	    $currentCmdID++;

                    if($state->_devinfoRequested == false && 
			$this->_sourceLocURI != null && 
			is_a($state->getPreferedContentTypeClient($this->_sourceLocURI), 'PEAR_Error')) {
			
			$output->startElement($state->getURI(), 'Get', $attrs);

			$output->startElement($state->getURI(), 'CmdID', $attrs);
			$output->characters($currentCmdID);
			$currentCmdID++;
			$output->endElement($state->getURI(), 'CmdID');
			
			$output->startElement($state->getURI(), 'Meta', $attrs);
			$output->startElement($state->getURIMeta(), 'Type', $attrs);
			if(is_a($output, 'XML_WBXML_Encoder')) {
				$output->characters('application/vnd.syncml-devinf+wbxml');
			} else {
				$output->characters('application/vnd.syncml-devinf+xml');
			}
			$output->endElement($state->getURIMeta(), 'Type');
			$output->endElement($state->getURI(), 'Meta');
			
			$output->startElement($state->getURI(), 'Item', $attrs);
			$output->startElement($state->getURI(), 'Target', $attrs);
			$output->startElement($state->getURI(), 'LocURI', $attrs);
			$output->characters(($state->getVersion() == 0) ? './devinf10' : './devinf11');
			$output->endElement($state->getURI(), 'LocURI');
			$output->endElement($state->getURI(), 'Target');
			$output->endElement($state->getURI(), 'Item');
			
			$output->endElement($state->getURI(), 'Get');
			
			$state->_devinfoRequested = true;
		    }
        	}

	} elseif ($this->_alert == ALERT_NEXT_MESSAGE) {
        	$status = new Horde_SyncML_Command_Status(RESPONSE_OK, 'Alert');
        	$status->setCmdRef($this->_cmdID);
        	if ($this->_targetLocURI != null) {
        	    $status->setTargetRef((isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI));
        	}
        	if ($this->_sourceLocURI != null) {
        	    $status->setSourceRef($this->_sourceLocURI);
        	}
        	$status->setItemSourceLocURI($this->_sourceLocURI);
        	$status->setItemTargetLocURI(isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI);
        	$currentCmdID = $status->output($currentCmdID, $output);

        	$state->setAlert222Received(true);

	} else {
        	$status = new Horde_SyncML_Command_Status(RESPONSE_OK, 'Alert');
        	$status->setCmdRef($this->_cmdID);
        	if ($this->_sourceLocURI != null) {
        	    $status->setSourceRef($this->_sourceLocURI);
        	}
        	if ($this->_targetLocURI != null) {
        	    $status->setTargetRef((isset($this->_targetLocURIParameters) ? $this->_targetLocURI.'?/'.$this->_targetLocURIParameters : $this->_targetLocURI));
        	}

        	$currentCmdID = $status->output($currentCmdID, $output);
	}

        return $currentCmdID;
    }

    /**
     * Setter for property sourceURI.
     *
     * @param string $sourceURI  New value of property sourceURI.
     */
    function setSourceLocURI($sourceURI)
    {
        $this->_sourceLocURI = $sourceURI;
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
     // is this function still used???
    function setTargetURI($targetURI)
    {
        $this->_targetLocURI = $targetURI;
    }

    /**
     * Setter for property targetURI.
     *
     * @param string $targetURI  New value of property targetURI.
     */
    function setTargetLocURI($targetURI)
    {
        $this->_targetLocURI = $targetURI;
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch ($this->_xmlStack) {
        case 3:
            if ($element == 'Target') {
                $this->_isInSource = false;
            } else {
                $this->_isInSource = true;
            }
            break;
        }
    }

	function endElement($uri, $element)
	{
		switch ($this->_xmlStack) {
			case 1:
				$state = & $_SESSION['SyncML.state'];
				$sync = $state->getSync($this->_targetLocURI);
				
				if (!$sync && $this->_alert < ALERT_RESULT_ALERT) {
					Horde::logMessage('SyncML: create new sync for ' . $this->_targetLocURI . ' ' . $this->_alert, __FILE__, __LINE__, PEAR_LOG_DEBUG);
					$sync = &Horde_SyncML_Sync::factory($this->_alert);
					
					$sync->_targetLocURI = $this->_targetLocURI;
					$sync->_sourceLocURI = $this->_sourceLocURI;
					if(isset($this->_targetLocURIParameters)) {
						$sync->_targetLocURIParameters = $this->_targetLocURIParameters;
					}
					
					$state->setSync($this->_targetLocURI, $sync);

					if($this->_alert == ALERT_SLOW_SYNC || $this->_alert == ALERT_REFRESH_FROM_SERVER) {
						$state->removeAllUID($this->_targetLocURI);
					}
				}
				
				break;
				
			case 2:
				if ($element == 'Data') {
					$this->_alert = intval(trim($this->_chars));
				}
				
				break;
				
			case 4:
				if ($element == 'LocURI') {
					if ($this->_isInSource) {
						$this->_sourceLocURI = trim($this->_chars);
					} else {
						$targetLocURIData = explode('?/',trim($this->_chars));
						
						$this->_targetLocURI = $targetLocURIData[0];
						
						if(isset($targetLocURIData[1])) {
							$this->_targetLocURIParameters = $targetLocURIData[1];
						}
					}
				}
				
				break;
				
			case 5:
				if ($element == 'Next') {
					$this->_metaAnchorNext = trim($this->_chars);
				} else if ($element == 'Last') {
					$this->_metaAnchorLast = trim($this->_chars);
				}
				
				break;
		}
			
		parent::endElement($uri, $element);
	}

    function getAlert()
    {
        return $this->_alert;
    }

    function setAlert($alert)
    {
        $this->_alert = $alert;
    }

}
