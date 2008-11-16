<?php

define('ALERT_DISPLAY', 100);

// Not implemented.
define('ALERT_TWO_WAY', 200);
define('ALERT_SLOW_SYNC', 201);
define('ALERT_ONE_WAY_FROM_CLIENT', 202);
define('ALERT_REFRESH_FROM_CLIENT', 203);
define('ALERT_ONE_WAY_FROM_SERVER', 204);
define('ALERT_REFRESH_FROM_SERVER', 205);

// Not implemented.
define('ALERT_TWO_WAY_BY_SERVER', 206);
define('ALERT_ONE_WAY_FROM_CLIENT_BY_SERVER', 207);
define('ALERT_REFRESH_FROM_CLIENT_BY_SERVER', 208);
define('ALERT_ONE_WAY_FROM_SERVER_BY_SERVER', 209);
define('ALERT_REFRESH_FROM_SERVER_BY_SERVER', 210);

define('ALERT_RESULT_ALERT', 221);
define('ALERT_NEXT_MESSAGE', 222);
define('ALERT_NO_END_OF_DATA', 223);

define('MIME_SYNCML_XML', 'application/vnd.syncml+xml');
define('MIME_SYNCML_WBXML', 'application/vnd.syncml+wbxml');

define('MIME_SYNCML_DEVICE_INFO_XML', 'application/vnd.syncml-devinf+xml');
define('MIME_SYNCML_DEVICE_INFO_WBXML', 'application/vnd.syncml-devinf+wbxml');

define('MIME_TEXT_PLAIN', 'text/plain');
define('MIME_VCARD_V21', 'text/x-vcard');
define('MIME_VCARD_V30', 'text/vcard');

define('MIME_VCALENDAR', 'text/x-vcalendar');
define('MIME_ICALENDAR', 'text/calendar');
define('MIME_XML_ICALENDAR', 'application/vnd.syncml-xcal');

define('MIME_MESSAGE', 'text/message');

define('MIME_SYNCML_XML_EMAIL', 'application/vnd.syncml-xmsg');
define('MIME_SYNCML_XML_BOOKMARK', 'application/vnd.syncml-xbookmark');
define('MIME_SYNCML_RELATIONAL_OBJECT', 'application/vnd.syncml-xrelational');

define('RESPONSE_IN_PROGRESS', 101);

define('RESPONSE_OK', 200);
define('RESPONSE_ITEM_ADDED', 201);
define('RESPONSE_ACCEPTED_FOR_PROCESSING', 202);
define('RESPONSE_NONAUTHORIATATIVE_RESPONSE', 203);
define('RESPONSE_NO_CONTENT', 204);
define('RESPONSE_RESET_CONTENT', 205);
define('RESPONSE_PARTIAL_CONTENT', 206);
define('RESPONSE_CONFLICT_RESOLVED_WITH_MERGE', 207);
define('RESPONSE_CONFLICT_RESOLVED_WITH_CLIENT_WINNING', 208);
define('RESPONSE_CONFILCT_RESOLVED_WITH_DUPLICATE', 209);
define('RESPONSE_DELETE_WITHOUT_ARCHIVE', 210);
define('RESPONSE_ITEM_NO_DELETED', 211);
define('RESPONSE_AUTHENTICATION_ACCEPTED', 212);
define('RESPONSE_CHUNKED_ITEM_ACCEPTED_AND_BUFFERED', 213);
define('RESPONSE_OPERATION_CANCELLED', 214);
define('RESPONSE_NO_EXECUTED', 215);
define('RESPONSE_ATOMIC_ROLL_BACK_OK', 216);

define('RESPONSE_MULTIPLE_CHOICES', 300);
// Need to change names.
// define('RESPONSE_MULTIPLE_CHOICES', 301);
// define('RESPONSE_MULTIPLE_CHOICES', 302);
// define('RESPONSE_MULTIPLE_CHOICES', 303);
// define('RESPONSE_MULTIPLE_CHOICES', 304);
define('RESPONSE_USE_PROXY', 305);

