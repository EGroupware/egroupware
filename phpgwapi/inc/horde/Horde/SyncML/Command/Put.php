<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Anthony Mills <amills@pyramid6.com>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Put extends Horde_SyncML_Command {

    /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Put';

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
     * @var array $_deviceInfo
     */

    var $_deviceInfo;

    /**
     * @var string $_softwareVersion
     */

    var $_softwareVersion;

	function endElement($uri, $element) {
		switch (count($this->_stack)) {
			case 5:
				switch ($element) {
					case 'DataStore':
						$this->_deviceInfo['dataStore'][$this->_sourceReference] = array (
							'maxGUIDSize'		=> $this->_maxGUIDSize,
							'rxPreference'		=> $this->_rxPreference,
							'txPreference'		=> $this->_txPreference,
							'syncCapabilities'	=> $this->_syncCapabilities,
							'properties'		=> $this->_properties,
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
						$this->_sourceReference = strtolower(trim($this->_chars));
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
								case 'card':
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

					case 'Property':
						if (isset($this->_PropName)) {
							$this->_properties[$this->_contentType][$this->_contentVersion][$this->_PropName] = array(
								'Size'		=>	$this->_PropSize,
								'NoTruncate'	=>	$this->_PropNoTruncate,
							);
						}
						break;
				}
				break;

			case 8:
				switch($element) {
					case 'PropName':
						$this->_PropName = trim($this->_chars);
						break;

					case 'Size':
						$this->_PropSize = trim($this->_chars);
						break;

					case 'NoTruncate':
						$this->_PropNoTruncate = true;
						break;
				}
				beak;
		}

		parent::endElement($uri, $element);
	}

	function finalizeDeviceInfo()
	{
		// get some more information about the device from out of band data

		$ua = $_SERVER['HTTP_USER_AGENT'];

		if (($pos = strpos($ua, 'Funambol'))!== false) {
			$this->_deviceInfo['manufacturer'] = 'Funambol';
			$this->_deviceInfo['model'] = 'generic';
			$this->_deviceInfo['softwareVersion'] = 3.1; // force special treatment
			$type = substr($ua, $pos + 9);
			if (preg_match("/^(.*) [^\d]*(\d+\.?\d*)[\.|\d]*\s*$/i", $type, $matches)) {
				// Funambol uses the hardware Manufacturer we don't care about
				$this->_deviceInfo['model'] = trim($matches[1]);
				$this->_deviceInfo['softwareVersion'] = floatval($matches[2]);
			}
			if (!isset($this->_deviceInfo['deviceType'])) {
				switch (strtolower(trim($matches[1]))) {
					case 'pocket pc plug-in':
						$this->_deviceInfo['deviceType'] = 'windowsmobile';
						break;
					case 'outlook plug-in':
					default:
						$this->_deviceInfo['deviceType'] = 'workstation';
					break;
				}
			}

		}

		switch (strtolower($this->_deviceInfo['deviceID'])) {
			case 'fmz-thunderbird-plugin':
				if (empty($this->_devinceInfo['manufacturer'])) {
					$this->_deviceInfo['manufacturer'] = 'Funambol';
				}
				if (empty($this->_devinceInfo['model'])) {
					$this->_deviceInfo['model'] = 'ThunderBird';
				}
				if (empty($this->_devinceInfo['softwareVersion'])) {
					$this->_deviceInfo['softwareVersion']	= '3.0';
				}
				break;
		}

		if (preg_match('/Funambol.*/i', $this->_deviceInfo['manufacturer'])) {
			$this->_deviceInfo['supportLargeObjs'] = true;
		}

		switch (strtolower($this->_deviceInfo['manufacturer'])) {
			case 'sonyericsson':
			case 'sony ericsson':
				if (strtolower($this->_deviceInfo['model']) == 'w890i') {
					$this->_deviceInfo['supportLargeObjs'] = false;
				}
				break;
			case 'synthesis ag':
				foreach ($this->_deviceInfo['dataStore'] as &$ctype) {
					$ctype['maxGUIDSize'] = 255;
				}
				break;
		}
	}

	function output($currentCmdID, &$output ) {
		$state = &$_SESSION['SyncML.state'];

		$status = new Horde_SyncML_Command_Status((($state->isAuthorized()) ? RESPONSE_OK : RESPONSE_INVALID_CREDENTIALS), $this->_cmdName);
		$status->setCmdRef($this->_cmdID);

        if ($state->getVersion() == 2) {
		    $ref = './devinf12';
        } elseif ($state->getVersion() == 1) {
		    $ref = './devinf11';
        } else {
		    $ref = './devinf10';
        }

		$status->setSourceRef($ref);

		if($state->isAuthorized()) {
			$this->finalizeDeviceInfo();

			if(count((array)$this->_deviceInfo) > 0) {
				$devInfo = $state->getClientDeviceInfo();
				if (is_array($devInfo['dataStore'])
					&& $devInfo['softwareVersion'] == $this->_deviceInfo['softwareVersion']) {
					// merge with existing information
					$devInfo['dataStore'] =
						array_merge($devInfo['dataStore'],
							$this->_deviceInfo['dataStore']);
				} else {
					// new device
					$devInfo = $this->_deviceInfo;
				}
	            #Horde::logMessage("SyncML: Put DeviceInfo:\n" . print_r($this->_deviceInfo, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);
				$state->setClientDeviceInfo($devInfo);
				$state->writeClientDeviceInfo();
			}
		}

		return $status->output($currentCmdID, $output);
	}

	function startElement($uri, $element, $attrs) {
	       	#Horde::logMessage("SyncML: startElement[" . count($this->_stack) . "] $uri $element", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		switch (count($this->_stack)) {
			case 4:
				switch ($element) {
					case 'DataStore':
						$this->_properties = array();
						break;
				}
				break;

			case 6:
				switch ($element) {
					case 'Property':
						unset($this->_PropName);
						$this->_PropSize = -1;
						$this->_PropNoTruncate = false;
						break;
				}
				break;
		}
		parent::startElement($uri, $element, $attrs);
	}

}
