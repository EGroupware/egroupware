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
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */

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

// Not (really) implemented.
define('ALERT_SUSPEND',        224); // New in SyncML 1.2
define('ALERT_RESUME',         225); // New in SyncML 1.2

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
define('RESPONSE_CONFLICT_RESOLVED_WITH_CLIENT_WINS', 208);
define('RESPONSE_CONFILCT_RESOLVED_WITH_DUPLICATE', 209);
define('RESPONSE_DELETE_WITHOUT_ARCHIVE', 210);
define('RESPONSE_ITEM_NO_DELETED', 211);
define('RESPONSE_AUTHENTICATION_ACCEPTED', 212);
define('RESPONSE_CHUNKED_ITEM_ACCEPTED_AND_BUFFERED', 213);
define('RESPONSE_OPERATION_CANCELLED', 214);
define('RESPONSE_NO_EXECUTED', 215);
define('RESPONSE_ATOMIC_ROLL_BACK_OK', 216);

define('RESPONSE_MULTIPLE_CHOICES', 300);
define('RESPONSE_MOVED_PERMANENTLY', 301);
define('RESPONSE_FOUND', 302);
define('RESPONSE_SEE_OTHER', 303);
define('RESPONSE_NOT_MODIFIED', 304);
define('RESPONSE_USE_PROXY', 305);

define('RESPONSE_BAD_REQUEST', 400);
define('RESPONSE_INVALID_CREDENTIALS', 401);
define('RESPONSE_PAYMENT_NEEDED', 402);
define('RESPONSE_FORBIDDEN', 403);
define('RESPONSE_NOT_FOUND', 404);
define('RESPONSE_COMMAND_DENIED', 405);
define('RESPONSE_FEATURE_NOT_SUPPORTED', 406);
define('RESPONSE_MISSING_CREDENTIALS', 407);
define('RESPONSE_REQUEST_TIMEOUT', 408);
define('RESPONSE_CONFLICT_DETECTED', 409);
define('RESPONSE_ITEM_GONE', 410);
define('RESPONSE_SIZE_REQUIRED', 411);
define('RESPONSE_INCOMPLETE_COMMAND', 412);
define('RESPONSE_ENTITY_TO_LARGE', 413);
define('RESPONSE_URI_TOO_LONG', 414);
define('RESPONSE_UNSUPPORTED_MEDIA_TYPE', 415);
define('RESPONSE_REQUEST_SIZE_TOO_BIG', 416);
define('RESPONSE_RETRY_LATER', 417);
define('RESPONSE_ALREADY_EXISITS', 418);
define('RESPONSE_CONFLICT_RESOLVED_WITH_SERVER_WINS', 419);
define('RESPONSE_DEVICE_FULL', 420);
define('RESPONSE_UNKNOWN_SEARCH_GRAMMAR', 421);
define('RESPONSE_BAD_CGI', 422);
define('RESPONSE_SOFT_DELETE_CONFICT', 423);
define('RESPONSE_SIZE_MISMATCH', 424);
define('RESPONSE_PERMISSION_DENIED', 425);
define('RESPONSE_PARTIAL_ITEM_NOT_ACCEPTED', 426);
define('RESPONSE_ITEM_NOT_EMPTY', 427);
define('RESPONSE_MOVE_FAILED', 428);

define('RESPONSE_COMMAND_FAILED', 500);
define('RESPONSE_COMMAND_NOT_IMPLEMENTED', 501);
define('RESPONSE_BAD_GATEWAY', 502);
define('RESPONSE_SERVICE_UNAVAILABLE', 503);
define('RESPONSE_GATEWAY_TIMEOUT', 504);
define('RESPONSE_DTD_VERSION_NOT_SUPPORTED', 505);
define('RESPONSE_PROCESSING_ERROR', 506);
define('RESPONSE_ATOMIC_FAILED', 507);
define('RESPONSE_REFRESH_REQUIRED', 508);
// define('RESPONSE_FUTURE_USE', 509);
define('RESPONSE_DATASTORE_FAILURE', 510);
define('RESPONSE_SERVER_FAILURE', 511);
define('RESPONSE_SYNCHRONIZATION_FAILED', 512);
define('RESPONSE_PROTOCOL_VERSION_NOT_SUPPORTED', 513);
define('RESPONSE_OPERATION_CANCELLED', 514);
define('RESPONSE_COMMAND_FAILED', 515);
define('RESPONSE_ATOMIC_ROLL_BACK_FAILED', 516);
define('RESPONSE_ATOMIC_RESPONSE_TOO_LARGE', 517);

