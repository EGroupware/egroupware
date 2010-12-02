<?php
/**
 * EGroupware: ActiveSync access: Calendar plugin
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage activesync
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Philip Herbert <philip@knauber.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */


/**
 * Calendar activesync plugin
 *
 * Plugin creates a device specific file to map alphanumeric folder names to nummeric id's.
 */
class calendar_activesync implements activesync_plugin_read
{
	/**
	 * var BackendEGW
	 */
	private $backend;

	/**
	 * Instance of calendar_bo
	 *
	 * @var calendar_boupdate
	 */
	private $calendar;

	/**
	 * Integer id of current mail account / connection
	 *
	 * @var int
	 */
	private $account;

	/**
	 * Constructor
	 *
	 * @param BackendEGW $backend
	 */
	public function __construct(BackendEGW $backend)
	{
		$this->backend = $backend;
	}


	/**
	 *  This function is analogous to GetMessageList.
	 *
	 *  @ToDo implement preference, include own private calendar
	 */
	public function GetFolderList()
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		foreach ($this->calendar->list_cals() as $label => $entry)
		{
			$folderlist[] = $f = array(
				'id'	=>	$this->backend->createID('calendar',$entry['grantor']),
				'mod'	=>	$label,
				'parent'=>	'0',
			);
		};
		//error_log(__METHOD__."() returning ".array2string($folderlist));
		return $folderlist;
	}

	/**
	 * Get Information about a folder
	 *
	 * @param string $id
	 * @return SyncFolder|boolean false on error
	 */
	public function GetFolder($id)
	{
		$this->backend->splitID($id, $type, $owner);

		$folderObj = new SyncFolder();
		$folderObj->serverid = $id;
		$folderObj->parentid = '0';
		$folderObj->displayname = $GLOBALS['egw']->accounts->id2name($owner,'account_fullname');
		if ($owner == $GLOBALS['egw_info']['user']['account_id'])
		{
			$folderObj->type = SYNC_FOLDER_TYPE_APPOINTMENT;
		}
		else
		{
			$folderObj->type = SYNC_FOLDER_TYPE_USER_APPOINTMENT;
		}
		//error_log(__METHOD__."('$id') folderObj=".array2string($folderObj));
		return $folderObj;
	}

	/**
	 * Return folder stats. This means you must return an associative array with the
	 * following properties:
	 *
	 * "id" => The server ID that will be used to identify the folder. It must be unique, and not too long
	 *		 How long exactly is not known, but try keeping it under 20 chars or so. It must be a string.
	 * "parent" => The server ID of the parent of the folder. Same restrictions as 'id' apply.
	 * "mod" => This is the modification signature. It is any arbitrary string which is constant as long as
	 *		  the folder has not changed. In practice this means that 'mod' can be equal to the folder name
	 *		  as this is the only thing that ever changes in folders. (the type is normally constant)
	 *
	 * @return array with values for keys 'id', 'mod' and 'parent'
	 */
	public function StatFolder($id)
	{
		$folder = $this->GetFolder($id);
		$this->backend->splitID($id, $type, $owner);

		$stat = array(
			'id'     => $id,
			'mod'    => $GLOBALS['egw']->accounts->id2name($owner),
			'parent' => '0',
		);

		return $stat;
	}

	/**
	 * Should return a list (array) of messages, each entry being an associative array
     * with the same entries as StatMessage(). This function should return stable information; ie
     * if nothing has changed, the items in the array must be exactly the same. The order of
     * the items within the array is not important though.
     *
     * The cutoffdate is a date in the past, representing the date since which items should be shown.
     * This cutoffdate is determined by the user's setting of getting 'Last 3 days' of e-mail, etc. If
     * you ignore the cutoffdate, the user will not be able to select their own cutoffdate, but all
     * will work OK apart from that.
     *
     * @param string $id folder id
     * @param int $cutoffdate=null
     * @return array
  	 */
	function GetMessageList($id, $cutoffdate=NULL)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		debugLog (__METHOD__."('$id',$cutoffdate)");
		$this->backend->splitID($id,$type,$user);

		if (!$cutoffdate) $cutoffdate = $this->bo->now - 100*24*3600;	// default three month back -30 breaks all sync recurrences

		// todo return only etag relevant information
		$filter = array(
			'users' => $user,
			'start' => $cutoffdate,	// default one month back -30 breaks all sync recurrences
			'enum_recuring' => false,
			'daywise' => false,
			'date_format' => 'server',
			'filter' => 'default',	// not rejected
		);

		$messagelist = array();
		foreach ($this->calendar->search($filter) as $k => $event)
		{
			$messagelist[] = $this->StatMessage($id, $event);
		}
		return $messagelist;
	}

	/**
	 * Get specified item from specified folder.
	 *
	 * @param string $folderid
	 * @param string $id
	 * @param int $truncsize
	 * @param int $bodypreference
	 * @param bool $mimesupport
	 * @return $messageobject|boolean false on error
	*/
	public function GetMessage($folderid, $id, $truncsize, $bodypreference=false, $mimesupport = 0)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		debugLog (__METHOD__."('$folderid', $id, truncsize=$truncsize, bodyprefence=$bodypreference, mimesupport=$mimesupport)");
		$this->backend->splitID($folderid, $type, $account);
		if ($type != 'calendar' || !($event = $this->calendar->read($id,null,'ts',false,$account)))
		{
			return false;
		}
		$message = new SyncAppointment();
		// copying timestamps
		foreach(array(
			'start' => 'starttime',
			'end'   => 'endtime',
			'created' => 'dtstamp',
			'modified' => 'dtstamp',
		) as $key => $attr)
		{
			if (!empty($event[$key])) $message->$attr = $event[$key];
		}
		// copying strings
		foreach(array(
			'title' => 'subject',
			'uid'   => 'uid',
			'location' => 'location',
		) as $key => $attr)
		{
			if (!empty($event[$key])) $message->$attr = $event[$key];
		}
		$message->organizername  = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_fullname');
		$message->organizeremail = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');
		$message->location;

		$message->sensitivity = $event['public'] ? 0 : 2;	// 0=normal, 1=personal, 2=private, 3=confidential
		$message->alldayevent = (int)$this->calendar->isWholeDay($event);

		$message->attendees = array();
		foreach($event['participants'] as $uid => $status)
		{
			static $status2as = array(
				'u' => 0,	// unknown
				't' => 2,	// tentative
				'a' => 3,	// accepted
				'r' => 4,	// decline
				// 5 = not responded
			);
			static $role2as = array(
				'REQ-PARTICIPANT' => 1,	// required
				'CHAIR' => 1,			// required
				'OPT-PARTICIPANT' => 2,	// optional
				'NON-PARTICIPANT' => 2,
				// 3 = ressource
			);
			calendar_so::split_status($status, $quantity, $role);
			$attendee = new SyncAttendee();
			if (is_numeric($uid))
			{
				$attendee->name = $GLOBALS['egw']->accounts->id2name($uid,'account_fullname');
				$attendee->email = $GLOBALS['egw']->accounts->id2name($uid,'account_email');
				$attendee->status = (int)$status2as[$status];
				$attendee->type = (int)$role2as[$role];
			}
			$message->attendees[] = $attendee;
		}
		$message->categories = array();
		foreach($event['catgory'] ? explode(',',$event['category']) : array() as $cat_id)
		{
			$message->categories[] = categories::id2name($cat_id);
		}
		//$message->recurrence;		// SYNC RECURRENCE;
		//$message->busystatus;
		//$message->reminder;
		//$message->meetingstatus;
		//$message->exceptions;	// SYNC APPOINTMENTS;
		//$message->deleted;
		//$message->exceptionstarttime;