define('RESPONSE_BAD_REQUEST', 400);
define('RESPONSE_INVALID_CREDENTIALS', 401);
// Need to change names.
// define('RESPONSE_INVALID_CREDENTIALS', 402);
// define('RESPONSE_INVALID_CREDENTIALS', 403);
define('RESPONSE_NOT_FOUND', 404);
// Need to change names.
// define('RESPONSE_INVALID_CREDENTIALS', 405);
// define('RESPONSE_INVALID_CREDENTIALS', 406);
define('RESPONSE_MISSING_CREDENTIALS', 407);
// define('RESPONSE_INVALID_CREDENTIALS', 408);
// define('RESPONSE_INVALID_CREDENTIALS', 409);
// define('RESPONSE_INVALID_CREDENTIALS', 410);
// define('RESPONSE_INVALID_CREDENTIALS', 411);
// define('RESPONSE_INVALID_CREDENTIALS', 412);
// define('RESPONSE_INVALID_CREDENTIALS', 413);
// define('RESPONSE_INVALID_CREDENTIALS', 414);
// define('RESPONSE_INVALID_CREDENTIALS', 415);
define('RESPONSE_REQUEST_SIZE_TOO_BIG', 416);
// Need to change names.
// define('RESPONSE_INVALID_CREDENTIALS', 417);
// define('RESPONSE_INVALID_CREDENTIALS', 418);
// define('RESPONSE_INVALID_CREDENTIALS', 419);
// define('RESPONSE_INVALID_CREDENTIALS', 420);
// define('RESPONSE_INVALID_CREDENTIALS', 421);
// define('RESPONSE_INVALID_CREDENTIALS', 422);
// define('RESPONSE_INVALID_CREDENTIALS', 423);
define('RESPONSE_SIZE_MISMATCH', 424);

define('RESPONSE_COMMAND_FAILED', 500);
// Need to change names.
// define('RESPONSE_COMMAND_FAILED', 501);
// define('RESPONSE_COMMAND_FAILED', 502);
// define('RESPONSE_COMMAND_FAILED', 503);
// define('RESPONSE_COMMAND_FAILED', 504);
// define('RESPONSE_COMMAND_FAILED', 505);
// define('RESPONSE_COMMAND_FAILED', 506);
// define('RESPONSE_COMMAND_FAILED', 507);
// define('RESPONSE_COMMAND_FAILED', 508);
// define('RESPONSE_COMMAND_FAILED', 509);
// define('RESPONSE_COMMAND_FAILED', 510);
// define('RESPONSE_COMMAND_FAILED', 511);
// define('RESPONSE_COMMAND_FAILED', 512);
// define('RESPONSE_COMMAND_FAILED', 513);
// define('RESPONSE_COMMAND_FAILED', 514);
// define('RESPONSE_COMMAND_FAILED', 515);
define('RESPONSE_ATOMIC_ROLL_BACK_FAILED', 516);

define('NAME_SPACE_URI_SYNCML', 'syncml:syncml1.0');
define('NAME_SPACE_URI_SYNCML_1_1', 'syncml:syncml1.1');
define('NAME_SPACE_URI_METINF', 'syncml:metinf');
define('NAME_SPACE_URI_METINF_1_1', 'syncml:metinf1.1');
define('NAME_SPACE_URI_DEVINF', 'syncml:devinf');
define('NAME_SPACE_URI_DEVINF_1_1', 'syncml:devinf1.1');

define('CLIENT_SYNC_STARTED',		1);
define('CLIENT_SYNC_FINNISHED',		2);
define('CLIENT_SYNC_ACKNOWLEDGED',	3);
define('SERVER_SYNC_DATA_PENDING',	4);
define('SERVER_SYNC_FINNISHED',		5);
define('SERVER_SYNC_ACKNOWLEDGED',	6);

define('MAX_DATA',			19);
define('MAX_ENTRIES',			10);

