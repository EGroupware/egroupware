<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Results.php,v 1.11 2004/07/02 19:24:44 chuck Exp $
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
class Horde_SyncML_Command_Results extends Horde_SyncML_Command {

    var $_cmdRef;
    var $_type;
    var $_data;
    var $_locSourceURI;
    var $_deviceInfo;

    function endElement($uri, $element)
    {
        #Horde::logMessage('SyncML: put endelement ' . $element . ' stack ' . $this->_xmlStack, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        switch ($this->_xmlStack) {
        	case 5:
        		switch($element) {
        			case 'DataStore':
        				$this->_deviceInfo['dataStore'][$this->_sourceReference] = array(
        					'maxGUIDSize'		=> $this->_maxGUIDSize,
        					'rxPreference'		=> $this->_rxPreference,
        					'txPreference'		=> $this->_txPreference,
        					'syncCapabilities'	=> $this->_syncCapabilities,
        				);
        				break;
        			case 'DevID':
        				$this->_deviceInfo['deviceID'] 		= trim($this->_chars);
        				break;
        			case 'DevTyp':
        				$this->_deviceInfo['deviceType']	= trim($this->_chars);
        				break;
        			case 'Man':
        				$this->_deviceInfo['manufacturer']	= trim($this->_chars);
        				break;
        			case 'Mod':
        				$this->_deviceInfo['model']		= trim($this->_chars);
        				break;
        			case 'OEM':
        				$this->_deviceInfo['oem']		= trim($this->_chars);
        				break;
        			case 'SwV':
        				$this->_deviceInfo['softwareVersion']	= trim($this->_chars);
        				break;
        			case 'SupportLargeObjs':
        				$this->_deviceInfo['supportLargeObjs']	= true;
        				break;
        			case 'SupportNumberOfChanges':
        				$this->_deviceInfo['supportNumberOfChanges'] = true;
        				break;
        			case 'VerDTD':
        				$this->_deviceInfo['DTDVersion']	= trim($this->_chars);
        				break;
        		}
        		break;
        	case 6:
        		switch($element) {
        			case 'MaxGUIDSize':
        				$this->_maxGUIDSize = trim($this->_chars);
        				break;
        			case 'Rx-Pref':
        				$this->_rxPreference = array(
        					'contentType'		=> $this->_contentType,
        					'contentVersion'	=> $this->_contentVersion,
        				);
        				break;
        			case 'SourceRef':
        				$this->_sourceReference = trim($this->_chars);
        				break;
        			case 'Tx-Pref':
        				$this->_txPreference = array(
        					'contentType'		=> $this->_contentType,
        					'contentVersion'	=> $this->_contentVersion,
        				);
        				break;
        		}
        		break;
        	case 7:
        		switch($element) {
        			case 'CTType':
        				$this->_contentType = trim($this->_chars);
        				break;
        			case 'SyncType':
        				$this->_syncCapabilities[] = trim($this->_chars);
        				break;
        			case 'VerCT':
        				$this->_contentVersion = trim($this->_chars);
        				break;
        		}
        		break;
        }
        
        parent::endElement($uri, $element);
    }
    
    function output($currentCmdID, &$output)
    {
	if(!isset($this->_locSourceURI))
	{	
		Horde::logMessage('SyncML: BIG TODO!!!!!!!!!!!!!!!!!! parse reply', __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state = &$_SESSION['SyncML.state'];
		
		$status = &new Horde_SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Results');
		$status->setCmdRef($this->_cmdID);
		
		$ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';
		
		$status->setSourceRef($ref);
		
		if($state->isAuthorized())
		{
			if(count((array)$this->_deviceInfo) > 0)
			{
				$state->setClientDeviceInfo($this->_deviceInfo);
				$state->writeClientDeviceInfo();
			}
		}
		
		return $status->output($currentCmdID, $output);
	}
	else
	{
		Horde::logMessage('SyncML: BIG TODO!!!!!!!!!!!!!!!!!! generate reponse', __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state = $_SESSION['SyncML.state'];
		
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
		$chars = $this->_cmdRef;
		$output->characters($chars);
		$output->endElement($state->getURI(), 'CmdRef');
		
		$output->startElement($state->getURI(), 'Meta', $attrs);
		$output->startElement($state->getURIMeta(), 'Type', $attrs);
		$output->characters($this->_type);
		$output->endElement($state->getURIMeta(), 'Type');
		$output->endElement($state->getURI(), 'Meta');
		
		$output->startElement($state->getURI(), 'Item', $attrs);
		$output->startElement($state->getURI(), 'Source', $attrs);
		$output->startElement($state->getURI(), 'LocURI', $attrs);
		$chars = $this->_locSourceURI;
		$output->characters($chars);
		$output->endElement($state->getURI(), 'LocURI');
		$output->endElement($state->getURI(), 'Source');
		
		$output->startElement($state->getURI(), 'Data', $attrs);
		
		// Need to send this information as opaque data so the WBXML
		// will understand it.
		$output->opaque($this->_data);
		
		$output->endElement($state->getURI(), 'Data');
		$output->endElement($state->getURI(), 'Item');
		
		$output->endElement($state->getURI(), 'Results');
		
		$currentCmdID++;
		
		return $currentCmdID;
	}
    }

    /**
     * Setter for property cmdRef.
     *
     * @param string $cmdRef  New value of property cmdRef.
     */
    function setCmdRef($cmdRef)
    {
        $this->_cmdRef = $cmdRef;
    }

    /**
     * Setter for property Type.
     *
     * @param string $type  New value of property type.
     */
    function setType($type)
    {
        $this->_type = $type;
    }

    /**
     * Setter for property data.
     *
     * @param string $data  New value of property data.
     */
    function setData($data)
    {
        $this->_data = $data;
    }

    /**
     * Setter for property locSourceURI.
     *
     * @param string $locSourceURI  New value of property locSourceURI.
     */
    function setlocSourceURI($locSourceURI)
    {
        $this->_locSourceURI = $locSourceURI;
    }

}