/*
		if (isset($protocolversion) && $protocolversion < 12.0) {
			$message->body;
			$message->bodytruncated;
			$message->rtf;
		}

		if(isset($protocolversion) && $protocolversion >= 12.0) {

		 	$message->airsyncbasebody;	// SYNC SyncAirSyncBaseBody
		}
*/
		return $message;
	}

	/**
	 * StatMessage should return message stats, analogous to the folder stats (StatFolder). Entries are:
     * 'id'     => Server unique identifier for the message. Again, try to keep this short (under 20 chars)
     * 'flags'     => simply '0' for unread, '1' for read
     * 'mod'    => modification signature. As soon as this signature changes, the item is assumed to be completely
     *             changed, and will be sent to the PDA as a whole. Normally you can use something like the modification
     *             time for this field, which will change as soon as the contents have changed.
     *
     * @param string $folderid
     * @param int|array $id event id or array
     * @return array
     */
	public function StatMessage($folderid, $id)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		if (!($etag = $this->calendar->get_etag($id)))
		{
			$stat = false;
			// error_log why access is denied (should nevery happen for everything returned by calendar_bo::search)
			$backup = $this->calendar->debug;
			$this->calendar->debug = 2;
			$this->check_perms(EGW_ACL_FREEBUSY, $id, 0, 'server');
			$this->calendar->debug = $backup;
		}
		else
		{
list(,,$etag) = explode(':',$etag);
			$stat = array(
				'mod' => $etag,
				'id' => is_array($id) ? $id['id'] : $id,
				'flags' => 1,
			);
		}
		debugLog (__METHOD__."('$folderid',".array2string($id).") returning ".array2string($stat));

		return $stat;
	}

	/**
	 * Return a changes array
	 *
     * if changes occurr default diff engine computes the actual changes
	 *
	 * @param string $folderid
	 * @param string &$syncstate on call old syncstate, on return new syncstate
	 * @return array|boolean false if $folderid not found, array() if no changes or array(array("type" => "fakeChange"))
	 */
	function AlterPingChanges($folderid, &$syncstate)
	{
		$this->backend->splitID($folderid, $type, $owner);

		if ($type != 'calendar') return false;

    	if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();
		$ctag = $this->calendar->get_ctag($owner);

		$changes = array();	// no change
		$syncstate_was = $syncstate;

		if ($ctag !== $syncstate)
		{
			$syncstate = $ctag;
			$changes = array(array('type' => 'fakeChange'));
		}
		//error_log(__METHOD__."('$folderid','$syncstate_was') syncstate='$syncstate' returning ".array2string($changes));
		return $changes;
	}
}
