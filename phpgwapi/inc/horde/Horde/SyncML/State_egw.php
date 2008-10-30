<?php
/**
 * eGroupWare SyncML
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @package syncml
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once dirname(__FILE__).'/State.php';

/**
 * The EGW_SyncML_State class provides a SyncML state object.
 */
class EGW_SyncML_State extends Horde_SyncML_State
{
	var $table_devinfo	= 'egw_syncmldevinfo';

	/*
	 * store the mappings of egw uids to client uids
	 */
	var $uidMappings	= array();

    /**
     * Returns the timestamp (if set) of the last change to the
     * obj:guid, that was caused by the client. This is stored to
     * avoid mirroring these changes back to the client.
     */
    function getChangeTS($type, $guid)
    {
    	$mapID = $this->_locName . $this->_sourceURI . $type;

    	#Horde::logMessage('SyncML: getChangeTS for ' . $mapID .' / '. $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

    	if ($ts = $GLOBALS['egw']->db->select('egw_contentmap', 'map_timestamp', array(
    		'map_id'	=> $mapID,
    		'map_guid'	=> $guid,
    	), __LINE__, __FILE__, false, '', 'syncml')->fetchSingle())
	    {
    		#Horde::logMessage('SyncML: getChangeTS changets is ' . $GLOBALS['egw']->db->from_timestamp($ts), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			return $GLOBALS['egw']->db->from_timestamp($ts);
	    }
     	return false;
    }

	/**
	* Retrieves information about the clients device info if any. Returns
	* false if no info found or a DateTreeObject with at least the
	* following attributes:
	*
	* a array containing all available infos about the device
	*/
	function getClientDeviceInfo()
	{
		if(($deviceID = $GLOBALS['egw']->db->select('egw_syncmldeviceowner', 'owner_devid',array (
			'owner_locname'		=> $this->_locName,
			'owner_deviceid'	=> $this->_sourceURI,
		), __LINE__, __FILE__, false, '', 'syncml')->fetchSingle()))
		{
			$cols = array(
				'dev_dtdversion',
				'dev_numberofchanges',
				'dev_largeobjs',
				'dev_swversion',
				'dev_fwversion',
				'dev_hwversion',
				'dev_oem',
				'dev_model',
				'dev_manufacturer',
				'dev_devicetype',
				'dev_datastore',
				'dev_utc',
			);

			$where = array(
				'dev_id'	=> $deviceID,
			);

			if (($row = $GLOBALS['egw']->db->select('egw_syncmldevinfo', $cols, $where, __LINE__, __FILE__, false, '', 'syncml')->fetch()))
			{
				return array (
					'DTDVersion'			=> $row['dev_dtdversion'],
					'supportNumberOfChanges'=> $row['dev_numberofchanges'],
					'supportLargeObjs'		=> $row['dev_largeobjs'],
					'UTC'					=> $row['dev_utc'],
					'softwareVersion'		=> $row['dev_swversion'],
					'hardwareVersion'		=> $row['dev_hwversion'],
					'firmwareVersion'		=> $row['dev_fwversion'],
					'oem'					=> $row['dev_oem'],
					'model'					=> $row['dev_model'],
					'manufacturer'			=> $row['dev_manufacturer'],
					'deviceType'			=> $row['dev_devicetype'],
					'dataStore'				=> unserialize($row['dev_datastore']),
				);
			}
		}

		return false;
	}

	/**
 	 * returns GUIDs of all client items
 	 */
	function _getClientItems($type)
	{
		$mapID = $this->_locName . $this->_sourceURI . $type;

    	$guids = array();
    	foreach($GLOBALS['egw']->db->select('egw_contentmap', 'map_guid', array(
    		'map_id'		=> $mapID,
    		'map_expired'	=> 0,
    	), __LINE__, __FILE__, false, '', 'syncml') as $row)
    	{
    		$guids[] = $row['map_guid'];
    	}
    	return $guids ? $guids : false;
	}

    /**
     * Retrieves the Horde server guid (like
     * kronolith:0d1b415fc124d3427722e95f0e926b75) for a given client
     * locid. Returns false if no such id is stored yet.
     *
     * Opposite of getLocId which returns the locid for a given guid.
     */
    function getGlobalUID($type, $locid)
    {
    	$mapID = $this->_locName . $this->_sourceURI . $type;

    	#Horde::logMessage('SyncML: search GlobalUID for  ' . $mapID .' / '.$locid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

     	return $GLOBALS['egw']->db->select('egw_contentmap', 'map_guid', array(
    		'map_id'		=> $mapID,
    		'map_locuid'	=> $locid,
    		'map_expired'	=> 0,
    	), __LINE__, __FILE__, false, '', 'syncml')->fetchSingle();
    }

