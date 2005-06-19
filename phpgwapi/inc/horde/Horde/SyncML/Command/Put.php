<?php

include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Put.php,v 1.12 2004/07/02 19:24:44 chuck Exp $
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
class Horde_SyncML_Command_Put extends Horde_SyncML_Command {

    /**
     * @var string $_manufacturer
     */

    var $_manufacturer;

    /**
     * @var string $_model
     */

    var $_model;

    /**
     * @var string $_oem
     */

    var $_oem;

    /**
     * @var string $_softwareVersion
     */

    var $_softwareVersion;

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
    
    function output($currentCmdID, &$output )
    {
        $state = &$_SESSION['SyncML.state'];

        $status = &new Horde_SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Put');
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

    function startElement($uri, $element, $attrs)
    {
        #Horde::logMessage('SyncML: put startelement ' . $element, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        parent::startElement($uri, $element, $attrs);
    }

}
