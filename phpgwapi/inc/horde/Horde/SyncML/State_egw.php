<?php

include_once dirname(__FILE__).'/State.php';

class EGW_SyncML_State extends Horde_SyncML_State
{
    /**
     * Returns the timestamp (if set) of the last change to the
     * obj:guid, that was caused by the client. This is stored to
     * avoid mirroring these changes back to the client.
     */
    function getChangeTS($type, $guid)
    {
    	$mapID = $this->_locName . $this->_sourceURI . $type;
    	
    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$cols = array('map_timestamp');
    	
    	$where = array
    	(
    		'map_id'	=> $mapID,
    		'map_guid'	=> $guid,
    	);

    	Horde::logMessage('SyncML: getChangeTS for ' . $mapID .' / '. $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	
    	$db->select('egw_contentmap', $cols, $where, __LINE__, __FILE__);
    	
    	if($db->next_record())
    	{
    		Horde::logMessage('SyncML: getChangeTS changets is ' . $db->from_timestamp($db->f('map_timestamp')), __FILE__, __LINE__, PEAR_LOG_DEBUG);
    		return $db->from_timestamp($db->f('map_timestamp'));
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
        $deviceID = $this->_locName . $this->_sourceURI;
    	
    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$cols = array
    	(
    		'dev_dtdversion',
    		'dev_numberofchanges',
    		'dev_largeobjs',
    		'dev_swversion',
    		'dev_oem',
    		'dev_model',
    		'dev_manufacturer',
    		'dev_devicetype',
    		'dev_deviceid',
    		'dev_datastore',
    	);
    	
    	$where = array
    	(
    		'dev_id'	=> $deviceID,
    	);
    	
    	$db->select('egw_syncmldevinfo', $cols, $where, __LINE__, __FILE__);
    	
    	if($db->next_record())
    	{
    		$devInfo = array
    		(
    			'DTDVersion'			=> $db->f('dev_dtdversion'),
    			'supportNumberOfChanges'	=> $db->f('dev_numberofchanges'),
    			'supportLargeObjs'		=> $db->f('dev_largeobjs'),
    			'softwareVersion'		=> $db->f('dev_swversion'),
    			'oem'				=> $db->f('dev_oem'),
    			'model'				=> $db->f('dev_model'),
    			'manufacturer'			=> $db->f('dev_manufacturer'),
    			'deviceType'			=> $db->f('dev_devicetype'),
    			'deviceID'			=> $db->f('dev_deviceid'),
    			'dataStore'			=> unserialize($db->f('dev_datastore')),
    		);
    		
    		return $devInfo;
    	}
    	
    	return false;
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
    	
    	Horde::logMessage('SyncML: search GlobalUID for  ' . $mapID .' / '.$locid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	
    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$cols = array('map_guid');
    	
    	$where = array
    	(
    		'map_id'	=> $mapID,
    		'map_locuid'	=> $locid,
    		'map_expired'	=> 0,
    	);
    	
    	$db->select('egw_contentmap', $cols, $where, __LINE__, __FILE__);
    	
    	if($db->next_record())
    	{
    		return $db->f('map_guid');
    	}
    	
    	return false;
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
    	
    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$cols = array('map_locuid');
    	
    	$where = array
    	(
    		'map_id'	=> $mapID,
    		'map_guid'	=> $guid
    	);
    	
    	$db->select('egw_contentmap', $cols, $where, __LINE__, __FILE__);
    	
    	if($db->next_record())
    	{
    		return $db->f('map_locuid');
    	}
    	
    	return false;
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

    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$cols = array('sync_serverts','sync_clientts');
    	
    	$where = array
    	(
    		'dev_id'	=> $deviceID,
    		'sync_path'	=> $type
    	);
    	
    	$db->select('egw_syncmlsummary', $cols, $where, __LINE__, __FILE__);
    	
	Horde::logMessage("SyncML: get SYNCSummary for $deviceID", __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	if($db->next_record())
    	{
		Horde::logMessage("SyncML: get SYNCSummary for $deviceID serverts: ".$db->f('sync_serverts')."  clients: ".$db->f('sync_clientts'), __FILE__, __LINE__, PEAR_LOG_DEBUG);
    		$retData = array
    		(
    			'ClientAnchor'	=> $db->f('sync_clientts'),
    			'ServerAnchor'	=> $db->f('sync_serverts'),
    		);
    		return $retData;
    	}
    	
    	return false;
    	
    }

    function isAuthorized()
    {
	if (!$this->_isAuthorized) {
               
		if(!isset($this->_locName) && !isset($this->_password))
		{
			Horde::logMessage('SyncML: Authentication not possible currently. No username and password available' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
			return FALSE;
		}
                
                if(strstr($this->_locName,'@') === False)
                {
                	$this->_locName .= '@'.$GLOBALS['phpgw_info']['server']['default_domain'];
                }

		Horde::logMessage('SyncML: Authenticate ' . $this->_locName . ' - ' . $this->_password, __FILE__, __LINE__, PEAR_LOG_DEBUG);
                
		if($GLOBALS['sessionid'] = $GLOBALS['phpgw']->session->create($this->_locName,$this->_password,'text','u'))
		{
			$this->_isAuthorized = true;
			Horde::logMessage('SyncML_EGW: Authentication of ' . $this->_locName . '/' . $GLOBALS['sessionid'] . ' succeded' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
		}
		else
		{
			$this->_isAuthorized = false;
			Horde::logMessage('SyncML: Authentication of ' . $this->_locName . ' failed' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
		}
	}
	else
	{
		// store sessionID in a variable, because ->verify maybe resets that value
		$sessionID = session_id();
		if(!$GLOBALS['phpgw']->session->verify($sessionID, 'staticsyncmlkp3'))
			Horde::logMessage('SyncML_EGW: egw session('.$sessionID.') not verified ' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
	}

        return $this->_isAuthorized;
    }

    /**
     * Removes the locid<->guid mapping for the given locid. Returns
     * the guid that was removed or false if no mapping entry was
     * found.
     */
    function removeUID($type, $locid)
    {
    	$mapID = $this->_locName . $this->_sourceURI . $type;
    	
    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$cols = array('map_guid');
    	
    	$where = array
    	(
    		'map_id'	=> $mapID,
    		'map_locuid'	=> $locid
    	);
    	
    	$db->select('egw_contentmap', $cols, $where, __LINE__, __FILE__);
    	
    	if(!$db->next_record())
    	{
    		Horde::logMessage("SyncML: state->removeUID(type=$type,locid=$locid) : nothing to remove", __FILE__, __LINE__, PEAR_LOG_DEBUG);
    		return false;
    	}
    	
    	$guid = $db->f('map_guid');

        Horde::logMessage("SyncML:  state->removeUID(type=$type,locid=$locid) : removing guid:$guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
        
    	$db->delete('egw_contentmap', $where, __LINE__, __FILE__);
    	
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
    function setUID($type, $locid, $guid, $ts=0)
    {
    	// fix $guid, it maybe was to long for some devices
    	// format is appname-id-systemid
    	$guidParts = explode('-',$guid);
    	if(count($guidParts) == 3)
    	{
    		$guid = $GLOBALS['phpgw']->common->generate_uid($guidParts[0],$guidParts[1]);
    	}
    	
    	if($ts == 0)
    	{
    		$ts = time();
    	}
    	
	Horde::logMessage("SyncML: setUID $type, $locid, $guid, $ts ".count($guidParts), __FILE__, __LINE__, PEAR_LOG_DEBUG);

    	$db = clone($GLOBALS['phpgw']->db);
    	
    	$mapID = $this->_locName . $this->_sourceURI . $type;
    	
    	$where = array(
    		'map_id'	=> $mapID,
    		'map_guid'	=> $guid,
    	);
    	
    	$data = $where + array(
    		'map_locuid'	=> $locid,
    		'map_timestamp'	=> $ts,
    		'map_expired'	=> 0,
    	);

    	$db->insert('egw_contentmap', $data, $where, __LINE__, __FILE__);

	Horde::logMessage("SyncML: setUID $type, $locid, $guid, $ts $mapID", __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	
    }

    /**
     * write clients device info to database
     */
    function writeClientDeviceInfo()
    {
        if (!isset($this->_clientDeviceInfo) || !is_array($this->_clientDeviceInfo)) {
            return false;
        }

        $deviceID = $this->_locName . $this->_sourceURI;
        
        $data = array
        (
        	'dev_id'		=> $deviceID,
        	'dev_dtdversion' 	=> $this->_clientDeviceInfo['DTDVersion'],
        	'dev_numberofchanges'	=> $this->_clientDeviceInfo['supportNumberOfChanges'],
        	'dev_largeobjs'		=> $this->_clientDeviceInfo['supportLargeObjs'],
        	'dev_swversion'		=> $this->_clientDeviceInfo['softwareVersion'],
        	'dev_oem'		=> $this->_clientDeviceInfo['oem'],
        	'dev_model'		=> $this->_clientDeviceInfo['model'],
        	'dev_manufacturer'	=> $this->_clientDeviceInfo['manufacturer'],
        	'dev_devicetype'	=> $this->_clientDeviceInfo['deviceType'],
        	'dev_deviceid'		=> $this->_clientDeviceInfo['deviceID'],
        	'dev_datastore'		=> serialize($this->_clientDeviceInfo['dataStore']),
        );

        $where = array
        (
        	'dev_id'		=> $deviceID,
        );
        
    	$GLOBALS['phpgw']->db->insert('egw_syncmldevinfo',$data,$where);
    }

    /**
     * After a successful sync, the client and server's Next Anchors
     * are written to the database so they can be used to negotiate
     * upcoming syncs.
     */
    function writeSyncSummary()
    {
    	#parent::writeSyncSummary();
    	
        if (!isset($this->_serverAnchorNext) || !is_array($this->_serverAnchorNext)) {
            return;
        }
        
        $deviceID = $this->_locName . $this->_sourceURI;

        foreach((array)$this->_serverAnchorNext as $type => $a)
        {
		Horde::logMessage("SyncML: write SYNCSummary for $deviceID $type serverts: $a clients: ".$this->_clientAnchorNext[$type], __FILE__, __LINE__, PEAR_LOG_DEBUG);

        	$where = array
        	(
        		'dev_id'	=> $deviceID,
        		'sync_path'	=> $type,
        	);
        	
        	$data  = $where + array
        	(
        		'sync_serverts' => $a,
        		'sync_clientts' => $this->_clientAnchorNext[$type]
        	);
        	
        	$GLOBALS['phpgw']->db->insert('egw_syncmlsummary', $data, $where, __LINE__, __FILE__);
        }
    }


}

?>