    /**
     * Converts a EGW GUID (like
     * kronolith:0d1b415fc124d3427722e95f0e926b75) to a client ID as
     * used by the sync client (like 12) returns false if no such id
     * is stored yet.
     */
    function getLocID($type, $guid)
    {
    	$mapID = $this->_locName . $this->_sourceURI . $type;

    	Horde::logMessage('SyncML: search LocID for  ' . $mapID .' / '.$guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

    	if (($locuid = $GLOBALS['egw']->db->select('egw_contentmap', 'map_locuid', array(
    		'map_id'	=> $mapID,
    		'map_guid'	=> $guid
    	), __LINE__, __FILE__, false, '', 'syncml')->fetchSingle()))
    	{
	   		Horde::logMessage('SyncML: found LocID: '.$locuid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	}
    	return $locuid;
    }

    /**
     * Retrieves information about the previous sync if any. Returns
     * false if no info found or a DateTreeObject with at least the
     * following attributes:
     *
     * ClientAnchor: the clients Next Anchor of the previous sync.
     * ServerAnchor: the Server Next Anchor of the previous sync.
     */
    function getSyncSummary($type)
    {
		$deviceID = $this->_locName . $this->_sourceURI;

		#Horde::logMessage("SyncML: get SYNCSummary for $deviceID", __FILE__, __LINE__, PEAR_LOG_DEBUG);

    	if (($row = $GLOBALS['egw']->db->select('egw_syncmlsummary', array('sync_serverts','sync_clientts'), array(
    		'dev_id'	=> $deviceID,
    		'sync_path'	=> $type
    	), __LINE__, __FILE__, false, '', 'syncml')->fetch()))
    	{
			#Horde::logMessage("SyncML: get SYNCSummary for $deviceID serverts: ".$row['sync_serverts']."  clients: ".$row['sync_clientts'], __FILE__, __LINE__, PEAR_LOG_DEBUG);
    		return array(
    			'ClientAnchor'	=> $row['sync_clientts'],
    			'ServerAnchor'	=> $row['sync_serverts'],
    		);
    	}
    	return false;
    }

	function isAuthorized()
	{
		if (!$this->_isAuthorized)
		{
			if(!isset($this->_locName) && !isset($this->_password))
			{
				Horde::logMessage('SyncML: Authentication not yet possible currently. Username and password not available' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
				return FALSE;
			}

			if(!isset($this->_password))
			{
				Horde::logMessage('SyncML: Authentication not yet possible currently. Password not available' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
				return FALSE;
			}

			if(strpos($this->_locName,'@') === False)
			{
				$this->_locName .= '@'.$GLOBALS['egw_info']['server']['default_domain'];
			}

			#Horde::logMessage('SyncML: authenticate with username: ' . $this->_locName . ' and password: ' . $this->_password, __FILE__, __LINE__, PEAR_LOG_DEBUG);

			if($GLOBALS['sessionid'] = $GLOBALS['egw']->session->create($this->_locName,$this->_password,'text'))
			{
				$this->_isAuthorized = true;
				Horde::logMessage('SyncML_EGW: Authentication of ' . $this->_locName . '/' . $GLOBALS['sessionid'] . ' succeded' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
			}
			else
			{
				$this->_isAuthorized = false;
				Horde::logMessage('SyncML: Authentication of ' . $this->_locName . ' failed' , __FILE__, __LINE__, PEAR_LOG_INFO);
			}
		}
		else
		{
			// store sessionID in a variable, because ->verify maybe resets that value
			$sessionID = session_id();
			if(!$GLOBALS['egw']->session->verify($sessionID, 'staticsyncmlkp3')) {
				Horde::logMessage('SyncML_EGW: egw session(' .$sessionID. ') not verified ' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
			}
		}

		return $this->_isAuthorized;
	}

	/**
	* Removes all locid<->guid mappings for the given type.
	* Returns always true.
	*/
	function removeAllUID($type)
	{
		$mapID = $this->_locName . $this->_sourceURI . $type;

		Horde::logMessage("SyncML: state->removeAllUID(type=$type)", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$GLOBALS['egw']->db->delete('egw_contentmap', array('map_id' => $mapID), __LINE__, __FILE__, 'syncml');

		return true;
	}

	/**
	* Removes the locid<->guid mapping for the given locid. Returns
	* the guid that was removed or false if no mapping entry was
	* found.
	*/
	function removeUID($type, $locid)
	{
		$mapID = $this->_locName . $this->_sourceURI . $type;

		$where = array (
			'map_id'	=> $mapID,
			'map_locuid'	=> $locid
		);

		if (!($guid = $GLOBALS['egw']->db->select('egw_contentmap', 'map_guid', $where, __LINE__, __FILE__, false, '', 'syncml')->fetchSingle()))
		{
			Horde::logMessage("SyncML: state->removeUID(type=$type,locid=$locid) : nothing to remove", __FILE__, __LINE__, PEAR_LOG_INFO);
			return false;
		}

		Horde::logMessage("SyncML:  state->removeUID(type=$type,locid=$locid) : removing guid:$guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$GLOBALS['egw']->db->delete('egw_contentmap', $where, __LINE__, __FILE__, 'syncml');

		return $guid;
	}

    /**
     * Puts a given client $locid and Horde server $guid pair into the
     * map table to allow mapping between the client's and server's
     * IDs.  Actually there are two maps: from the localid to the guid
     * and vice versa.  The localid is converted to a key as follows:
     * this->_locName . $this->_sourceURI . $type . $locid so you can
     * have different syncs with different devices.  If an entry
     * already exists, it is overwritten.
     */
    function setUID($type, $locid, $_guid, $ts=0)
    {
    	#Horde::logMessage("SyncML: setUID $type, $locid, $guid, $ts ", __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	#Horde::logMessage("SyncML: setUID ". $this->getUIDMapping($guid), __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	// fix $guid, it maybe was to long for some devices
    	// format is appname-id-systemid
    	#$guidParts = explode('-',$guid);
    	#if(count($guidParts) == 3) {
    	#	$guid = $GLOBALS['egw']->common->generate_uid($guidParts[0],$guidParts[1]);
    	#}

    	// problem: entries created from client, come here with the (long) server guid,
    	// but getUIDMapping does not know them and can not map server-guid <--> client guid
    	$guid = $this->getUIDMapping($_guid);
    	if($guid === false)
    	{
    	    Horde::logMessage("SyncML: setUID $type, $locid, $guid something went wrong!!! Mapping not found.", __FILE__, __LINE__, PEAR_LOG_INFO);
    	    $guid = $_guid;
    	    //return false;
    	}
    	Horde::logMessage("SyncML: setUID $_guid => $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

    	if(!$ts) $ts = time();

		Horde::logMessage("SyncML: setUID $type, $locid, $guid, $ts ", __FILE__, __LINE__, PEAR_LOG_DEBUG);

    	$mapID = $this->_locName . $this->_sourceURI . $type;

    	// delete all client id's
    	$where = array(
    		'map_id'	=> $mapID,
    		'map_locuid'	=> $locid,
    	);
    	$GLOBALS['egw']->db->delete('egw_contentmap', $where, __LINE__, __FILE__, 'syncml');

    	// delete all egw id's
    	$where = array(
    		'map_id'	=> $mapID,
    		'map_guid'	=> $guid,
    	);
    	$GLOBALS['egw']->db->delete('egw_contentmap', $where, __LINE__, __FILE__, 'syncml');

    	$data = $where + array(
    		'map_locuid'	=> $locid,
    		'map_timestamp'	=> $ts,
    		'map_expired'	=> 0,
    	);
    	$GLOBALS['egw']->db->insert('egw_contentmap', $data, $where, __LINE__, __FILE__, 'syncml');

		#Horde::logMessage("SyncML: setUID $type, $locid, $guid, $ts $mapID", __FILE__, __LINE__, PEAR_LOG_DEBUG);
    }

	/**
	* writes clients deviceinfo into database
	*/
	function writeClientDeviceInfo()
	{
		if (!isset($this->_clientDeviceInfo) || !is_array($this->_clientDeviceInfo))
		{
			return false;
		}

		if(!isset($this->size_dev_hwversion))
		{
			$tableDefDevInfo = $GLOBALS['egw']->db->get_table_definitions('syncml',$this->table_devinfo);
			$this->size_dev_hwversion = $tableDefDevInfo['fd']['dev_hwversion']['precision'];
			unset($tableDefDevInfo);
		}

		$softwareVersion = !empty($this->_clientDeviceInfo['softwareVersion']) ? $this->_clientDeviceInfo['softwareVersion'] : '';
		$hardwareVersion = !empty($this->_clientDeviceInfo['hardwareVersion']) ? substr($this->_clientDeviceInfo['hardwareVersion'], 0, $this->size_dev_hwversion) : '';
		$firmwareVersion = !empty($this->_clientDeviceInfo['firmwareVersion']) ? $this->_clientDeviceInfo['firmwareVersion'] : '';

		$where = array(
			'dev_model'		=> $this->_clientDeviceInfo['model'],
			'dev_manufacturer'	=> $this->_clientDeviceInfo['manufacturer'],
			'dev_swversion'		=> $softwareVersion,
			'dev_hwversion'		=> $hardwareVersion,
			'dev_fwversion'		=> $firmwareVersion,
		);

		if (($deviceID = $GLOBALS['egw']->db->select('egw_syncmldevinfo', 'dev_id', $where, __LINE__, __FILE__, false, '', 'syncml')->fetchSingle()))
		{
			$data = array (
				'dev_datastore'		=> serialize($this->_clientDeviceInfo['dataStore']),
			);
			$GLOBALS['egw']->db->update('egw_syncmldevinfo', $data, $where, __LINE__, __FILE__, 'syncml');
		}
		else
		{
			$data = array (
				'dev_dtdversion' 	=> $this->_clientDeviceInfo['DTDVersion'],
				'dev_numberofchanges'	=> $this->_clientDeviceInfo['supportNumberOfChanges'] ? true : false,
				'dev_largeobjs'		=> $this->_clientDeviceInfo['supportLargeObjs'] ? true : false,
				'dev_utc'		=> $this->_clientDeviceInfo['UTC'] ? true : false,
				'dev_swversion'		=> $softwareVersion,
				'dev_hwversion'		=> $hardwareVersion,
				'dev_fwversion'		=> $firmwareVersion,
				'dev_oem'		=> $this->_clientDeviceInfo['oem'],
				'dev_model'		=> $this->_clientDeviceInfo['model'],
				'dev_manufacturer'	=> $this->_clientDeviceInfo['manufacturer'],
				'dev_devicetype'	=> $this->_clientDeviceInfo['deviceType'],
				'dev_datastore'		=> serialize($this->_clientDeviceInfo['dataStore']),
			);
			$GLOBALS['egw']->db->insert('egw_syncmldevinfo', $data, $where, __LINE__, __FILE__, 'syncml');

			$deviceID = $GLOBALS['egw']->db->get_last_insert_id('egw_syncmldevinfo', 'dev_id');
		}

		$data = array (
			'owner_locname'		=> $this->_locName,
			'owner_deviceid'	=> $this->_sourceURI,
			'owner_devid'		=> $deviceID,
		);
		$where = array (
			'owner_locname'		=> $this->_locName,
			'owner_deviceid'	=> $this->_sourceURI,
		);
		$GLOBALS['egw']->db->insert('egw_syncmldeviceowner', $data, $where, __LINE__, __FILE__, 'syncml');
	}

    /**
     * After a successful sync, the client and server's Next Anchors
     * are written to the database so they can be used to negotiate
     * upcoming syncs.
     */
    function writeSyncSummary()
    {
    	#parent::writeSyncSummary();

        if (!isset($this->_serverAnchorNext) || !is_array($this->_serverAnchorNext))
        {
            return;
        }

        $deviceID = $this->_locName . $this->_sourceURI;

        foreach((array)$this->_serverAnchorNext as $type => $a)
        {
			Horde::logMessage("SyncML: write SYNCSummary for $deviceID $type serverts: $a clients: ".$this->_clientAnchorNext[$type], __FILE__, __LINE__, PEAR_LOG_DEBUG);

        	$where = array(
        		'dev_id'	=> $deviceID,
        		'sync_path'	=> $type,
        	);

        	$data  = array(
        		'sync_serverts' => $a,
        		'sync_clientts' => $this->_clientAnchorNext[$type]
        	);

        	$GLOBALS['egw']->db->insert('egw_syncmlsummary', $data, $where, __LINE__, __FILE__, 'syncml');
        }
    }
}
