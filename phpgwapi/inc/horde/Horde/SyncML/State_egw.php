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
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Joerg Lehrke <jlehrke@noc.de>
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

	/*
	 * the domain of the current user
	 */
	var $_account_domain = 'default';

	/**
	 * get the local content id from a syncid
	 *
	 * @param sting $_syncid id used in syncml
	 * @return int local egw content id
	 */
	function get_egwID($_syncid) {
		$syncIDParts = explode('-',$_syncid);
		array_shift($syncIDParts);
		$_id = implode ('', $syncIDParts);
		return $_id;
	}

	/**
	 * when got a entry last added/modified/deleted
	 *
	 * @param $_syncid containing appName-contentid
	 * @param $_action string can be add, delete or modify
	 * @return string the last timestamp
	 */
	function getSyncTSforAction($_syncid, $_action)	{
		$syncIDParts = explode('-',$_syncid);
		$_appName = array_shift($syncIDParts);
		$_id = implode ('', $syncIDParts);

		$ts = $GLOBALS['egw']->contenthistory->getTSforAction($_appName, $_id, $_action);

		if (strstr($_id, ':')) {
			// pseudo entries are related to parent entry
			$parentId =  array_shift(explode(':', $_id));
			$pts = $GLOBALS['egw']->contenthistory->getTSforAction($_appName, $parentId, $_action);
			if ($pts > $ts) $ts = $pts; // We have changed the parent
		}
		return $ts;
	}

	/**
	 * get the timestamp for action
	 *
	 * find which content changed since $_ts for application $_appName
	 *
	 * @param string$_appName the appname example: infolog_notes
	 * @param string $_action can be modify, add or delete
	 * @param string $_ts timestamp where to start searching from
	 * @param array $readableItems	(optional) readable items of current user
	 * @return array containing syncIDs with changes
	 */
	function getHistory($_appName, $_action, $_ts, $readableItems = false) {
		$guidList = array();
		$syncIdList = array();
		$userItems = false;
		if (is_array($readableItems))
		{
			$userItems = array();
			foreach($readableItems as $guid)
			{
				if (preg_match('/.*-(\d+)(:(\d+))?/', $guid, $matches))
				{
					// We use only the real items' ids
					$userItems[] = $matches[1];
				}
			}
			$userItems = array_unique($userItems);
		}
		$idList = $GLOBALS['egw']->contenthistory->getHistory($_appName, $_action, $_ts, $userItems);
		foreach ($idList as $idItem)
		{
			if ($idItem) // ignore inconsistent entries
			{
				$syncIdList[] = $_appName . '-' . $idItem;
			}
		}
		return $syncIdList;
	}

	/**
	 * Returns the timestamp (if set) of the last change to the
	 * obj:guid, that was caused by the client. This is stored to
	 * avoid mirroring these changes back to the client.
	 */
	function getChangeTS($type, $guid) {
		$mapID = $this->_locName . $this->_sourceURI . $type;

		#Horde::logMessage('SyncML: getChangeTS for ' . $mapID
		#	. ' / '. $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if ($ts = $GLOBALS['egw']->db->select('egw_contentmap', 'map_timestamp',
			array(
				'map_id'	=> $mapID,
				'map_guid'	=> $guid,
			), __LINE__, __FILE__, false, '', 'syncml')->fetchColumn()) {
			#Horde::logMessage('SyncML: getChangeTS changets is '
			#	. $GLOBALS['egw']->db->from_timestamp($ts),
			#	__FILE__, __LINE__, PEAR_LOG_DEBUG);
			return $GLOBALS['egw']->db->from_timestamp($ts);
		}
		return false;
	}

	/**
	 * Returns the exceptions for a GUID which the client knows of
	 */
	function getGUIDExceptions($type, $guid) {
		$mapID = $this->_locName . $this->_sourceURI . $type;

		#Horde::logMessage('SyncML: getChangeTS for ' . $mapID
		#	. ' / '. $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$guid_exceptions = array();
		$where = array ('map_id'	=> $mapID,);
		$where[] = "map_guid LIKE '$guid" . ":%'";

		// Fetch all exceptions which the client knows of
		foreach ($GLOBALS['egw']->db->select('egw_contentmap', 'map_guid', $where,
			__LINE__,__FILE__, false, '', 'syncml') as $row)
		{
			$parts = preg_split('/:/', $row['map_guid']);
			$Id = $parts[0];
			$extension = $parts[1];
			$guid_exceptions[$extension] = $row['map_guid'];
		}
		return $guid_exceptions;
	}

	/**
	 * Retrieves information about the clients device info if any. Returns
	 * false if no info found or a DateTreeObject with at least the
	 * following attributes:
	 *
	 * a array containing all available infos about the device
	 */
	function getClientDeviceInfo() {
		#Horde::logMessage("SyncML: getClientDeviceInfo " . $this->_locName
		#	. ", " . $this->_sourceURI, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$syncml_prefs = $GLOBALS['egw_info']['user']['preferences']['syncml'];
		$deviceMaxEntries = 'maxEntries-' . $this->_clientDeviceInfo['devId'];
		$deviceUIDExtension = 'uidExtension-' . $this->_clientDeviceInfo['devId'];
		$deviceNonBlockingAllday = 'nonBlockingAllday-' . $this->_clientDeviceInfo['devId'];
		$deviceTimezone = 'tzid-' . $this->_clientDeviceInfo['devId'];
		$deviceCharSet = 'charset-' . $this->_clientDeviceInfo['devId'];
		if (isset($this->_clientDeviceInfo)
				&& is_array($this->_clientDeviceInfo)) {
			// update user preferences
			$this->_clientDeviceInfo['maxEntries'] = $syncml_prefs[$deviceMaxEntries];
			$this->_clientDeviceInfo['uidExtension'] = $syncml_prefs[$deviceUIDExtension];
			$this->_clientDeviceInfo['nonBlockingAllday'] = $syncml_prefs[$deviceNonBlockingAllday];
			$this->_clientDeviceInfo['tzid'] = $syncml_prefs[$deviceTimezone];
			$this->_clientDeviceInfo['charset'] = $syncml_prefs[$deviceCharSet];
			// use cached information
			return $this->_clientDeviceInfo;
		}
		if (!($deviceID = $GLOBALS['egw']->db->select('egw_syncmldeviceowner',
			'owner_devid',
			array (
				'owner_locname'		=> $this->_locName,
				'owner_deviceid'	=> $this->_sourceURI,
			), __LINE__, __FILE__, false, '', 'syncml')->fetchColumn())) {
			return false;
		}

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

		#Horde::logMessage("SyncML: getClientDeviceInfo $deviceID", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$where = array(
			'dev_id'		=> $deviceID,
		);



		if (($row = $GLOBALS['egw']->db->select('egw_syncmldevinfo',
			$cols, $where, __LINE__, __FILE__, false, '', 'syncml')->fetch())) {
			$this->_clientDeviceInfo = array (
				'DTDVersion'				=> $row['dev_dtdversion'],
				'supportNumberOfChanges'	=> $row['dev_numberofchanges'],
				'supportLargeObjs'			=> $row['dev_largeobjs'],
				'UTC'						=> $row['dev_utc'],
				'softwareVersion'			=> $row['dev_swversion'],
				'hardwareVersion'			=> $row['dev_hwversion'],
				'firmwareVersion'			=> $row['dev_fwversion'],
				'oem'						=> $row['dev_oem'],
				'model'						=> $row['dev_model'],
				'manufacturer'				=> $row['dev_manufacturer'],
				'deviceType'				=> $row['dev_devicetype'],
				'maxMsgSize'				=> $this->_maxMsgSize,
				'maxEntries'        		=> $syncml_prefs[$deviceMaxEntries],
				'uidExtension'        		=> $syncml_prefs[$deviceUIDExtension],
				'nonBlockingAllday'			=> $syncml_prefs[$deviceNonBlockingAllday],
				'tzid'						=> $syncml_prefs[$deviceTimezone],
				'charset'					=> $syncml_prefs[$deviceCharSet],
				'devId'						=> $deviceID,
				'persistent'				=> true,
				'dataStore'					=> unserialize($row['dev_datastore']),
			);
			return $this->_clientDeviceInfo;
		}
		return false;
	}

	/**
	 * returns GUIDs of all client items
	 */
	function getClientItems($type=false) {
		if (!$type) $type = $this->_targetURI;
		$mapID = $this->_locName . $this->_sourceURI . $type;

		$guids = array();
		foreach($GLOBALS['egw']->db->select('egw_contentmap', 'map_guid', array(
			'map_id'		=> $mapID,
			'map_expired'	=> false,
		), __LINE__, __FILE__, false, '', 'syncml') as $row) {
			$guids[] = $row['map_guid'];
		}
		return $guids;
	}

	/**
	 * Retrieves the Horde server guid (like
	 * kronolith:0d1b415fc124d3427722e95f0e926b75) for a given client
	 * locid. Returns false if no such id is stored yet.
	 *
	 * Opposite of getLocId which returns the locid for a given guid.
	 */
	function getGlobalUID($type, $locid) {
		$mapID = $this->_locName . $this->_sourceURI . $type;

		#Horde::logMessage('SyncML: search GlobalUID for  ' . $mapID .' / '.$locid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		return $GLOBALS['egw']->db->select('egw_contentmap', 'map_guid',
			array(
				'map_id'		=> $mapID,
				'map_locuid'	=> $locid,
				'map_expired'	=> false,
			), __LINE__, __FILE__, false, '', 'syncml')->fetchColumn();
	}

	/**
	 * Converts a EGW GUID (like
	 * kronolith:0d1b415fc124d3427722e95f0e926b75) to a client ID as
	 * used by the sync client (like 12) returns false if no such id
	 * is stored yet.
	 */
	function getLocID($type, $guid) {
		$mapID = $this->_locName . $this->_sourceURI . $type;

		Horde::logMessage('SyncML: search LocID for ' . $mapID . ' / ' . $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if (($locuid = $GLOBALS['egw']->db->select('egw_contentmap', 'map_locuid', array(
			'map_id'	=> $mapID,
			'map_guid'	=> $guid
		), __LINE__, __FILE__, false, '', 'syncml')->fetchColumn())) {
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
	function getSyncSummary($type) {
		$deviceID = $this->_locName . $this->_sourceURI;

		Horde::logMessage("SyncML: getSyncSummary for $deviceID", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if (($row = $GLOBALS['egw']->db->select('egw_syncmlsummary', array('sync_serverts','sync_clientts'), array(
			'dev_id'	=> $deviceID,
			'sync_path'	=> $type
		), __LINE__, __FILE__, false, '', 'syncml')->fetch())) {
			Horde::logMessage("SyncML: getSyncSummary for $deviceID serverts: ".$row['sync_serverts']."  clients: ".$row['sync_clientts'], __FILE__, __LINE__, PEAR_LOG_DEBUG);
			return array(
				'ClientAnchor'	=> $row['sync_clientts'],
				'ServerAnchor'	=> $row['sync_serverts'],
			);
		}
		return false;
	}

	function isAuthorized()	{

		if(!isset($this->_locName))
		{
			Horde::logMessage('SyncML: Authentication not yet possible. Username not available',
				__FILE__, __LINE__, PEAR_LOG_DEBUG);
			return false;
		}

		// store sessionID in a variable, because create() and verify() reset this value
		$sessionID = session_id();

		if (strpos($this->_locName,'@') === False)
		{
			$this->_account_domain = $GLOBALS['egw_info']['server']['default_domain'];
			$this->_locName .= '@'. ($this->_account_domain ? $this->_account_domain : 'default');
		}
		else
		{
			$parts = explode('@',$this->_locName);
			$this->_account_domain = array_pop($parts);
		}

		if (!is_object($GLOBALS['egw']))
		{
			// Let the EGw core create the infrastructure classes
			$_POST['login'] = $this->_locName;
			$_REQUEST['domain'] = $this->_account_domain;
			$GLOBALS['egw_info']['server']['default_domain'] = $this->_account_domain;
			$GLOBALS['egw_info']['user']['domain'] = $this->_account_domain;
			$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
			$GLOBALS['egw_info']['flags']['noapi'] = false;
			require_once(EGW_API_INC . '/functions.inc.php');
		}

		$GLOBALS['egw_info']['flags']['currentapp'] = 'syncml';

		if (!$this->_isAuthorized)
		{

			if (!isset($this->_password))
			{
				Horde::logMessage('SyncML: Authentication not yet possible. Credetials missing',
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				return false;
			}

			if ($GLOBALS['egw']->session->create($this->_locName,$this->_password,'text'))
			{
				if ($GLOBALS['egw_info']['user']['apps']['syncml'])
				{
					$this->_isAuthorized = 1;
					// restore the original sessionID
					session_regenerate_id();
					session_id($sessionID);
					$GLOBALS['sessionid'] = $sessionID;
					@session_start();
					Horde::logMessage('SyncML_EGW[' . $GLOBALS['sessionid']
						.']: Authentication of ' . $this->_locName . ' succeded',
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					$syncml_prefs = $GLOBALS['egw_info']['user']['preferences']['syncml'];
					if (($deviceID = $GLOBALS['egw']->db->select('egw_syncmldeviceowner',
						'owner_devid',
						array (
							'owner_locname'		=> $this->_locName,
							'owner_deviceid'	=> $this->_sourceURI,
						), __LINE__, __FILE__, false, '', 'syncml')->fetchColumn())) {
						$allowed_name = 'allowed-' . $deviceID;
						if (isset($syncml_prefs[$allowed_name]))
						{
							$deviceAllowed = $syncml_prefs[$allowed_name];
						}
						else
						{
							$deviceAllowed = -1;
						}
					}
					else
					{
						$deviceAllowed = -1; // Unkown device
					}
					if (!$GLOBALS['egw_info']['user']['apps']['admin'] &&
						isset($syncml_prefs['deny_unknown_devices']) &&
							$syncml_prefs['deny_unknown_devices'] != 0)
					{
						if ($syncml_prefs['deny_unknown_devices'] == -1 &&
							$deviceAllowed != 1 ||
							$syncml_prefs['deny_unknown_devices'] == 1 &&
								$deviceAllowed == 0)
						{
							$this->_isAuthorized = -1;
							Horde::logMessage('SyncML: Device is not allowed for user ' . $this->_locName,
								__FILE__, __LINE__, PEAR_LOG_INFO);
						}
					}
				}
				else
				{
					$this->_isAuthorized = -1; // Authorization failed!
					Horde::logMessage('SyncML is not enabled for user '
						. $this->_locName, __FILE__, __LINE__, PEAR_LOG_ERROR);
				}
			}
			else
			{
				$this->_isAuthorized = -1;
				Horde::logMessage('SyncML: Authentication of ' . $this->_locName
					. ' failed', __FILE__, __LINE__, PEAR_LOG_INFO);

			}

		}
		elseif ($this->_isAuthorized > 0)
		{
			if (!$GLOBALS['egw']->session->verify($sessionID, 'staticsyncmlkp3'))
			{
				Horde::logMessage('SyncML_EGW: egw session(' . $sessionID
					. ') could not be not verified' ,
					__FILE__, __LINE__, PEAR_LOG_ERROR);
			}
			if (empty($GLOBALS['egw_info']['user']['passwd']))
			{
				$GLOBALS['egw_info']['user']['passwd'] = $this->_password;
			}
		}
		return ($this->_isAuthorized > 0);
	}

	/**
	 * Removes all locid<->guid mappings for the given type.
	 * Returns always true.
	 */
	function removeAllUID($type) {
		$mapID = $this->_locName . $this->_sourceURI . $type;

		Horde::logMessage("SyncML: state->removeAllUID(type=$type)", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$GLOBALS['egw']->db->delete('egw_contentmap', array('map_id' => $mapID), __LINE__, __FILE__, 'syncml');

		return true;
	}

	/**
	 * Used in SlowSync
	 * Removes all locid<->guid mappings for the given type,
	 * that are older than $ts.
	 *
	 * Returns always true.
	 */
	function removeOldUID($type, $ts) {
		$mapID = $this->_locName . $this->_sourceURI . $type;
		$where[] = "map_id = '".$mapID."' AND map_timestamp < '".$GLOBALS['egw']->db->to_timestamp($ts)."'";

		Horde::logMessage("SyncML: state->removeOldUID(type=$type)", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$GLOBALS['egw']->db->delete('egw_contentmap', $where, __LINE__, __FILE__, 'syncml');

		return true;
	}

	/**
	 * Used at session end to cleanup expired entries
	 * Removes all locid<->guid mappings for the given type,
	 * that are marked as expired and older than $ts.
	 *
	 * Returns always true.
	 */
	function removeExpiredUID($type, $ts) {
		$mapID = $this->_locName . $this->_sourceURI . $type;
		$where['map_id'] = $mapID;
		$where['map_expired'] = true;
		$where[] = "map_timestamp <= '".$GLOBALS['egw']->db->to_timestamp($ts)."'";

		Horde::logMessage("SyncML: state->removeExpiredUID(type=$type)",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

		$GLOBALS['egw']->db->delete('egw_contentmap', $where,
			__LINE__, __FILE__, 'syncml');

		return true;
	}

	/**
	 * Check if an entry is already expired
	 *
	 * Returns true for expired mappings.
	 */
	function isExpiredUID($type, $locid) {
		$mapID = $this->_locName . $this->_sourceURI . $type;
		$expired = false;
		$where = array(
			'map_id'	=> $mapID,
			'map_locuid'	=> $locid,
		);
		if (($expired = $GLOBALS['egw']->db->select('egw_contentmap', 'map_expired',
			$where, __LINE__, __FILE__, false, '', 'syncml')->fetchColumn())) {
			Horde::logMessage('SyncML: found LocID: '. $locid,
				__FILE__, __LINE__, PEAR_LOG_DEBUG);
		}
		return $expired;
	}

	/**
	 * Removes the locid<->guid mapping for the given locid. Returns
	 * the guid that was removed or false if no mapping entry was
	 * found.
	 */
	function removeUID($type, $locid) {
		$mapID = $this->_locName . $this->_sourceURI . $type;

		$where = array (
			'map_id'	=> $mapID,
			'map_locuid'	=> $locid
		);

		if (!($guid = $GLOBALS['egw']->db->select('egw_contentmap', 'map_guid', $where,
			__LINE__, __FILE__, false, '', 'syncml')->fetchColumn())) {
			Horde::logMessage("SyncML: state->removeUID(type=$type,locid=$locid)"
				. " nothing to remove", __FILE__, __LINE__, PEAR_LOG_INFO);
			return false;
		}

		Horde::logMessage("SyncML:  state->removeUID(type=$type,locid=$locid): "
			. "removing guid $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

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
	 * Expired entries can be deleted at the next session start.
	 */
	function setUID($type, $locid, $_guid, $ts=0, $expired=false) {
		#Horde::logMessage("SyncML: setUID $type, $locid, $_guid, $ts ", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if (!strlen("$_guid")) {
			// We can't handle this case otherwise
			return;
		}

		// problem: entries created from client, come here with the (long) server guid,
		// but getUIDMapping does not know them and can not map server-guid <--> client guid
		$guid = $this->getUIDMapping($_guid);
		if($guid === false)	{
			// this message is not really usefull here because setUIDMapping is only called when adding content to the client,
			// however setUID is called also when adding content from the client. So in all other conditions this
			// message will be logged.
			//Horde::logMessage("SyncML: setUID $type, $locid, $guid something went wrong!!! Mapping not found.", __FILE__, __LINE__, PEAR_LOG_INFO);
			$guid = $_guid;
			//return false;
		}
		#Horde::logMessage("SyncML: setUID $_guid => $guid", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if(!$ts) $ts = time();

		Horde::logMessage("SyncML: setUID $type, $locid, $guid, $ts ",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

		$mapID = $this->_locName . $this->_sourceURI . $type;

		// expire all client id's
		$where = array(
			'map_id'	=> $mapID,
			'map_locuid'	=> $locid,
		);
		$GLOBALS['egw']->db->delete('egw_contentmap', $where,
			__LINE__, __FILE__, 'syncml');

		// expire old EGw id's
		$where = array(
			'map_id'	=> $mapID,
			'map_guid'	=> $guid,
		);
		$GLOBALS['egw']->db->delete('egw_contentmap', $where,
			__LINE__, __FILE__, 'syncml');
		/*	
		$data = array ('map_expired' => true);
		$GLOBALS['egw']->db->update('egw_contentmap', $data, $where,
			__LINE__, __FILE__, 'syncml');
		*/
		$data = $where + array(
			'map_locuid'	=> $locid,
			'map_timestamp'	=> $ts,
			'map_expired'	=> ($expired ? true : false),
		);
		$GLOBALS['egw']->db->insert('egw_contentmap', $data, $where,
			__LINE__, __FILE__, 'syncml');
	}

	/**
	 * writes clients deviceinfo into database
	 */
	function writeClientDeviceInfo() {
		if (!isset($this->_clientDeviceInfo)
				|| !is_array($this->_clientDeviceInfo)) {
			return false;
		}
		
		if (empty($this->_clientDeviceInfo['persistent'])) {
			// the device information was updated 
			
			if(!isset($this->size_dev_hwversion)) {
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
			
			if (($deviceID = $GLOBALS['egw']->db->select('egw_syncmldevinfo', 'dev_id', $where,
				__LINE__, __FILE__, false, '', 'syncml')->fetchColumn())) {
				$data = array (
					'dev_datastore'		=> serialize($this->_clientDeviceInfo['dataStore']),
				);
				$GLOBALS['egw']->db->update('egw_syncmldevinfo', $data, $where,
					__LINE__, __FILE__, 'syncml');
			} else {
				$data = array (
					'dev_dtdversion' 		=> $this->_clientDeviceInfo['DTDVersion'],
					'dev_numberofchanges'	=> ($this->_clientDeviceInfo['supportNumberOfChanges'] ? true : false),
					'dev_largeobjs'			=> ($this->_clientDeviceInfo['supportLargeObjs'] ? true : false),
					'dev_utc'				=> ($this->_clientDeviceInfo['UTC'] ? true : false),
					'dev_swversion'			=> $softwareVersion,
					'dev_hwversion'			=> $hardwareVersion,
					'dev_fwversion'			=> $firmwareVersion,
					'dev_oem'				=> $this->_clientDeviceInfo['oem'],
					'dev_model'				=> $this->_clientDeviceInfo['model'],
					'dev_manufacturer'		=> $this->_clientDeviceInfo['manufacturer'],
					'dev_devicetype'		=> $this->_clientDeviceInfo['deviceType'],
					'dev_datastore'			=> serialize($this->_clientDeviceInfo['dataStore']),
				);
				$GLOBALS['egw']->db->insert('egw_syncmldevinfo', $data, $where, __LINE__, __FILE__, 'syncml');
				
				$deviceID = $GLOBALS['egw']->db->get_last_insert_id('egw_syncmldevinfo', 'dev_id');
			}
			
			$this->_clientDeviceInfo['persistent'] = true;
		}

		$data = $where = array (
			'owner_locname'		=> $this->_locName,
			'owner_deviceid'	=> $this->_sourceURI,
		);

		$GLOBALS['egw']->db->delete('egw_syncmldeviceowner', $where,
			__LINE__, __FILE__, 'syncml');
			
		$data['owner_devid'] = $deviceID;

		$GLOBALS['egw']->db->insert('egw_syncmldeviceowner', $data, $where,
			__LINE__, __FILE__, 'syncml');
	}

	/**
	 * After a successful sync, the client and server's Next Anchors
	 * are written to the database so they can be used to negotiate
	 * upcoming syncs.
	 */
	function writeSyncSummary() {
		#parent::writeSyncSummary();

		if (!isset($this->_serverAnchorNext)
				|| !is_array($this->_serverAnchorNext)) {
			return;
		}

		$deviceID = $this->_locName . $this->_sourceURI;

		foreach((array)$this->_serverAnchorNext as $type => $a) {
			Horde::logMessage("SyncML: write SYNCSummary for $deviceID "
				. "$type serverts: $a clients: "
				. $this->_clientAnchorNext[$type],
				__FILE__, __LINE__, PEAR_LOG_DEBUG);

			$where = array(
				'dev_id'	=> $deviceID,
				'sync_path'	=> $type,
			);

			$data  = array(
				'sync_serverts' => $a,
				'sync_clientts' => $this->_clientAnchorNext[$type]
			);

			$GLOBALS['egw']->db->insert('egw_syncmlsummary', $data, $where,
				__LINE__, __FILE__, 'syncml');
		}
	}
}