define('NAME_SPACE_URI_SYNCML_1_0', 'syncml:syncml1.0');
define('NAME_SPACE_URI_SYNCML_1_1', 'syncml:syncml1.1');
define('NAME_SPACE_URI_SYNCML_1_2', 'syncml:syncml1.2');
define('NAME_SPACE_URI_METINF_1_0', 'syncml:metinf');
define('NAME_SPACE_URI_METINF_1_1', 'syncml:metinf');
define('NAME_SPACE_URI_METINF_1_2', 'syncml:metinf');
define('NAME_SPACE_URI_DEVINF_1_0', 'syncml:devinf');
define('NAME_SPACE_URI_DEVINF_1_1', 'syncml:devinf');
define('NAME_SPACE_URI_DEVINF_1_2', 'syncml:devinf');

define('CLIENT_SYNC_STARTED',		1);
define('CLIENT_SYNC_FINNISHED',		2);
define('CLIENT_SYNC_ACKNOWLEDGED',	3);
define('SERVER_SYNC_DATA_PENDING',	4);
define('SERVER_SYNC_FINNISHED',		5);
define('SERVER_SYNC_ACKNOWLEDGED',	6);

// conflict management
define('CONFLICT_CLIENT_WINNING',			0);
define('CONFLICT_SERVER_WINNING',			1);
define('CONFLICT_MERGE_DATA',				2);
define('CONFLICT_RESOLVED_WITH_DUPLICATE',	3);
define('CONFLICT_CLIENT_CHANGES_IGNORED',	4);
define('CONFLICT_CLIENT_REFRESH_ENFORCED',	5);

define('MAX_DATA',			19);
define('MAX_ENTRIES',		10); // default
define('MAX_GUID_SIZE',		64);
define('MIN_MSG_LEFT',		200); // Overhead

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
 * @modified Joerg Lehrke <jlehrke@noc.de> 2009/01/20, support all syn types
 */
class Horde_SyncML_State {

	var $_sessionID;

	var $_verProto;

	var $_msgID;

	var $_maxMsgSize;

	var $_maxGUIDSize;

	var $_targetURI;

	var $_sourceURI;

	var $_version;

	var $_locName;

	var $_password;

	/*
	 * integer  0 authorization pending
	 * 		   -1 authorization failed
	 *          1 session is authorized
	 */
	var $_isAuthorized;

	var $_AuthConfirmed;

	var $_uri;

	var $_uriMeta;

	var $_syncs = array();

	var $_clientAnchorNext = array(); // written to db after successful sync

	var $_serverAnchorLast = array();

	var $_serverAnchorNext = array(); // written to db after successful sync

	var $_clientDeviceInfo;

	// array list of changed items, which need to be synced to the client
	var $_changedItems;

	// array list of deleted items, which need to be synced to the client
	var $_deletedItems;

	// array list of added items, which need to be synced to the client
	var $_addedItems;

	// array list of items, which need to be refreshed at the client
	var $_conflictItems;

	// current session status
	var $_syncStatus = 0;

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
     * Current sync element sent from client.
     *
     * Stored in state if one element is split into multiple message packets.
     *
     * @var SyncML_SyncElement
     */
    var $curSyncItem;

    /**
     * Number of sync elements sent to client within current message.
     *
     * @var _numberOfElements
     */
    var $_numberOfElements;

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

