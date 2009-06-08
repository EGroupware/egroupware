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

	function endElement($uri, $element) {
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
					
					case 'FwV':
						$this->_deviceInfo['firmwareVersion']	= trim($this->_chars);
						break;
						
					case 'HwV':
						$this->_deviceInfo['hardwareVersion']	= trim($this->_chars);
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
					
					case 'UTC':
						$this->_deviceInfo['UTC']		= true;
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
						if (substr($this->_contentType, 0, 14) == "text/x-s4j-sif")
						{
							// workaround a little bug in sync4j for mobile v3.1.3 (and possibly others)
							// where the content-type is set to just one value regardless of
							// the source... this further leads to a failure to send updates
							// by the server since it does not know how to convert say tasks to text/x-s4j-sifc
							// (it should be text/x-s4j-sift).
							switch ($this->_sourceReference)
							{
								case 'contact':
									if ($this->_contentType != "text/x-s4j-sifc")
									{
										error_log("forcing 'contact' content type to 'text/x-s4j-sifc' instead of '".$this->_contentType."'");
										$this->_contentType = "text/x-s4j-sifc";
									}
									break;
								case 'calendar':
								case 'appointment':
									if ($this->_contentType != "text/x-s4j-sife")
									{
										error_log("forcing 'calendar' content type to 'text/x-s4j-sife' instead of '".$this->_contentType."'");
										$this->_contentType = "text/x-s4j-sife";
									}
									break;
								case 'task':
									if ($this->_contentType != "text/x-s4j-sift")
									{
										error_log("forcing 'task' content type to 'text/x-s4j-sift' instead of '".$this->_contentType."'");
										$this->_contentType = "text/x-s4j-sift";
									}
									break;
								case 'note':
									if ($this->_contentType != "text/x-s4j-sifn")
									{
										error_log("forcing 'note' content type to 'text/x-s4j-sifn' instead of '".$this->_contentType."'");
										$this->_contentType = "text/x-s4j-sifn";
									}
									break;
								default:
									#error_log("Leaving ContentType='".$this->_contentType."' as is for source '".$this->_sourceReference."'");
									break;
							}
						}
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
    
	function finalizeDeviceInfo()
	{
		// get some more information about the device from out of band data

		$ua = $_SERVER['HTTP_USER_AGENT'];

		if (preg_match("/^\s*Funambol (.*) (\d+\.\d+\.\d+)\s*$/i", $ua, $matches))
		{
			if (!isset($this->_deviceInfo['manufacturer']))
				$this->_deviceInfo['manufacturer'] = 'Funambol';
			if (!isset($this->_deviceInfo['model']))
				$this->_deviceInfo['model'] = 'Funambol ' . trim($matches[1]);
			if (!isset($this->_deviceInfo['softwareVersion']))
				$this->_deviceInfo['softwareVersion'] = $matches[2];

			if (!isset($this->_deviceInfo['deviceType']))
			{
				switch (strtolower(trim($matches[1])))
				{
					case 'outlook plug-in':
					default:
						$this->_deviceInfo['deviceType'] = 'workstation';
						break;
					case 'pocket pc plug-in':
						$this->_deviceInfo['deviceType'] = 'windowsmobile';
						break;
				}
			}
		}

		$devid = $this->_deviceInfo['deviceID'];
		switch (strtolower($devid))
		{
			case 'fmz-thunderbird-plugin':
				if (empty($this->_devinceInfo['manufacturer']))
					$this->_deviceInfo['manufacturer'] = 'Funambol';
				if (empty($this->_devinceInfo['model']))
					$this->_deviceInfo['model'] = 'ThunderBird';
				if (empty($this->_devinceInfo['softwareVersion']))
					$this->_deviceInfo['softwareVersion']	= '0.3';
				break;
		}
	}
	
	function output($currentCmdID, &$output ) {
		$state = &$_SESSION['SyncML.state'];
		
		$status = new Horde_SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), 'Put');
		$status->setCmdRef($this->_cmdID);
		
		$ref = ($state->getVersion() == 0) ? './devinf10' : './devinf11';
		
		$status->setSourceRef($ref);
		
		if($state->isAuthorized()) {
			$this->finalizeDeviceInfo();
			if(count((array)$this->_deviceInfo) > 0) {
				$state->setClientDeviceInfo($this->_deviceInfo);
				$state->writeClientDeviceInfo();
			}
		}
		
		return $status->output($currentCmdID, $output);
	}

	function startElement($uri, $element, $attrs) {
		parent::startElement($uri, $element, $attrs);
	}

}