/**
 * The Horde_SyncML_State class provides a SyncML state object.
 *
 * $Horde: framework/SyncML/SyncML/State.php,v 1.15 2004/07/26 09:24:38 jan Exp $
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
class Horde_SyncML_State {

	var $_sessionID;

	var $_verProto;

	var $_msgID;

	var $_targetURI;

	var $_sourceURI;

	var $_version;

	var $_locName;

	var $_password;

	var $_isAuthorized;

	var $_uri;

	var $_uriMeta;

	var $_syncs = array();

	var $_clientAnchorNext = array(); // written to db after successful sync

	var $_serverAnchorLast = array();

	var $_serverAnchorNext = array(); // written to db after successful sync

	var $_clientDeviceInfo = array();

	// array list of changed items, which need to be synced to the client
	var $_changedItems;

	// array list of deleted items, which need to be synced to the client
	var $_deletedItems;

	// array list of added items, which need to be synced to the client
	var $_addedItems;

	// bool flag that we need to more data
	var $_syncStatus;

	var $_log = array();

	// stores if we received Alert 222 already
	var $_receivedAlert222 = false;

	// stores if we already requested the deviceinfo
	var $_devinfoRequested = false;

	/*
	 * store the mappings of egw uids to client uids
	 */
	var $_uidMappings	= array();

    /**
     * Creates a new instance of Horde_SyncML_State.
     */
    function Horde_SyncML_State($sourceURI, $locName, $sessionID, $password = false)
    {
        $this->setSourceURI($sourceURI);
        $this->setLocName($locName);
        $this->setSessionID($sessionID);
        if ($password) {
            $this->setPassword($password);
        }

        $this->isAuthorized = false;
    }

    /**
     * store the sent global uid
     */
    function setUIDMapping($_realEgwUid, $_sentEgwUid) {
    	$this->_uidMappings[$_sentEgwUid] = $_realEgwUid;
    }

    /**
     * retrieve the real egw uid for a given send uid
     */
    function getUIDMapping($_sentEgwUid) {
    	if(isset($this->_uidMappings[$_sentEgwUid])) {
    		return $this->_uidMappings[$_sentEgwUid];
	}

	return false;
    }

    /**
     * Returns the DataTree used as persistence layer for SyncML.  The
     * datatree var should not be a class member of State as State is
     * stored as a session var. Resource handles (=db connections)
     * cannot be stored in sessions.
     *
     * @return object DataTree  The DataTree object.
     */
    function &getDataTree()
    {
        $driver = $GLOBALS['conf']['datatree']['driver'];
        $params = Horde::getDriverConfig('datatree', $driver);
        $params = array_merge($params, array( 'group' => 'syncml' ));

        return DataTree::singleton($driver, $params);
    }

    function getLocName()
    {
    	if(isset($this->_locName))
        	return $this->_locName;
        else
        	return False;
    }

    function getSourceURI()
    {
        return $this->_sourceURI;
    }

    function getTargetURI()
    {
        return $this->_targetURI;
    }

    function getVersion()
    {
        return $this->_version;
    }

    function &getAddedItems($_type)
    {
    	if(isset($this->_addedItems[$_type]))
    	{
    		return $this->_addedItems[$_type];
    	}

    	return false;
    }

    function &getChangedItems($_type)
    {
    	if(isset($this->_changedItems[$_type]))
    	{
    		return $this->_changedItems[$_type];
    	}

    	return false;
    }

    function &getDeletedItems($_type)
    {
    	if(isset($this->_deletedItems[$_type]))
    	{
    		return $this->_deletedItems[$_type];
    	}

    	return false;
    }

    function getMoreDataPending()
    {
    	return $this->_moreDataPending;
    }

    function getMsgID()
    {
        return $this->_msgID;
    }

    function setWBXML($wbxml)
    {
        $this->_wbxml = $wbxml;
    }

    function isWBXML()
    {
        return !empty($this->_wbxml);
    }

    function &getSyncStatus()
    {
    	return $this->_syncStatus;
    }

    function setAddedItems($_type, $_addedItems)
    {
    	$this->_addedItems[$_type] = $_addedItems;
    }

    function setChangedItems($_type, $_changedItems)
    {
    	$this->_changedItems[$_type] = $_changedItems;
    }

    function setClientDeviceInfo($clientDeviceInfo)
    {
    	$this->_clientDeviceInfo = $clientDeviceInfo;
    }

    function setDeletedItems($_type, $_deletedItems)
    {
    	$this->_deletedItems[$_type] = $_deletedItems;
    }

    function setMoreDataPending($_state)
    {
    	$this->_moreDataPending = $_state;
    }

    /**
     * Setter for property msgID.
     * @param msgID New value of property msgID.
     */
    function setMsgID($msgID)
    {
        $this->_msgID = $msgID;
    }

    /**
     * Setter for property locName.
     * @param locName New value of property locName.
     */
    function setLocName($locName)
    {
        $this->_locName = $locName;
    }

    /**
     * Setter for property locName.
     * @param locName New value of property locName.
     */
    function setPassword($password)
    {
        $this->_password = $password;
    }

    function setSourceURI($sourceURI)
    {
        $this->_sourceURI = $sourceURI;
    }

    function setSyncStatus($_syncStatus)
    {
	#Horde::logMessage('SyncML: syncState set to ==> ' . $_syncStatus, __FILE__, __LINE__, PEAR_LOG_DEBUG);
    	$this->_syncStatus = $_syncStatus;
    }

    function setTargetURI($targetURI)
    {
        $this->_targetURI = $targetURI;
    }

    function setVersion($version)
    {
        $this->_version = $version;

        if ($version == 0) {
            $this->_uri = NAME_SPACE_URI_SYNCML;
            $this->_uriMeta = NAME_SPACE_URI_METINF;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF;
        } else {
            $this->_uri = NAME_SPACE_URI_SYNCML_1_1;
            $this->_uriMeta = NAME_SPACE_URI_METINF_1_1;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF_1_1;
        }
    }

    function setSessionID($sessionID)
    {
        $this->_sessionID = $sessionID;
    }

    function isAuthorized()
    {
	if (!$this->_isAuthorized) {

                if(strstr($this->_locName,'@') === False)
                {
                	$this->_locName .= '@'.$GLOBALS['egw_info']['server']['default_domain'];
                }

		#Horde::logMessage('SyncML: Authenticate ' . $this->_locName . ' - ' . $this->_password, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if($GLOBALS['sessionid'] = $GLOBALS['egw']->session->create($this->_locName,$this->_password,'text','u'))
		{
			$this->_isAuthorized = true;
			#Horde::logMessage('SyncML_EGW: Authentication of ' . $this->_locName . '/' . $GLOBALS['sessionid'] . ' succeded' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
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
		if(!$GLOBALS['egw']->session->verify($sessionID, 'staticsyncmlkp3'))
			Horde::logMessage('SyncML_EGW: egw session('.$sessionID. ') not verified ' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
	}

        return $this->_isAuthorized;
    }

    function clearSync($target)
    {
    	unset($this->_syncs[$target]);
    }

    function setSync($target, $sync)
    {
        $this->_syncs[$target] = $sync;
    }

    function getSync($target)
    {
        if (isset($this->_syncs[$target])) {
            return $this->_syncs[$target];
        } else {
            return false;
        }
    }

    function getTargets()
    {
    	if(count($this->_syncs) < 1)
    		return FALSE;

    	foreach($this->_syncs as $target => $sync)
    	{
    		$targets[] = $target;
    	}

    	return $targets;
    }

    function getURI()
    {
        /*
         * The non WBXML devices (notably P900 and Sync4j seem to get confused
         * by a <SyncML xmlns="syncml:SYNCML1.1"> element. They require
         * just <SyncML>. So don't use an ns for non wbxml devices.
         */
        if ($this->isWBXML()) {
            return $this->_uri;
        } else {
            return '';
        }
    }
    function getURIMeta()
    {
        return $this->_uriMeta;
    }

    function getURIDevInf()
    {
        return $this->_uriDevInf;
    }


    /**
     * Converts a Horde GUID (like
     * kronolith:0d1b415fc124d3427722e95f0e926b75) to a client ID as
     * used by the sync client (like 12) returns false if no such id
     * is stored yet.
     *
     * Remember that the datatree is really a tree disguised as a
     * table. So to look up the guid above, getId first looks for an
     * entry 'kronolith' and then for an entry
     * 0d1b415fc124d3427722e95f0e926b75 with kronolith as parent.
     */
    function getLocID($type, $guid)
    {
        $dt = &$this->getDataTree();
        $id = $dt->getId($this->_locName . $this->_sourceURI . $type . $guid);
        if (is_a($id, 'PEAR_Error')) {
            return false;
        }

        $gid = $dt->getObjectById($id);
        if (is_a($gid, 'PEAR_Error')) {
            return false;
        }

        return $gid->get('locid');
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
        $dt = &$this->getDataTree();

        // Set $locid.
        $gid = &new DataTreeObject($this->_locName . $this->_sourceURI . $type . $guid);
        $gid->set('type', $type);
        $gid->set('locid', $locid);
        $gid->set('ts', $ts);

        $r = $dt->add($gid);
        if (is_a($r, 'PEAR_Error')) {
            // Object already exists: update instead.
            $r = $dt->updateData($gid);
        }
        $this->dieOnError($r, __FILE__, __LINE__);

        // Set $globaluid
        $lid = &new DataTreeObject($this->_locName . $this->_sourceURI . $type . $locid);
        $lid->set('globaluid', $guid);
        $r = $dt->add($lid);
        if (is_a($r, 'PEAR_Error')) {
            // object already exists: update instead.
            $r = $dt->updateData($lid);
        }
        $this->dieOnError($r, __FILE__, __LINE__);
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
        $this->dieOnError($type, __FILE__, __LINE__);
        $this->dieOnError($locid, __FILE__, __LINE__);
        $this->dieOnError($locid, __FILE__, __LINE__);
        $this->dieOnError($this->_locName, __FILE__, __LINE__);
        $this->dieOnError($this->_sourceURI, __FILE__, __LINE__);

        $dt = &$this->getDataTree();

        $id = $dt->getId($this->_locName . $this->_sourceURI . $type . $locid);
        if (is_a($id, 'PEAR_Error')) {
            return false;
        }
        $lid = $dt->getObjectById($id);
        if (is_a($lid, 'PEAR_Error')) {
            return false;
        }

        return $lid->get('globaluid');
    }

    /**
     * Returns the timestamp (if set) of the last change to the
     * obj:guid, that was caused by the client. This is stored to
     * avoid mirroring these changes back to the client.
     */
    function getChangeTS($type, $guid)
    {
        $dt = &$this->getDataTree();

        $id = $dt->getId($this->_locName . $this->_sourceURI . $type . $guid);
        if (is_a($id, 'PEAR_Error')) {
            return false;
        }

        $gid = $dt->getObjectById($id);
        if (is_a($gid, 'PEAR_Error')) {
            return false;
        }

        return $gid->get('ts');
    }

    /**
     * Removes the locid<->guid mapping for the given locid. Returns
     * the guid that was removed or false if no mapping entry was
     * found.
     */
    function removeUID($type, $locid)
    {
        $dt = &$this->getDataTree();

        $id = $dt->getId($this->_locName . $this->_sourceURI . $type . $locid);
        if (is_a($id, 'PEAR_Error')) {
            Horde::logMessage("SyncML: state->removeUID(type=$type,locid=$locid) : nothing to remove", __FILE__, __LINE__, PEAR_LOG_DEBUG);
            return false;
        }
        $lid = $dt->getObjectById($id);
        $guid = $lid->get('globaluid');
        Horde::logMessage("SyncML:  state->removeUID(type=$type,locid=$locid) : removing guid:$guid and lid:$lid", __FILE__, __LINE__, PEAR_LOG_DEBUG);
        $dt->remove($guid);
        $dt->remove($lid);

        return $guid;
    }


	/**
	* This function should use DevINF information.
	*/
	function adjustContentType($type, $target = null)
	{
		$ctype;
		if (is_array($type))
		{
			$ctype = $type['ContentType'];
			$res = $type;
		}
		else
		{
			$ctype = $type;
			$res = array();
			$res['ContentType'] = $ctype;
		}

        $deviceInfo = $this->getClientDeviceInfo();

        if (isset($deviceInfo['manufacturer']))
        {
            switch (strtolower($deviceInfo['manufacturer']))
            {
                case 'funambol':
                    if (strtolower($deviceInfo['model']) == 'thunderbird')
                    {
                        $res['mayFragment'] = 1;
                    }

                    if (isset($deviceInfo['softwareVersion'])
                        && $deviceInfo['softwareVersion']{0} == '3')
                    {
						// anything beyond 6.0 supports fragmentation
                        $res['mayFragment'] = 0;
                    }
                    else
                    {
                        $res['mayFragment'] = 1;
                    }
                    break;
            }
        }

        if (!isset($res['mayFragment']))
        {
            $res['mayFragment'] = 1;
        }


		// the funambol specific types need to be encoded in base 64
		switch(strtolower($ctype))
		{
			case 'text/x-s4j-sifc':
			case 'text/x-s4j-sife':
			case 'text/x-s4j-sift':
			case 'text/x-s4j-sifn':
				$res['ContentFormat'] = 'b64';
				break;
		}

		return $res;
	}

	function getPreferedContentType($type)
	{
		$_type = str_replace('./','',$type);
		switch(strtolower($_type))
		{
			case 'contacts':
				return 'text/x-vcard';
				break;

			case 'notes':
				return 'text/x-vnote';
				break;

			case 'calendar':
			case 'tasks':
			case 'caltasks':
				return 'text/x-vcalendar';
				break;

			case 'sifcalendar':
			case 'scal':
				return 'text/x-s4j-sife';
				break;

			case 'sifcontacts':
			case 'scard':
				return 'text/x-s4j-sifc';
				break;

			case 'siftasks':
			case 'stask':
				return 'text/x-s4j-sift';
				break;

			case 'sifnotes':
			case 'snote':
				return 'text/x-s4j-sifn';
				break;

			default:
				Horde::logMessage("SyncML: unrecognized content type '$_type'", __FILE__, __LINE__, PEAR_LOG_ERR);
				break;
		}
	}

	function getHordeType($type)
	{
		$_type = str_replace('./','',$type);
		switch(strtolower($_type))
		{
			case 'contacts':
				return 'contacts';
				break;

			case 'notes':
				return 'notes';
				break;

			case 'tasks':
				return 'tasks';
				break;

			case 'calendar':
				return 'calendar';
				break;

			case 'caltasks':
				return 'caltasks';
				break;

			# funambol related types

			case 'sifcalendar':
			case 'scal':
				return 'sifcalendar';
				break;

			case 'sifcontacts':
			case 'scard':
				return 'sifcontacts';
				break;

			case 'siftasks':
			case 'stask':
				return 'siftasks';
				break;

			case 'sifnotes':
			case 'snote':
				return 'sifnotes';
				break;

			default:
				Horde::logMessage("SyncML: unknown hordeType for type=$type ($_type)", __FILE__, __LINE__, PEAR_LOG_INFO);
				return $_type;
				break;
		}
	}

	/**
	/**
	* Returns the preferred contenttype of the client for the given
	* sync data type (database).
	*
	* This is passed as an option to the Horde API export functions.
	*/

	function getPreferedContentTypeClient($_sourceLocURI, $_targetLocURI = null) {
		$deviceInfo = $this->getClientDeviceInfo();

		if(isset($deviceInfo['dataStore'][$_sourceLocURI]['rxPreference']['contentType']))
		{
			return $this->adjustContentType($deviceInfo['dataStore'][$_sourceLocURI]['rxPreference']['contentType'], $_targetLocURI);
		}

		Horde::logMessage('SyncML: sourceLocURI ' . $_sourceLocURI .' not found', __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if ($_targetLocURI != null)
		{
			return $this->adjustContentType($this->getPreferedContentType($_targetLocURI), $_targetLocURI);
		}

		return PEAR::raiseError(_('sourceLocURI not found'));
	}

    function setClientAnchorNext($type, $a)
    {
        $this->_clientAnchorNext[$type] = $a;
    }

    function setServerAnchorLast($type, $a)
    {
        $this->_serverAnchorLast[$type] = $a;
    }

    function setServerAnchorNext($type, $a)
    {
        $this->_serverAnchorNext[$type] = $a;
    }

    function getClientAnchorNext($type)
    {
        return $this->_clientAnchorNext[$type];
    }

    function getServerAnchorNext($type)
    {
        return $this->_serverAnchorNext[$type];
    }

    function getServerAnchorLast($type)
    {
        return $this->_serverAnchorLast[$type];
    }

    /**
     * Retrieves information about the previous sync if any. Returns
     * false if no info found or a DateTreeObject with at least the
     * following attributes:
     *
     * ClientAnchor: the clients Next Anchor of the previous sync.
     * ServerAnchor: the Server Next Anchor of the previous sync.
     */
    function &getSyncSummary($type)
    {
        $dt = &$this->getDataTree();

        $id = $dt->getId($this->_locName . $this->_sourceURI . $type . 'syncSummary');
        if (is_a($id, 'PEAR_Error')) {
            return false;
        }

        return $dt->getObjectById($id);
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
        $dt = &$this->getDataTree();

        $id = $dt->getId($this->_locName . $this->_sourceURI . 'deviceInfo');
        if (is_a($id, 'PEAR_Error')) {
            return false;
        }

	$info = $dt->getObjectById($id);

        return $info->get('ClientDeviceInfo');
    }

    /**
     * write clients device info to database
     */
    function writeClientDeviceInfo()
    {
        if (!isset($this->_clientDeviceInfo) || !is_array($this->_clientDeviceInfo)) {
            return;
        }

        $dt = &$this->getDataTree();

        $s = $this->_locName . $this->_sourceURI . 'deviceInfo';

        // Set $locid.
        $info = &new DataTreeObject($s);
        $info->set('ClientDeviceInfo', $this->_clientDeviceInfo);
        $r = $dt->add($info);
        if (is_a($r, 'PEAR_Error')) {
            // Object already exists: update instead.
            $dt->updateData($info);
        }
    }

    /**
     * After a successful sync, the client and server's Next Anchors
     * are written to the database so they can be used to negotiate
     * upcoming syncs.
     */
    function writeSyncSummary()
    {
        if (!isset($this->_serverAnchorNext) || !is_array($this->_serverAnchorNext)) {
            return;
        }

        $dt = &$this->getDataTree();

        foreach (array_keys($this->_serverAnchorNext) as $type) {
            $s = $this->_locName . $this->_sourceURI . $type . 'syncSummary';

            // Set $locid.
            $info = &new DataTreeObject($s);
            $info->set('ClientAnchor', $this->_clientAnchorNext);
            $info->set('ServerAnchor', $this->_serverAnchorNext);
            $r = $dt->add($info);
            if (is_a($r, 'PEAR_Error')) {
                // Object already exists: update instead.
                $dt->updateData($info);
            }
        }
    }

    /**
     * The log simply counts the entries for each topic.
     */
    function log($topic)
    {
        if (isset($this->_log[$topic])) {
            $this->_log[$topic] += 1;
        } else {
            $this->_log[$topic] = 1;
        }
    }

    /**
     * The Log is an array where the key is the event name and the
     * value says how often this event occured.
     */
    function getLog()
    {
        return $this->_log;
    }

    /**
     * Convert the content.
     *
     * Currently strips uid (primary key) information as client and
     * server might use different ones.
     *
     * Charset conversions might be added here too.
     */
    function convertClient2Server($content, $contentType)
    {
        switch ($contentType) {
        case 'text/calendar':
        case 'text/x-icalendar':
        case 'text/x-vcalendar':
        case 'text/x-vevent':
        case 'text/x-vtodo':
            // $content = preg_replace('/^UID:.*\n/m', '', $content, 1);
            break;
        }

        return $content;
    }

    /**
     * Convert the content.
     *
     * Currently strips uid (primary key) information as client and
     * server might use different ones.
     *
     * Charset conversions might be added here too.
     */
    function convertServer2Client($content, $contentType)
    {
        switch ($contentType) {
        case 'text/calendar':
        case 'text/x-icalendar':
        case 'text/x-vcalendar':
        case 'text/x-vevent':
        case 'text/x-vtodo':
            // $content = preg_replace('/^UID:.*\n/m', '', $content, 1);
            break;
        }

        // FIXME: utf8 really should be fine.  But the P900 seems to
        // expect ISO 8559 even when &lt;?xml version="1.0"
        // encoding="utf-8"&gt; is specified.
        //
        // So at least make this dependant on the device information.
        return utf8_decode($content);
    }

    /**
     * When True, Task Item changes (NAG) are sent to the server
     * during "calendar" Syncs.  That's the way the P800/900 handles
     * things.  Should be retrieved from devinf?
     */
    function handleTasksInCalendar()
    {
        return true;
    }

    /**
     * This is a small helper function that can be included to check
     * whether a given $obj is a PEAR_Error or not. If so, it logs
     * to debug, var_dumps the $obj and exits.
     */
    function dieOnError($obj, $file = __FILE__, $line = __LINE__)
    {
        if (!is_a($obj, 'PEAR_Error')) {
            return;
        }

        Horde::logMessage('SyncML: PEAR Error: ' . $obj->getMessage(), $file, $line, PEAR_LOG_ERR);
        print "PEAR ERROR\n\n";
        var_dump($obj);
        exit;
    }

	function getAlert222Received() {
		return $this->_receivedAlert222;
	}

	function setAlert222Received($_status) {
		$this->_receivedAlert222 = (bool)$_status;
	}

}