        $this->_isAuthorized = 0;
        $this->_isAuthConfirmed = false;
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
    	if(strlen("$_sentEgwUid") && isset($this->_uidMappings[$_sentEgwUid])) {
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

    function &getConflictItems($_type)
    {
    	if(isset($this->_conflictItems[$_type]))
    	{
    		return $this->_conflictItems[$_type];
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

    function getMaxMsgSizeClient()
    {
	if (isset($this->_maxMsgSize)) {
        	return $this->_maxMsgSize;
	}
	return false;
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

    function mergeAddedItems($_type, $_addedItems)
    {
    	if (is_array($this->_addedItems[$_type])) {
    		$this->_addedItems[$_type] = array_merge($this->_addedItems[$_type], $_addedItems);
    	} else {
    		$this->_addedItems[$_type] = $_addedItems;
    	}
    }

    function pushAddedItem($_type, $_addedItem)
    {
    	$this->_addedItems[$_type][] = $_addedItem;
    }

    function setChangedItems($_type, $_changedItems)
    {
    	$this->_changedItems[$_type] = $_changedItems;
    }

    function mergeChangedItems($_type, $_changedItems)
    {
    	if (is_array($this->_changedItems[$_type])) {
    		$this->_changedItems[$_type] = array_merge($this->_changedItems[$_type], $_changedItems);
    	} else {
    		$this->_changedItems[$_type] = $_changedItems;
    	}
    }

    function pushChangedItem($_type, $_changedItem)
    {
    	$this->_changedItems[$_type][] = $_changedItem;
    }

    function setClientDeviceInfo($clientDeviceInfo)
    {
    	$this->_clientDeviceInfo = $clientDeviceInfo;
    	unset($this->_clientDeviceInfo['persistent']);
    }

    function setDeletedItems($_type, $_deletedItems)
    {
    	$this->_deletedItems[$_type] = $_deletedItems;
    }

    function addConflictItem($_type, $_conflict)
    {
    	$this->_conflictItems[$_type][] = $_conflict;
    }

    function clearConflictItems($_type)
    {
    	$this->_conflictItems[$_type] = array();
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
     * Setter for property maxMsgSize.
     * @param size New value of property maxMsgSize.
     */
    function setMaxMsgSize($size)
    {
        $this->_maxMsgSize = $size;
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

        if ($version == 2) {
            $this->_uri = NAME_SPACE_URI_SYNCML_1_2;
            $this->_uriMeta = NAME_SPACE_URI_METINF_1_2;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF_1_2;
        } elseif ($version == 1) {
            $this->_uri = NAME_SPACE_URI_SYNCML_1_1;
            $this->_uriMeta = NAME_SPACE_URI_METINF_1_1;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF_1_1;
        } else {
            $this->_uri = NAME_SPACE_URI_SYNCML_1_0;
            $this->_uriMeta = NAME_SPACE_URI_METINF_1_0;
            $this->_uriDevInf = NAME_SPACE_URI_DEVINF_1_0;
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
			$this->_isAuthorized = 1;
			#Horde::logMessage('SyncML_EGW: Authentication of ' . $this->_locName . '/' . $GLOBALS['sessionid'] . ' succeded' , __FILE__, __LINE__, PEAR_LOG_DEBUG);
		}
		else
		{
			$this->_isAuthorized = -1;
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

        return ($this->_isAuthorized > 0);
    }

    function isAuthConfirmed()
    {
    	return $this->_AuthConfirmed;
    }

    function AuthConfirmed()
    {
    	$this->_AuthConfirmed = true;
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
    		return false;

    	foreach($this->_syncs as $target => $sync)
    	{
    		$targets[] = $target;
    	}

    	// Make sure we keep the order
    	sort($targets);

    	return $targets;
    }
    
    function needDeviceInfo()
    {
    	if ($this->_devinfoRequested || count($this->_syncs) < 1) return false;
    		
		$sendGetRequest = false;
		
    	foreach($this->_syncs as $sync)
    	{
    		if (($source = $sync->getSourceLocURI()) &&
    			is_a($this->getPreferedContentTypeClient($source), 'PEAR_Error')) {
    			$sendGetRequest = true;
				break;
    		}
    	}

    	return $sendGetRequest;
    }
    
    function deviceInfoRequested()
    {
    	$this->_devinfoRequested = true;
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
     * Expired entries can be deleted at the next session start.
     */
    function setUID($type, $locid, $guid, $ts=0, $expired=0)
    {
        $dt = &$this->getDataTree();

        // Set $locid.
        $gid = new DataTreeObject($this->_locName . $this->_sourceURI . $type . $guid);
        $gid->set('type', $type);
        $gid->set('locid', $locid);
        $gid->set('ts', $ts);
        $gid->set('expired', $expired);

        $r = $dt->add($gid);
        if (is_a($r, 'PEAR_Error')) {
            // Object already exists: update instead.
            $r = $dt->updateData($gid);
        }
        $this->dieOnError($r, __FILE__, __LINE__);

        // Set $globaluid
        $lid = new DataTreeObject($this->_locName . $this->_sourceURI . $type . $locid);
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
		if (is_array($type)) {
			$ctype = $type['ContentType'];
			$res = $type;
		} else {
			$ctype = $type;
			$res = array();
			$res['ContentType'] = $ctype;
		}

		$deviceInfo = $this->getClientDeviceInfo();
		$manufacturer = isset($deviceInfo['manufacturer']) ? strtolower($deviceInfo['manufacturer']) : 'unknown';
		switch ($manufacturer) {
			case 'funambol':
				switch (strtolower($deviceInfo['model'])) {
					case 'thunderbird':
					case 'mozilla plugin':
						$res['mayFragment'] = 1;
						break;
					default:
						if (isset($deviceInfo['softwareVersion'])
							&& $deviceInfo['softwareVersion'] < 4.0) {
							$res['mayFragment'] = 0;
						} else {
							$res['mayFragment'] = 1;
						}
						break;
				}
				break;
			default:
				$res['mayFragment'] = 1;
				break;
		}

		// the funambol specific types need to be encoded in base64
		switch (strtolower($ctype)) {
			case 'text/x-s4j-sifc':
			case 'text/x-s4j-sife':
			case 'text/x-s4j-sift':
			case 'text/x-s4j-sifn':
				$res['ContentFormat'] = 'b64';
				break;
			case 'text/vcard':
				if ($manufacturer == 'nokia' ||
					isset($res['ContentVersion']) &&  $res['ContentVersion'] == '2.1') {
					$res['ContentType'] = 'text/x-vcard';
				}
				break;
			case 'text/calendar':
				if ($manufacturer == 'nokia' ||
					isset($res['ContentVersion']) &&  $res['ContentVersion'] == '1.0') {
					$res['ContentType'] = 'text/x-vcalendar';
				}
		}
		return $res;
	}

	function getPreferedContentType($type)
	{
		$_type = str_replace('./','',$type);
		switch (strtolower($_type)) {
			case 'contacts':
			case 'contact':
			case 'card':
				return 'text/vcard';
				break;

			case 'notes':
			case 'note':
				return 'text/x-vnote';
				break;

			case 'calendar':
			case 'events':
			case 'event':
			case 'tasks':
			case 'task':
			case 'jobs':
			case 'caltasks':
				return 'text/calendar';
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
			case 'contact':
			case 'card':
				return 'contacts';

			case 'notes':
			case 'note':
				return 'notes';

			case 'tasks':
			case 'task':
			case 'jobs':
				return 'tasks';

			case 'events':
			case 'event':
			case 'calendar':
				return 'calendar';

			case 'caltasks':
				return 'caltasks';

			# funambol related types
			case 'sifcalendar':
			case 'scal':
				return 'sifcalendar';

			case 'sifcontacts':
			case 'scard':
				return 'sifcontacts';

			case 'siftasks':
			case 'stask':
				return 'siftasks';

			case 'sifnotes':
			case 'snote':
				return 'sifnotes';
				
			case 'configuration':
				return 'configuration';

			default:
				Horde::logMessage("SyncML: unknown hordeType for type=$type ($_type)", __FILE__, __LINE__, PEAR_LOG_INFO);
		}
		return $_type;
	}

	/**
	* Returns the preferred contenttype of the client for the given
	* sync data type (database).
	*
	* This is passed as an option to the Horde API export functions.
	*/

	function getPreferedContentTypeClient($_sourceLocURI, $_targetLocURI = null) {
		$deviceInfo = $this->getClientDeviceInfo();
		$_sourceLocURI = strtolower($_sourceLocURI);

		if(isset($deviceInfo['dataStore'][$_sourceLocURI]['maxGUIDSize']['contentType'])) {
			$this->_maxGUIDSize = $deviceInfo['dataStore'][$this->_sourceURI]['maxGUIDSize']['contentType'];
		}

		if(isset($deviceInfo['dataStore'][$_sourceLocURI]['rxPreference']['contentType']))
		{
			$ctype = $deviceInfo['dataStore'][$_sourceLocURI]['rxPreference']['contentType'];
			$cvers = $deviceInfo['dataStore'][$_sourceLocURI]['rxPreference']['contentVersion'];
			$cfrmt = $deviceInfo['dataStore'][$_sourceLocURI]['rxPreference']['contentFormat'];
			$cprops = $deviceInfo['dataStore'][$_sourceLocURI]['properties'][$ctype][$cvers];
			$cprops['UID']['NoTruncate'] = true;
			/*
			 if (isset($deviceInfo['dataStore'][$_sourceLocURI]['maxGUIDSize'])) { 
				// get UID properties from maxGUIDSize
				$cprops['UID']['Size'] = $deviceInfo['dataStore'][$_sourceLocURI]['maxGUIDSize'];	
			}
			*/
			$clientPrefs = array(
				'ContentType'		=>	$ctype,
				'ContentVersion'	=>	$cvers,
				'ContentFormat'		=>	$cfrmt,
				'mayFragment'		=>	1,
				'Properties'		=>	$cprops,
				);
				
			if ($_targetLocURI == 'configuration') $clientPrefs['ContentFormat'] = 'b64';
			
			#Horde::logMessage('SyncML: sourceLocURI ' . $_sourceLocURI . " clientPrefs:\n"
			#	. print_r($clientPrefs, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			return $this->adjustContentType($clientPrefs, $_targetLocURI);
		}

		Horde::logMessage('SyncML: sourceLocURI ' . $_sourceLocURI . " not found:\n" . print_r($deviceInfo, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if ($_targetLocURI != null)
		{
			return $this->adjustContentType($this->getPreferedContentType($_targetLocURI), $_targetLocURI);
		}

		return PEAR::raiseError(_('sourceLocURI not found'));
	}

	/**
	* Returns the MaxGUIDSize of the client
	*/

	function getMaxGUIDSizeClient() {
		$maxGUIDSize = MAX_GUID_SIZE;

		if (isset($this->_maxGUIDSize)) {
			$maxGUIDSize = $this->_maxGUIDSize;
		}
		return $maxGUIDSize;
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
    	if (isset($this->_clientDeviceInfo) && is_array($this->_clientDeviceInfo)) {
    		// use cached information
    		return $this->_clientDeviceInfo;
    	}

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
        if (!isset($this->_clientDeviceInfo) ||
        	!is_array($this->_clientDeviceInfo) ||
        	!empty($this->_clientDeviceInfo['persistent'])) {
            return;
        }

        $dt = &$this->getDataTree();

        $s = $this->_locName . $this->_sourceURI . 'deviceInfo';
        $this->_clientDeviceInfo['persistent'] = true;

        // Set $locid.
        $info = new DataTreeObject($s);
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
            $info = new DataTreeObject($s);
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

	function incNumberOfElements() {
		$this->_numberOfElements++;
	}

	function clearNumberOfElements() {
		$this->_numberOfElements = 0;
	}

	function getNumberOfElements() {
		if (isset($this->_numberOfElements)) {
			return $this->_numberOfElements;
		} else {
			return false;
		}
	}

	function maxNumberOfElements() {
		unset($this->_numberOfElements);
	}
}
