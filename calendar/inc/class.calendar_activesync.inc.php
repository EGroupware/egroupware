<?php
/**
 * EGroupware: eSync: Calendar plugin
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage esync
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Philip Herbert <philip@knauber.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Required for TZID <--> AS timezone blog test, if script is called directly via URL
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	interface activesync_plugin_write {};
	interface activesync_plugin_meeting_requests {};
}

/**
 * Calendar eSync plugin
 *
 * Plugin to make EGroupware calendar data available
 *
 * Handling of (virtual) exceptions of recurring events:
 * ----------------------------------------------------
 * Virtual exceptions are exceptions caused by recurcences with just different participant status
 * compared to regular series (master). Real exceptions usually have different dates and/or other data.
 * EGroupware calendar data model does NOT store virtual exceptions as exceptions,
 * as participant status is stored per recurrence date and not just per event!
 *
 * --> ActiveSync protocol does NOT support different participants or status for exceptions!
 *
 * Handling of alarms:
 * ------------------
 * We report only alarms of the current user (which should ring on the device)
 * and save alarms set on the device only for the current user, if not yet there (preserving all other alarms).
 * How to deal with multiple alarms allowed in EGroupware: report earliest one to the device
 * (and hope it resyncs before next one is due, thought we do NOT report that as change currently!).
 */
class calendar_activesync implements activesync_plugin_write, activesync_plugin_meeting_requests
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
	 * Instance of addressbook_bo
	 *
	 * @var addressbook_bo
	 */
	private $addressbook;

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
	 */
	public function GetFolderList()
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		$cals = $GLOBALS['egw_info']['user']['preferences']['activesync']['calendar-cals'];
		$cals = $cals ? explode(',',$cals) : array('P');	// implicit default of 'P'
		$folderlist = array();

		foreach ($this->calendar->list_cals() as $entry)
		{
			$account_id = $entry['grantor'];
			$label = $entry['name'];
			if (in_array('A',$cals) || in_array($account_id,$cals) ||
				$account_id == $GLOBALS['egw_info']['user']['account_id'] ||	// always incl. own calendar!
				$account_id == $GLOBALS['egw_info']['user']['account_primary_group'] && in_array('G',$cals))
			{
				$folderlist[] = $f = array(
					'id'	=>	$this->backend->createID('calendar',$account_id),
					'mod'	=>	$GLOBALS['egw']->accounts->id2name($account_id,'account_fullname'),
					'parent'=>	'0',
				);
			}
		};
		//error_log(__METHOD__."() returning ".array2string($folderlist));
		debugLog(__METHOD__."() returning ".array2string($folderlist));
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
		//debugLog(__METHOD__."('$id') folderObj=".array2string($folderObj));
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
			'id'	 => $id,
			'mod'	=> $GLOBALS['egw']->accounts->id2name($owner,'account_fullname'),
			'parent' => '0',
		);
		//error_log(__METHOD__."('$id') folderObj=".array2string($stat));
		//debugLog(__METHOD__."('$id') folderObj=".array2string($stat));
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
	 * @param array $not_uids=null uids NOT to return for meeting requests
	 * @return array
  	 */
	function GetMessageList($id, $cutoffdate=NULL, array $not_uids=null)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		debugLog (__METHOD__."('$id',$cutoffdate)");
		$this->backend->splitID($id,$type,$user);

		if (!$cutoffdate) $cutoffdate = $this->bo->now - 100*24*3600;	// default three month back -30 breaks all sync recurrences

		$filter = array(
			'users' => $user,
			'start' => $cutoffdate,	// default one month back -30 breaks all sync recurrences
			'enum_recuring' => false,
			'daywise' => false,
			'date_format' => 'server',
			// default = not rejected, current user return NO meeting requests (status=unknown), as they are returned via email!
			//'filter' => $user == $GLOBALS['egw_info']['user']['account_id'] ? (is_array($not_uids) ? 'unknown' : 'not-unknown') : 'default',
			'filter' => $user == $GLOBALS['egw_info']['user']['account_id'] ? (is_array($not_uids) ? 'unknown' : 'default') : 'default',
			// @todo return only etag relevant information (seems not to work ...)
			//'cols'		=> array('egw_cal.cal_id', 'cal_start',	'recur_type', 'cal_modified', 'cal_uid', 'cal_etag'),
			'query' => array('cal_recurrence' => 0),	// do NOT return recurrence exceptions
		);

		$messagelist = array();
		foreach ($this->calendar->search($filter) as $event)
		{
			if ($not_uids && in_array($event['uid'], $not_uids)) continue;
			$messagelist[] = $this->StatMessage($id, $event);

			// add virtual exceptions for recuring events too
			// (we need to read event, as get_recurrence_exceptions need all infos!)
/*			if ($event['recur_type'] != calendar_rrule::NONE)// && ($event = $this->calendar->read($event['id'],0,true,'server')))
			{

				foreach($this->calendar->so->get_recurrence_exceptions($event,
					egw_time::$server_timezone->getName(), $cutoffdate, 0, 'all') as $recur_date)
				{
					$messagelist[] = $this->StatMessage($id, $event['id'].':'.$recur_date);
				}
			}*/
		}
		return $messagelist;
	}

	/**
	 * List all meeting requests / invitations of user NOT having a UID in $not_uids (already received by email)
	 *
	 * @param array $not_uids
	 * @param int $cutoffdate=null
	 * @return array
	 */
	function GetMeetingRequests(array $not_uids, $cutoffdate=NULL)
	{
return array();	// temporary disabling meeting requests from calendar
		$folderid = $this->backend->createID('calendar', $GLOBALS['egw_info']['user']['account_id']);	// users personal calendar

		$ret = $this->GetMessageList($folderid, $cutoffdate, $not_uids);
		// return all id's negative to not conflict with uids from fmail
		foreach($ret as &$message)
		{
			$message['id'] = -$message['id'];
		}

		debugLog(__METHOD__.'('.array2string($not_uids).", $cutoffdate) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Stat a meeting request
	 *
	 * @param int $id negative! id
	 * @return array
	 */
	function StatMeetingRequest($id)
	{
		$folderid = $this->backend->createID('calendar', $GLOBALS['egw_info']['user']['account_id']);	// users personal calendar

		$ret = $this->StatMessage($folderid, abs($id));
		$ret['id'] = $id;

		debugLog(__METHOD__."($id) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Return a meeting request as AS SyncMail object
	 *
	 * @param int $id negative! cal_id
	 * @param int $truncsize
	 * @param int $bodypreference
	 * @param $optionbodypreference
	 * @param bool $mimesupport
	 * @return SyncMail
	 */
	function GetMeetingRequest($id, $truncsize, $bodypreference=false, $optionbodypreference=false, $mimesupport = 0)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		if (!($event = $this->calendar->read(abs($id), 0, false, 'server')))
		{
			$message = false;
		}
		else
		{
			$message = new SyncMail();
			$message->read = false;
			$message->subject = $event['title'];
			$message->importance = 1;	// 0=Low, 1=Normal, 2=High
			$message->datereceived = $event['created'];
			$message->to = $message->displayto = $GLOBALS['egw_info']['user']['account_email'];
			$message->from = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_fullname').
				' <'.$GLOBALS['egw']->accounts->id2name($event['owner'],'account_email').'>';
			$message->internetcpid = 65001;
			$message->contentclass="urn:content-classes:message";

			$message->meetingrequest = self::meetingRequest($event);
			$message->messageclass = "IPM.Schedule.Meeting.Request";

			// add description as message body
			if ($bodypreference == false)
			{
				$message->body = $event['description'];
				$message->bodysize = strlen($message->body);
				$message->bodytruncated = 0;
			}
			else
			{
				$message->airsyncbasebody = new SyncAirSyncBaseBody();
				$message->airsyncbasenativebodytype=1;
				$this->backend->note2messagenote($event['description'], $bodypreference, $message->airsyncbasebody);
			}
		}
		debugLog(__METHOD__."($id) returning ".array2string($message));
		return $message;
	}

	/**
	 * Generate SyncMeetingRequest object from an event array
	 *
	 * Used by (calendar|felamimail)_activesync
	 *
	 * @param array|string $event event array or string with iCal
	 * @return SyncMeetingRequest or null ical not parsable
	 */
	public static function meetingRequest($event)
	{
		if (!is_array($event))
		{
			$ical = new calendar_ical();
			if (!($events = $ical->icaltoegw($event, '', 'utf-8')) || count($events) != 1)
			{
				debugLog(__METHOD__."('$event') error parsing iCal!");
				return null;
			}
			$event = array_shift($events);
			debugLog(__METHOD__."(...) parsed as ".array2string($event));
		}
		$message = new SyncMeetingRequest();
		// set timezone
		try {
			$as_tz = self::tz2as($event['tzid']);
			$message->timezone = base64_encode(calendar_activesync::_getSyncBlobFromTZ($as_tz));
		}
		catch(Exception $e) {
			// ignore exception, simply set no timezone, as it is optional
		}
		// copying timestamps (they are already read in servertime, so non tz conversation)
		foreach(array(
			'start' => 'starttime',
			'end'   => 'endtime',
			'created' => 'dtstamp',
		) as $key => $attr)
		{
			if (!empty($event[$key])) $message->$attr = $event[$key];
		}
		if (($message->alldayevent = (int)calendar_bo::isWholeDay($event)))
		{
			++$message->endtime;	// EGw all-day-events are 1 sec shorter!
		}
		// copying strings
		foreach(array(
			'title' => 'subject',
			'location' => 'location',
		) as $key => $attr)
		{
			if (!empty($event[$key])) $message->$attr = $event[$key];
		}
		$message->organizer = $event['organizer'];

		$message->sensitivity = $event['public'] ? 0 : 2;	// 0=normal, 1=personal, 2=private, 3=confidential

		// busystatus=(0=free|1=tentative|2=busy|3=out-of-office), EGw has non_blocking=0|1
		$message->busystatus = $event['non_blocking'] ? 0 : 2;

		// ToDo: recurring events: InstanceType, RecurrenceId, Recurrences; ...
		$message->instancetype = 0;	// 0=Single, 1=Master recurring, 2=Single recuring, 3=Exception

		$message->responserequested = 1;	//0=No, 1=Yes
		$message->disallownewtimeproposal = 1;	//1=forbidden, 0=allowed
		//$message->messagemeetingtype;	// email2

		// ToDo: alarme: Reminder

		// convert UID to GlobalObjID
		$message->globalobjid = BackendEGW::uid2globalObjId($event['uid']);

		return $message;
	}

	/**
	 * Process response to meeting request
	 *
	 * @see BackendDiff::MeetingResponse()
	 * @param int|string $requestid uid of mail with meeting request, or < 0 for cal_id, or string with iCal from fmail plugin
	 * @param string $folderid folder of meeting request mail
	 * @param int $response 1=accepted, 2=tentative, 3=decline
	 * @return int|boolean id of calendar item, false on error
	 */
	function MeetingResponse($requestid, $folderid, $response)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		static $as2status = array(	// different from self::$status2as!
			1 => 'A',
			2 => 'T',
			3 => 'D',
		);
		$status = isset($as2status[$response]) ? $as2status[$response] : 'U';
		$uid = $GLOBALS['egw_info']['user']['account_id'];

		if (!is_numeric($requestid))	// iCal from fmail
		{
			$ical = new calendar_ical();
			if (!($events = $ical->icaltoegw($requestid, '', 'utf-8')) || count($events) != 1)
			{
				debugLog(__METHOD__."('$event') error parsing iCal!");
				return null;
			}
			$parsed_event = array_shift($events);
			debugLog(__METHOD__."(...) parsed as ".array2string($parsed_event));

			// check if event already exist (invitation of or already imported by other user)
			if (!($event = $this->calendar->read($parsed_event['uid'], 0, false, 'server')))
			{
				$event = $parsed_event;	// create new event from external invitation
			}
			elseif(!isset($event['participants'][$uid]))
			{
				debugLog(__METHOD__.'('.array2string($requestid).", $folderid, $response) current user ($uid) is NO participant of event ".array2string($event));
				// maybe we should silently add him, as he might not have the rights to add him himself with calendar->update ...
			}
			elseif($event['deleted'])
			{
				debugLog(__METHOD__.'('.array2string($requestid).", $folderid, $response) event ($uid) deleted on server --> return false");
				return false;
			}
		}
		elseif($requestid > 0)	// uid from mail --> let fmail plugin call us
		{
			debugLog(__METHOD__."('$requestid', '$folderid', $response) returning NULL (fmail uid)");
			return null;
		}
		elseif (!($event = $this->calendar->read(abs($requestid), 0, false, 'server')))
		{
			debugLog(__METHOD__."('$requestid', '$folderid', $response) returning FALSE");
			return false;
		}
		// keep role and quantity as AS has no idea about it
		calendar_so::split_status($event['participants'][$uid], $quantity, $role);
		$status = calendar_so::combine_status($status,$quantity,$role);

		if ($event['id'] && isset($event['participants'][$uid]))
		{
			$ret = $this->calendar->set_status($event, $uid, $status) ? $event['id'] : false;
		}
		else
		{
			$event['participants'][$uid] = $status;
			$ret = $this->calendar->update($event, true);	// true = ignore conflicts, as there seems no conflict handling in AS
		}
		debugLog(__METHOD__.'('.array2string($requestid).", '$folderid', $response) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Conversation to AS status
	 *
	 * @var array
	 */
	static $status2as = array(
		'U' => 0,	// unknown
		'T' => 2,	// tentative
		'A' => 3,	// accepted
		'R' => 4,	// decline
		// 5 = not responded
	);
	/**
	 * Conversation to AS "roles", not really the same thing
	 *
	 * @var array
	 */
	static $role2as = array(
		'REQ-PARTICIPANT' => 1,	// required
		'CHAIR' => 1,			// required
		'OPT-PARTICIPANT' => 2,	// optional
		'NON-PARTICIPANT' => 2,
		// 3 = ressource
	);
	/**
	 * Conversation to AS recurrence types
	 *
	 * @var array
	 */
	static $recur_type2as = array(
		calendar_rrule::DAILY => 0,
		calendar_rrule::WEEKLY => 1,
		calendar_rrule::MONTHLY_MDAY => 2,	// monthly
		calendar_rrule::MONTHLY_WDAY => 3,	// monthly on nth day
		calendar_rrule::YEARLY => 5,
		// 6 = yearly on nth day (same as 5 on non-leapyears or before March on leapyears)
	);

	/**
	 * Changes or adds a message on the server
	 *
	 * Timestamps from z-push are in servertime and need to get converted to user-time, as bocalendar_update::save()
	 * expects user-time!
	 *
	 * @param string $folderid
	 * @param int $id for change | empty for create new
	 * @param SyncAppointment $message object to SyncObject to create
	 *
	 * @return array $stat whatever would be returned from StatMessage
	 *
	 * This function is called when a message has been changed on the PDA. You should parse the new
	 * message here and save the changes to disk. The return value must be whatever would be returned
	 * from StatMessage() after the message has been saved. This means that both the 'flags' and the 'mod'
	 * properties of the StatMessage() item may change via ChangeMessage().
	 * Note that this function will never be called on E-mail items as you can't change e-mail items, you
	 * can only set them as 'read'.
	 */
	public function ChangeMessage($folderid, $id, $message)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		$event = array();
		$this->backend->splitID($folderid, $type, $account);

		debugLog (__METHOD__."('$folderid', $id, ".array2string($message).") type='$type', account=$account");

		list($id,$recur_date) = explode(':',$id);

		if ($type != 'calendar' || $id && !($event = $this->calendar->read($id, $recur_date, false, 'server')))
		{
			debugLog(__METHOD__."('$folderid',$id,...) Folder wrong or event does not existing");
			return false;
		}
		if ($recur_date)	// virtual exception
		{
			// @todo check if virtual exception needs to be saved as real exception, or only stati need to be changed
			debugLog(__METHOD__."('$folderid',$id:$recur_date,".array2string($message).") handling of virtual exception not yet implemented!");
			error_log(__METHOD__."('$folderid',$id:$recur_date,".array2string($message).") handling of virtual exception not yet implemented!");
		}
		if (!$this->calendar->check_perms($id ? EGW_ACL_EDIT : EGW_ACL_ADD,$event ? $event : 0,$account))
		{
			// @todo: write in users calendar and make account only a participant
			debugLog(__METHOD__."('$folderid',$id,...) no rights to add/edit event!");
			error_log(__METHOD__."('$folderid',$id,".array2string($message).") no rights to add/edit event!");
			return false;
		}
		if (!$id) $event['owner'] = $account;	// we do NOT allow to change the owner of existing events

		$event = $this->message2event($message, $account, $event);

		// store event, ignore conflicts and skip notifications, as AS clients do their own notifications
		if (!($id = $this->calendar->update($event,$ignore_conflicts=true,$touch_modified=true,$ignore_acl=false,$updateTS=true,$messages=null, $skip_notification=true)))
		{
			debugLog(__METHOD__."('$folderid',$id,...) error saving event=".array2string($event)."!");
			return false;
		}
		// store non-delete exceptions
		if ($message->exceptions)
		{
			foreach($message->exceptions as $exception)
			{
				if (!$exception->deleted)
				{
					$ex_event = $event;
					unset($ex_event['id']);
					unset($ex_event['etag']);
					foreach($ex_event as $name => $value) if (substr($name,0,6) == 'recur_') unset($ex_event[$name]);
					$ex_event['recur_type'] = calendar_rrule::NONE;

					if ($event['id'] && ($ex_events = $this->calendar->search(array(
						'user' => $user,
						'enum_recuring' => false,
						'daywise' => false,
						'filter' => 'owner',  // return all possible entries
						'query' => array(
							'cal_uid' => $event['uid'],
							'cal_recurrence' => $exception->exceptionstarttime,	// in servertime
						),
					))))
					{
						$ex_event = array_shift($ex_events);
						$participants = $ex_event['participants'];
					}
					else
					{
						$participants = $event['participants'];
					}
					$ex_event = $this->message2event($exception, $account, $ex_event);
					$ex_event['participants'] = $participants;	// not contained in $exception
					$ex_event['reference'] = $event['id'];
					$ex_event['recurrence'] = egw_time::server2user($exception->exceptionstarttime);
					$ex_ok = $this->calendar->save($ex_event);
					debugLog(__METHOD__."('$folderid',$id,...) saving exception=".array2string($ex_event).' returned '.array2string($ex_ok));
					error_log(__METHOD__."('$folderid',$id,...) exception=".array2string($exception).") saving exception=".array2string($ex_event).' returned '.array2string($ex_ok));
				}
			}
		}
		debugLog(__METHOD__."('$folderid',$id,...) SUCESS saving event=".array2string($event).", id=$id");
		//error_log(__METHOD__."('$folderid',$id,".array2string($message).") SUCESS saving event=".array2string($event).", id=$id");
		return $this->StatMessage($folderid, $id);
	}

	/**
	 * Parse AS message into EGw event array
	 *
	 * @param SyncAppointment $message
	 * @param int $account
	 * @param array $event=array()
	 * @return array
	 */
	private function message2event(SyncAppointment $message, $account, $event=array())
	{
		// timestamps (created & modified are updated automatically)
		foreach(array(
			'start' => 'starttime',
			'end' => 'endtime',
		) as $key => $attr)
		{
			$event[$key] = egw_time::server2user($message->$attr);
		}
		// copying strings
		foreach(array(
			'title' => 'subject',
			'uid'   => 'uid',
			'location' => 'location',
		) as $key => $attr)
		{
			if (isset($message->$attr)) $event[$key] = $message->$attr;
		}

		$event['description'] = $this->backend->messagenote2note($message->body, $message->rtf, $message->airsyncbasebody);

		$event['public'] = (int)($message->sensitivity < 1);	// 0=normal, 1=personal, 2=private, 3=confidential

		// busystatus=(0=free|1=tentative|2=busy|3=out-of-office), EGw has non_blocking=0|1
		if (isset($message->busystatus))
		{
			$event['non_blocking'] = $message->busystatus ? 0 : 1;
		}

		if (($event['whole_day'] = $message->alldayevent))
		{
			if ($event['end'] == $event['start']) $event['end'] += 24*3600;	// some clients send equal start&end for 1day
			$event['end']--;	// otherwise our whole-day event code in save makes it one more day!
		}

		$participants = array();
		foreach((array)$message->attendees as $attendee)
		{
			if ($attendee->type == 3) continue;	// we can not identify resources and re-add them anyway later

			if (preg_match('/^noreply-(.*)-uid@egroupware.org$/',$attendee->email,$matches))
			{
				$uid = $matches[1];
			}
			elseif (!($uid = $GLOBALS['egw']->accounts->name2id($attendee->email,'account_email')))
			{
				$search = array(
					'email' => $attendee->email,
					'email_home' => $attendee->email,
					//'n_fn' => $attendee->name,	// not sure if we want matches without email
				);
				// search addressbook for participant
				if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();
				if ((list($data) = $this->addressbook->search($search,
					array('id','egw_addressbook.account_id as account_id','n_fn'),
					'egw_addressbook.account_id IS NOT NULL DESC, n_fn IS NOT NULL DESC',
					'','',false,'OR')))
				{
					$uid = $data['account_id'] ? (int)$data['account_id'] : 'c'.$data['id'];
				}
				else	// store just the email
				{
					$uid = 'e'.$attendee->name.' <'.$attendee->email.'>';
				}
			}
			// as status and (attendee-)type are optional, keep old status, quantity and role, if not specified
			if ($event['id'] && isset($event['participants'][$uid]))
			{
				$status = $event['participants'][$uid];
				calendar_so::split_status($status, $quantity, $role);
				//debugLog("old status for $uid is status=$status, quantity=$quantitiy, role=$role");
			}
			else	// set some defaults
			{
				$status = 'U';
				$quantitiy = 1;
				$role = 'REQ-PARTICIPANT';
				//debugLog("default status for $uid is status=$status, quantity=$quantitiy, role=$role");
			}
			if (isset($attendee->attendeestatus) && ($s = array_search($attendee->attendeestatus,self::$status2as)))
			{
				$status = $s;
			}
			if ($attendee->email == $message->organizeremail)
			{
				$role = 'CHAIR';
				$chair_set = true;
			}
			elseif (isset($attendee->attendeetype) && ($r = array_search($attendee->attendeetype,self::$role2as)) &&
				(int)self::$role2as[$role] != $attendee->attendeetype)	// if old role gives same type, use old role, as we have a lot more roles then AS
			{
				$role = $r;
			}
			//debugLog("-> status for $uid is status=$status ($s), quantity=$quantitiy, role=$role ($r)");
			$participants[$uid] = calendar_so::combine_status($status,$quantitiy,$role);
		}
		// if organizer is not already participant, add him as chair
		if (($uid = $GLOBALS['egw']->accounts->name2id($message->organizeremail,'account_email')) && !isset($participants[$uid]))
		{
			$participants[$uid] = calendar_so::combine_status($uid == $GLOBALS['egw_info']['user']['account_id'] ?
				'A' : 'U',1,'CHAIR');
			$chair_set = true;
		}
		// preserve all resource types not account, contact or email (eg. resources) for existing events
		// $account is also preserved, as AS does not add him as participant!
		foreach((array)$event['participant_types'] as $type => $parts)
		{
			if (in_array($type,array('c','e'))) continue;	// they are correctly representable in AS

			foreach($parts as $id => $status)
			{
				// accounts are represented correctly, but the event owner which is no participant in AS
				if ($type == 'u' && $id != $account) continue;

				$uid = calendar_so::combine_user($type, $id);
				if (!isset($participants[$uid]))
				{
					$participants[$uid] = $status;
				}
			}
		}
		// add calendar owner as participant, as otherwise event will NOT be in his calendar, in which it was posted
		if (!$event['id'] || !$participants || !isset($participants[$account]))
		{
			$participants[$account] = calendar_so::combine_status($account == $GLOBALS['egw_info']['user']['account_id'] ?
				'A' : 'U',1,!$chair_set ? 'CHAIR' : 'REQ-PARTICIPANT');
		}
		$event['participants'] = $participants;

		if (isset($message->categories))
		{
			$event['category'] = implode(',', array_filter($this->calendar->find_or_add_categories($message->categories, $event),'strlen'));
		}

		// check if event is recurring and import recur information (incl. timezone)
		if ($message->recurrence)
		{
			if ($message->timezone && !$event['id'])	// dont care for timezone, if no new and recurring event
			{
				$event['tzid'] = self::as2tz(self::_getTZFromSyncBlob(base64_decode($message->timezone)));
			}
			$event['recur_type'] = $message->recurrence->type == 6 ? calendar_rrule::YEARLY :
				array_search($message->recurrence->type, self::$recur_type2as);
			$event['recur_interval'] = $message->recurrence->interval;

			switch ($event['recur_type'])
			{
				case calendar_rrule::MONTHLY_WDAY:
					// $message->recurrence->weekofmonth is not explicitly stored in egw, just taken from start date
					// fall throught
				case calendar_rrule::WEEKLY:
					$event['recur_data'] = $message->recurrence->dayofweek;	// 1=Su, 2=Mo, 4=Tu, .., 64=Sa
					break;
				case calendar_rrule::MONTHLY_MDAY:
					// $message->recurrence->dayofmonth is not explicitly stored in egw, just taken from start date
					break;
				case calendar_rrule::YEARLY:
					// $message->recurrence->(dayofmonth|monthofyear) is not explicitly stored in egw, just taken from start date
					break;
			}
			if ($message->recurrence->until)
			{
				$event['recur_enddate'] = egw_time::server2user($message->recurrence->until);
			}
			$event['recur_exceptions'] = array();
			if ($message->exceptions)
			{
				foreach($message->exceptions as $exception)
				{
					$event['recur_exception'][] = egw_time::server2user($exception->exceptionstarttime);
				}
				$event['recur_exception'] = array_unique($event['recur_exception']);
			}
			if ($message->recurrence->occurrences > 0)
			{
				// calculate enddate from occurences count, as we only support enddate
				$count = $message->recurrence->occurrences;
				foreach(calendar_rrule::event2rrule($event, true) as $rtime)	// true = timestamps are user time here, because of save!
				{
					if (--$count <= 0) break;
				}
				$event['recur_enddate'] = $rtime->format('ts');
			}
		}
		// only import alarms in own calendar
		if ($message->reminder && $account == $GLOBALS['egw_info']['user']['account_id'])
		{
			foreach((array)$event['alarm'] as $alarm)
			{
				if (($alarm['all'] || $alarm['owner'] == $account) && $alarm['offset'] == 60*$message->reminder)
				{
					$alarm = true;	// alarm already exists --> do nothing
					break;
				}
			}
			if ($alarm !== true)	// new alarm
			{
				// delete all earlier alarms of that user
				// user get's per AS only the earliest alarm, as AS only supports one alarm
				// --> if a later alarm is returned, user probably modifed an existing alarm
				foreach((array)$event['alarm'] as $key => $alarm)
				{
					if ($alarm['owner'] == $account && $alarm['offset'] > 60*$message->reminder)
					{
						unset($event['alarm'][$key]);
					}
				}
				$event['alarm'][] = $alarm = array(
					'owner' => $account,
					'offset' => 60*$message->reminder,
				);
			}
		}
		return $event;
	}

	/**
	 *  Creates or modifies a folder
	 *
	 * @param string $id of the parent folder
	 * @param string $oldid => if empty -> new folder created, else folder is to be renamed
	 * @param string $displayname => new folder name (to be created, or to be renamed to)
	 * @param string $type folder type, ignored in IMAP
	 *
	 * @return array|boolean stat array or false on error
	 */
	public function ChangeFolder($id, $oldid, $displayname, $type)
	{
		debugLog(__METHOD__."('$id', '$oldid', '$displayname', $type) NOT supported!");
		return false;
	}

	/**
	 * Deletes (really delete) a Folder
	 *
	 * @param string $parentid of the folder to delete
	 * @param string $id of the folder to delete
	 *
	 * @return
	 * @TODO check what is to be returned
	 */
	public function DeleteFolder($parentid, $id)
	{
		debugLog(__METHOD__."('$parentid', '$id') NOT supported!");
		return false;
	}

	/**
	 * Moves a message from one folder to another
	 *
	 * @param $folderid of the current folder
	 * @param $id of the message
	 * @param $newfolderid
	 *
	 * @return $newid as a string | boolean false on error
	 *
	 * After this call, StatMessage() and GetMessageList() should show the items
	 * to have a new parent. This means that it will disappear from GetMessageList() will not return the item
	 * at all on the source folder, and the destination folder will show the new message
	 */
	public function MoveMessage($folderid, $id, $newfolderid)
	{
		debugLog(__METHOD__."('$folderid', $id, '$newfolderid') NOT supported!");
		return false;
	}

	/**
	 * Delete (really delete) a message in a folder
	 *
	 * @param $folderid
	 * @param $id
	 *
	 * @return boolean true on success, false on error, diffbackend does NOT use the returnvalue
	 *
	 * @DESC After this call has succeeded, a call to
	 * GetMessageList() should no longer list the message. If it does, the message will be re-sent to the PDA
	 * as it will be seen as a 'new' item. This means that if you don't implement this function, you will
	 * be able to delete messages on the PDA, but as soon as you sync, you'll get the item back
	 */
	public function DeleteMessage($folderid, $id)
	{
		if (!isset($this->caledar)) $this->calendar = new calendar_boupdate();

		$ret = $this->calendar->delete($id);
		debugLog(__METHOD__."('$folderid', $id) delete($id) returned ".array2string($ret));
		return $ret;
	}

	/**
	 * This should change the 'read' flag of a message on disk. The $flags
	 * parameter can only be '1' (read) or '0' (unread). After a call to
	 * SetReadFlag(), GetMessageList() should return the message with the
	 * new 'flags' but should not modify the 'mod' parameter. If you do
	 * change 'mod', simply setting the message to 'read' on the PDA will trigger
	 * a full resync of the item from the server
	 */
	function SetReadFlag($folderid, $id, $flags)
	{
		debugLog(__METHOD__."('$folderid', $id, ".array2string($flags)." NOT supported!");
		return false;
	}

	/**
	 * modify olflags (outlook style) flag of a message
	 *
	 * @param $folderid
	 * @param $id
	 * @param $flags
	 *
	 * @DESC The $flags parameter must contains the poommailflag Object
	 */
	function ChangeMessageFlag($folderid, $id, $flags)
	{
		debugLog(__METHOD__."('$folderid', $id, ".array2string($flags)." NOT supported!");
		return false;
	}

	/**
	 * Get specified item from specified folder.
	 *
	 * Timezone wise we supply zpush with timestamps in servertime (!), which it "converts" in streamer::formatDate($ts)
	 * via gmstrftime("%Y%m%dT%H%M%SZ", $ts) to UTC times.
	 * Timezones are only used to get correct recurring events!
	 *
	 * @param string $folderid
	 * @param string|array $id cal_id or event array (used internally)
	 * @param int $truncsize
	 * @param int|bool $bodypreference=false
	 * @param $optionbodypreference=false
	 * @param int $mimesupport=0
	 * @return SyncAppointment|boolean false on error
	 */
	public function GetMessage($folderid, $id, $truncsize, $bodypreference=false, $optionbodypreference=false, $mimesupport = 0)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		debugLog (__METHOD__."('$folderid', ".array2string($id).", truncsize=$truncsize, bodyprefence=$bodypreference, mimesupport=$mimesupport)");
		$this->backend->splitID($folderid, $type, $account);
		if (is_array($id))
		{
			$event = $id;
			$id = $event['id'];
		}
		else
		{
			list($id,$recur_date) = explode(':',$id);
			if ($type != 'calendar' || !($event = $this->calendar->read($id,$recur_date,false,'server',$account)))
			{
				error_log(__METHOD__."('$folderid', $id, ...) read($id,null,false,'server',$account) returned false");
				return false;
			}
		}
		debugLog(__METHOD__."($folderid,$id,...) start=$event[start]=".date('Y-m-d H:i:s',$event['start']).", recurrence=$event[recurrence]=".date('Y-m-d H:i:s',$event['recurrence']));
		foreach($event['recur_exception'] as $ex) debugLog("exception=$ex=".date('Y-m-d H:i:s',$ex));

		$message = new SyncAppointment();

		// set timezone
		try {
			$as_tz = self::tz2as($event['tzid']);
			$message->timezone = base64_encode(self::_getSyncBlobFromTZ($as_tz));
		}
		catch(Exception $e) {
			// ignore exception, simply set no timezone, as it is optional
		}
		// copying timestamps (they are already read in servertime, so non tz conversation)
		foreach(array(
			'start' => 'starttime',
			'end'   => 'endtime',
			'created' => 'dtstamp',
			'modified' => 'dtstamp',
		) as $key => $attr)
		{
			if (!empty($event[$key])) $message->$attr = $event[$key];
		}
		if (($message->alldayevent = (int)calendar_bo::isWholeDay($event)))
		{
			++$message->endtime;	// EGw all-day-events are 1 sec shorter!
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

		// appoint description
		if ($bodypreference == false)
		{
			$message->body = $event['description'];
			$message->bodysize = strlen($message->body);
			$message->bodytruncated = 0;
		}
		else
		{
			if (strlen($event['description']) > 0)
			{
				debugLog("airsyncbasebody!");
				$message->airsyncbasebody = new SyncAirSyncBaseBody();
				$message->airsyncbasenativebodytype=1;
				$this->backend->note2messagenote($event['description'], $bodypreference, $message->airsyncbasebody);
			}
		}
		$message->md5body = md5($event['description']);

		$message->organizername  = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_fullname');
		$message->organizeremail = $GLOBALS['egw']->accounts->id2name($event['owner'],'account_email');

		$message->sensitivity = $event['public'] ? 0 : 2;	// 0=normal, 1=personal, 2=private, 3=confidential

		// busystatus=(0=free|1=tentative|2=busy|3=out-of-office), EGw has non_blocking=0|1
		$message->busystatus = $event['non_blocking'] ? 0 : 2;

		$message->attendees = array();
		foreach($event['participants'] as $uid => $status)
		{
			// AS does NOT want calendar owner as participant
			if ($uid == $account) continue;
			calendar_so::split_status($status, $quantity, $role);
			$attendee = new SyncAttendee();
			$attendee->attendeestatus = (int)self::$status2as[$status];
			$attendee->attendeetype = (int)self::$role2as[$role];
			if (is_numeric($uid))
			{
				$attendee->name = $GLOBALS['egw']->accounts->id2name($uid,'account_fullname');
				$attendee->email = $GLOBALS['egw']->accounts->id2name($uid,'account_email');
			}
			else
			{
				list($info) = $i = $this->calendar->resources[$uid[0]]['info'] ?
					ExecMethod($this->calendar->resources[$uid[0]]['info'],substr($uid,1)) : array(false);

				if (!$info) continue;

				if (!$info['email'] && $info['responsible'])
				{
					$info['email'] = $GLOBALS['egw']->accounts->id2name($info['responsible'],'account_email');
				}
				$attendee->name = empty($info['cn']) ? $info['name'] : $info['cn'];
				$attendee->email = $info['email'];
				if ($uid[0] == 'r') $attendee->type = 3;	// 3 = resource
			}
			// email must NOT be empty, but MAY be an arbitrary text
			if (empty($attendee->email)) $attendee->email = 'noreply-'.$uid.'-uid@egroupware.org';

			$message->attendees[] = $attendee;
		}
		$message->categories = array();
		foreach($event['category'] ? explode(',',$event['category']) : array() as $cat_id)
		{
			$message->categories[] = categories::id2name($cat_id);
		}

		// recurring information, only if not a single recurrence eg. virtual exception (!$recur_date)
		if ($event['recur_type'] != calendar_rrule::NONE && !$recur_date)
		{
			$message->recurrence = $recurrence = new SyncRecurrence();
			$rrule = calendar_rrule::event2rrule($event,false);	// false = timestamps in $event are servertime
			$recurrence->type = (int)self::$recur_type2as[$rrule->type];
			$recurrence->interval = $rrule->interval;
			switch ($rrule->type)
			{
				case calendar_rrule::MONTHLY_WDAY:
					$recurrence->weekofmonth = $rrule->monthly_byday_num >= 1 ?
						$rrule->monthly_byday_num : 5;	// 1..5=last week of month, not -1
					// fall throught
				case calendar_rrule::WEEKLY:
					$recurrence->dayofweek = $rrule->weekdays;	// 1=Su, 2=Mo, 4=Tu, .., 64=Sa
					break;
				case calendar_rrule::MONTHLY_MDAY:
					$recurrence->dayofmonth = $rrule->monthly_bymonthday >= 1 ?	// 1..31
						$rrule->monthly_bymonthday : 31;	// not -1 for last day of month!
					break;
				case calendar_rrule::YEARLY:
					$recurrence->dayofmonth = (int)$rrule->time->format('d');	// 1..31
					$recurrence->monthofyear = (int)$rrule->time->format('m');	// 1..12
					break;
			}
			if ($rrule->enddate)	// enddate is only a date, but AS needs a time incl. correct starttime!
			{
				$enddate = clone $rrule->time;
				$enddate->setDate($rrule->enddate->format('Y'), $rrule->enddate->format('m'),
					$rrule->enddate->format('d'));
				$recurrence->until = $enddate->format('server');
			}

			if ($rrule->exceptions)
			{
				// search real / non-virtual exceptions
				if (!empty($event['uid']))
				{
					$ex_events =& $this->calendar->search(array(
						'query' => array('cal_uid' => $event['uid']),
						'filter' => 'owner',  // return all possible entries
						'daywise' => false,
						'date_format' => 'server',
					));
				}
				else
				{
					debugLog(__METHOD__.__LINE__." Exceptions found but no UID given for Event:".$event['id'].' Exceptions:'.array2string($event['recur_exception']));
				}
				if (count($ex_events)>=1) debugLog(__METHOD__.__LINE__." found ".count($ex_events)." exeptions for event with UID/ID:".$event['uid'].'/'.$event['id']);

				$message->exceptions = array();
				foreach($ex_events as $ex_event)
				{
					if ($ex_event['id'] == $event['id']) continue;	// ignore series master
					$exception = $this->GetMessage($folderid, $ex_event, $truncsize, $bodypreference, $mimesupport);
					$exception->exceptionstarttime = $exception_time = $ex_event['recurrence'];
					foreach(array('attendees','recurrence','uid','timezone','organizername','organizeremail') as $not_supported)
					{
						$exception->$not_supported = null;	// not allowed in exceptions :-(
					}
					$exception->deleted = 0;
					if (($key = array_search($exception_time,$event['recur_exception'])) !== false)
					{
						unset($event['recur_exception'][$key]);
					}
					debugLog(__METHOD__."() added exception ".date('Y-m-d H:i:s',$exception_time).' '.array2string($exception));
					$message->exceptions[] = $exception;
				}
				// add rest of exceptions as deleted
				foreach($event['recur_exception'] as $exception_time)
				{
					if (!empty($exception_time))
					{
						if (empty($event['uid'])) debugLog(__METHOD__.__LINE__." BEWARE no UID given for this event:".$event['id'].' but exception is set for '.$exception_time);
						$exception = new SyncAppointment();	// exceptions seems to be full SyncAppointments, with only starttime required
						$exception->deleted = 1;
						$exception->exceptionstarttime = $exception_time;
						debugLog(__METHOD__."() added deleted exception ".date('Y-m-d H:i:s',$exception_time).' '.array2string($exception));
						$message->exceptions[] = $exception;
					}
				}
			}
			/* disabled virtual exceptions for now, as AS does NOT support changed participants or status
			// add virtual exceptions here too (get_recurrence_exceptions should be able to return real-exceptions too!)
			foreach($this->calendar->so->get_recurrence_exceptions($event,
				egw_time::$server_timezone->getName(), $cutoffdate, 0, 'all') as $exception_time)
			{
				// exceptions seems to be full SyncAppointments, with only exceptionstarttime required
				$exception = $this->GetMessage($folderid, $event['id'].':'.$exception_time, $truncsize, $bodypreference, $mimesupport);
				$exception->deleted = 0;
				$exception->exceptionstarttime = $exception_time;
				debugLog(__METHOD__."() added virtual exception ".date('Y-m-d H:i:s',$exception_time).' '.array2string($exception));
				$message->exceptions[] = $exception;
			}*/
			//debugLog(__METHOD__."($id) message->exceptions=".array2string($message->exceptions));
		}
		// only return alarms if in own calendar
		if ($account == $GLOBALS['egw_info']['user']['account_id'] && $event['alarm'])
		{
			foreach($event['alarm'] as $alarm)
			{
				if ($alarm['all'] || $alarm['owner'] == $account)
				{
					$message->reminder = $alarm['offset']/60;	// is in minutes, not seconds as in EGw
					break;	// AS supports only one alarm! (we use the next/earliest one)
				}
			}
		}
		//$message->meetingstatus;

		return $message;
	}

	/**
	 * StatMessage should return message stats, analogous to the folder stats (StatFolder). Entries are:
	 * 'id'	 => Server unique identifier for the message. Again, try to keep this short (under 20 chars)
	 * 'flags'	 => simply '0' for unread, '1' for read
	 * 'mod'	=> modification signature. As soon as this signature changes, the item is assumed to be completely
	 *			 changed, and will be sent to the PDA as a whole. Normally you can use something like the modification
	 *			 time for this field, which will change as soon as the contents have changed.
	 *
	 * @param string $folderid
	 * @param int|array $id event id or array or cal_id:recur_date for virtual exception
	 * @return array
	 */
	public function StatMessage($folderid, $id)
	{
		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

		if (!($etag = $this->calendar->get_etag($id, $nul, true, true)))	// last true: $only_master=true
		{
			$stat = false;
			// error_log why access is denied (should never happen for everything returned by calendar_bo::search)
			$backup = $this->calendar->debug;
			//$this->calendar->debug = 2;
			list($id) = explode(':',$id);
			$this->calendar->check_perms(EGW_ACL_FREEBUSY, $id, 0, 'server');
			$this->calendar->debug = $backup;
		}
		else
		{
			$stat = array(
				'mod' => $etag,
				'id' => is_array($id) ? $id['id'] : $id,
				'flags' => 1,
			);
		}
		//debugLog (__METHOD__."('$folderid',".array2string(is_array($id) ? $id['id'] : $id).") returning ".array2string($stat));

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
		debugLog(__METHOD__."('$folderid','$syncstate') type='$type', owner=$owner");

		if ($type != 'calendar') return false;

		if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();
		$ctag = $this->calendar->get_ctag($owner,'owner',true);	// true only consider recurrence master
		// workaround for syncstate = 0 when calendar is empty causes synctate to not return 0 but array resulting in foldersync loop
		if ($ctag == 0) $ctag = 1;
		$changes = array();	// no change
		$syncstate_was = $syncstate;

		if ($ctag !== $syncstate)
		{
			$syncstate = $ctag;
			$changes = array(array('type' => 'fakeChange'));
		}
		//error_log(__METHOD__."('$folderid','$syncstate_was') syncstate='$syncstate' returning ".array2string($changes));
		debugLog(__METHOD__."('$folderid','$syncstate_was') syncstate='$syncstate' returning ".array2string($changes));
		return $changes;
	}

	/**
	 * Return AS timezone data from given timezone and time
	 *
	 * AS spezifies the timezone by the date it changes to dst and back and the offsets.
	 * Unfortunately this data is not available from PHP's DateTime(Zone) class.
	 * Just given the exact time of the next transition, which is available via DateTimeZone::getTransistions(),
	 * will fail for recurring events longer then a year, as the transition date/time changes!
	 *
	 * We use now the RRule given in the iCal timezone defintion available via calendar_timezones::tz2id($tz,'component').
	 *
	 * Not every timezone uses DST, in which case only bias matters and dstbias=0
	 * (probably all other values should be 0, as MapiMapping::_getGMTTZ() in backend/ics.php does it).
	 *
	 * For southern hermisphere DST in southern winter (eg. January), Active Sync implementation of iPhone
	 * uses a negative dstbias (eg. -60) and an accordingly moved start- and end-time.
	 * For Pacific/Auckland TZ iPhone AS implementation uses -720=-12h instead of 720=+12h.
	 * Both are corrected now in our Active Sync timezone generation, as we can not find
	 * matching timezones for incomming timezone data. iPhone seems not to care on receiving about the above.
	 *
	 * @param string|DateTimeZone $tz timezone, timezone name (eg. "Europe/Berlin") or ical with VTIMEZONE
	 * @param int|string|DateTime $ts=null time for which active sync timezone data is requested, default current time
	 * @return array with values for keys:
	 * - "bias": timezone offset from UTC in minutes for NO DST
	 * - "dstendmonth", "dstendday", "dstendweek", "dstendhour", "dstendminute", "dstendsecond", "dstendmillis"
	 * - "stdbias": seems not to be used
	 * - "dststartmonth", "dststartday", "dststartweek", "dststarthour", "dststartminute", "dststartsecond", "dststartmillis"
	 * - "dstbias": offset in minutes for no DST --> DST, usually 60 or 0 for no DST
	 *
	 * @link http://download.microsoft.com/download/5/D/D/5DD33FDF-91F5-496D-9884-0A0B0EE698BB/%5BMS-ASDTYPE%5D.pdf
	 * @throws egw_exception_assertion_failed if no vtimezone data found for given timezone
	 */
	static public function tz2as($tz,$ts=null)
	{
/*
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
--> bias: -60 min
TZOFFSETTO:+0200
--> dstbias: +1000 - +0200 = +0100 = -60 min
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
--> dststart: month: 3, day: SU(0???), week: -1|5, hour: 2, minute, second, millis: 0
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
--> dstend: month: 10, day: SU(0???), week: -1|5, hour: 3, minute, second, millis: 0
END:STANDARD
END:VTIMEZONE
*/
		$data = array(
			'bias' => 0,
			'stdbias' => 0,
			'dstbias' => 0,
			'dststartyear' => 0, 'dststartmonth' => 0, 'dststartday' => 0, 'dststartweek' => 0,
			'dststarthour' => 0, 'dststartminute' => 0, 'dststartsecond' => 0, 'dststartmillis' => 0,
			'dstendyear' => 0, 'dstendmonth' => 0, 'dstendday' => 0, 'dstendweek' => 0,
			'dstendhour' => 0, 'dstendminute' => 0, 'dstendsecond' => 0, 'dstendmillis' => 0,
		);

		$name = $component = is_a($tz,'DateTimeZone') ? $tz->getName() : $tz;
		if (strpos($component, 'VTIMEZONE') === false) $component = calendar_timezones::tz2id($name,'component');
		// parse ical timezone defintion
		$ical = self::ical2array($ical=$component);
		$standard = $ical['VTIMEZONE']['STANDARD'];
		$daylight = $ical['VTIMEZONE']['DAYLIGHT'];

		if (!isset($standard))
		{
			if (preg_match('/^etc\/gmt([+-])([0-9]+)$/i',$name,$matches))
			{
				$standard = array(
					'TZOFFSETTO'   => sprintf('%s%02d00',$matches[1],$matches[2]),
					'TZOFFSETFROM' => sprintf('%s%02d00',$matches[1],$matches[2]),
				);
				unset($daylight);
			}
			else
			{
				throw new egw_exception_assertion_failed("NO standard component for '$name' in '$component'!");
			}
		}
		// get bias and dstbias from standard component, which is present in all tz's
		// (dstbias is relative to bias and almost always 60 or 0)
		$data['bias'] = -(60 * substr($standard['TZOFFSETTO'],0,-2) + substr($standard['TZOFFSETTO'],-2));
		$data['dstbias'] = -(60 * substr($standard['TZOFFSETFROM'],0,-2) + substr($standard['TZOFFSETFROM'],-2) + $data['bias']);

		// check if we have an additional DAYLIGHT component and both have a RRULE component --> tz uses daylight saving
		if (isset($standard['RRULE']) && isset($daylight) && isset($daylight['RRULE']))
		{
			foreach(array('dststart' => $daylight,'dstend' => $standard) as $prefix => $comp)
			{
				if (preg_match('/FREQ=YEARLY;BYDAY=(.*);BYMONTH=(\d+)/',$comp['RRULE'],$matches))
				{
					$data[$prefix.'month'] = (int)$matches[2];
					$data[$prefix.'week'] = (int)$matches[1];
					// -1 for last week might be 5 for as as in recuring events definition
					// seems for start 1SU is always returned with week=5, like -1SU
					if ($data[$prefix.'week'] < 0 || $prefix == 'dststart' && $matches[1] == '1SU')
					{
						$data[$prefix.'week'] = 5;
					}
					// if both start and end use 1SU use week=5 and decrement month
					if ($prefix == 'dststart') $start_byday = $matches[1];
					if ($prefix == 'dstend' && $matches[1] == '1SU' && $start_byday == '1SU')
					{
						$data[$prefix.'week'] = 5;
						if ($prefix == 'dstend') $data[$prefix.'month'] -= 1;
					}
					static $day2int = array('SU'=>0,'MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6);
					$data[$prefix.'day'] = (int)$day2int[substr($matches[1],-2)];
				}
				if (preg_match('/^\d{8}T(\d{6})$/',$comp['DTSTART'],$matches))
				{
					$data[$prefix.'hour'] = (int)substr($matches[1],0,2)+($prefix=='dststart'?-1:1)*$data['dstbias']/60;
					$data[$prefix.'minute'] = (int)substr($matches[1],2,2)+($prefix=='dststart'?-1:1)*$data['dstbias']%60;
					$data[$prefix.'second'] = (int)substr($matches[1],4,2);
				}
			}
			// for southern hermisphere, were DST is in January, we have to swap start- and end-hour/-minute
			if ($data['dststartmonth'] > $data['dstendmonth'])
			{
				$start = $data['dststarthour'];   $data['dststarthour'] = $data['dstendhour'];     $data['dstendhour'] = $start;
				$start = $data['dststartminute']; $data['dststartminute'] = $data['dstendminute']; $data['dstendminute'] = $start;
			}
		}
		//error_log(__METHOD__."('$name') returning ".array2string($data));
		return $data;
	}

	/**
	 * Simple iCal parser:
	 *
	 * BEGIN:VTIMEZONE
	 * TZID:Europe/Berlin
	 * X-LIC-LOCATION:Europe/Berlin
	 * BEGIN:DAYLIGHT
	 * TZOFFSETFROM:+0100
	 * TZOFFSETTO:+0200
	 * TZNAME:CEST
	 * DTSTART:19700329T020000
	 * RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
	 * END:DAYLIGHT
	 * BEGIN:STANDARD
	 * TZOFFSETFROM:+0200
	 * TZOFFSETTO:+0100
	 * TZNAME:CET
	 * DTSTART:19701025T030000
	 * RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
	 * END:STANDARD
	 * END:VTIMEZONE
	 *
	 * Array
	 * (
	 *	 [VTIMEZONE] => Array
	 *		 (
	 *			 [TZID] => Europe/Berlin
	 *			 [X-LIC-LOCATION] => Europe/Berlin
	 *			 [DAYLIGHT] => Array
	 *				 (
	 *					 [TZOFFSETFROM] => +0100
	 *					 [TZOFFSETTO] => +0200
	 *					 [TZNAME] => CEST
	 *					 [DTSTART] => 19700329T020000
	 *					 [RRULE] => FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
	 *				 )
	 *			 [STANDARD] => Array
	 *				 (
	 *					 [TZOFFSETFROM] => +0200
	 *					 [TZOFFSETTO] => +0100
	 *					 [TZNAME] => CET
	 *					 [DTSTART] => 19701025T030000
	 *					 [RRULE] => FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
	 *				 )
	 *		 )
	 * )
	 *
	 * @param string|array $ical lines of ical file
	 * @param string $component=null
	 * @return array with parsed ical components
	 */
	static public function ical2array(&$ical,$component=null)
	{
		$arr = array();
		if (!is_array($ical)) $ical = preg_split("/[\r\n]+/m", $ical);
		while (($line = array_shift($ical)))
		{
			list($name,$value) = explode(':',$line,2);
			if ($name == 'BEGIN')
			{
				$arr[$value] = self::ical2array($ical,$value);
			}
			elseif($name == 'END')
			{
				break;
			}
			else
			{
				$arr[$name] = $value;
			}
		}
		return $arr;
	}

	/**
	 * Get timezone from AS timezone data
	 *
	 * Here we can only loop through all available timezones (starting with the users timezone) and
	 * try to find a timezone matching the change data and offsets specified in $data.
	 * This conversation is not unique, as multiple timezones can match the given data or none!
	 *
	 * @param array $data
	 * @return string timezone name, eg. "Europe/Berlin" or 'UTC' if NO matching timezone found
	 */
	public static function as2tz(array $data)
	{
		static $cache;	// some caching withing the request

		unset($data['name']);	// not used, but can stall the match

		$key = serialize($data);

		for($n = 0; !isset($cache[$key]); ++$n)
		{
			if (!$n)	// check users timezone first
			{
				$tz = egw_time::$user_timezone->getName();
			}
			elseif (!($tz = calendar_timezones::id2tz($n)))	// no further timezones to check
			{
				$cache[$key] = 'UTC';
				error_log(__METHOD__.'('.array2string($data).') NO matching timezone found --> using UTC now!');
				break;
			}
			try {
				if (self::tz2as($tz) == $data)
				{
					$cache[$key] = $tz;
					break;
				}
			}
			catch(Exception $e) {
				// simpy ignore that, as it only means $tz can NOT be converted, because it has no VTIMEZONE component
			}
		}
		return $cache[$key];
	}

	/**
	 * Unpack timezone info from Sync
	 *
	 * copied from backend/ics.php
	 */
	static public function _getTZFromSyncBlob($data)
	{
		$tz = unpack(	"lbias/a64name/vdstendyear/vdstendmonth/vdstendday/vdstendweek/vdstendhour/vdstendminute/vdstendsecond/vdstendmillis/" .
						"lstdbias/a64name/vdststartyear/vdststartmonth/vdststartday/vdststartweek/vdststarthour/vdststartminute/vdststartsecond/vdststartmillis/" .
						"ldstbias", $data);

		return $tz;
	}

	/**
	 * Pack timezone info for Sync
	 *
	 * copied from backend/ics.php
	 */
	static public function _getSyncBlobFromTZ($tz)
	{
		$packed = pack("la64vvvvvvvv" . "la64vvvvvvvv" . "l",
				$tz["bias"], "", 0, $tz["dstendmonth"], $tz["dstendday"], $tz["dstendweek"], $tz["dstendhour"], $tz["dstendminute"], $tz["dstendsecond"], $tz["dstendmillis"],
				$tz["stdbias"], "", 0, $tz["dststartmonth"], $tz["dststartday"], $tz["dststartweek"], $tz["dststarthour"], $tz["dststartminute"], $tz["dststartsecond"], $tz["dststartmillis"],
				$tz["dstbias"]);

		return $packed;
	}

	/**
	 * Populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	function settings($hook_data)
	{
		$cals = array();
		if (!$hook_data['setup'])
		{
			if (!isset($this->calendar)) $this->calendar = new calendar_boupdate();

			foreach ($this->calendar->list_cals() as $entry)
			{
				$account_id = $entry['grantor'];
				if ($account_id != $GLOBALS['egw_info']['user']['account_id'])
				{
					$cals[$account_id] = $entry['name'];
				}
			}
		}
		$cals['G'] = lang('Primary group');
		$cals['A'] = lang('All');
		// allow to force "none", to not show the prefs to the users
		if ($GLOBALS['type'] == 'forced')
		{
			$cals['N'] = lang('None');
		}
		$settings['calendar-cals'] = array(
			'type'   => 'multiselect',
			'label'  => 'Additional calendars to sync',
			'help'   => 'Not all devices support additonal calendars. Your personal calendar is always synchronised.',
			'name'   => 'calendar-cals',
			'values' => $cals,
			'xmlrpc' => True,
			'admin'  => False,
		);

		return $settings;
	}
}

/**
 * Testcode for active sync timezone stuff
 *
 * You need to comment implements activesync_plugin_write
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)	// some tests
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login'
		)
	);
	require_once('../../header.inc.php');
	ini_set('display_errors',1);
	error_reporting(E_ALL & ~E_NOTICE);

	echo "<html><head><title>Conversation of ActiveSync Timezone Blobs to TZID's</title></head>\n<body>\n";
	echo "<h3>Conversation of ActiveSync Timezone Blobs to TZID's</h3>\n";
	echo "<table border='1'>\n<tbody>\n";
	echo "<tr><th>TZID</th><th>bias</th><th>dstbias</th></th><th>dststart</th><th>dstend</th><th>matched TZID</th></tr>\n";
	echo "<script>
	function toggle_display(pre)
	{
		pre.style.display = pre.style.display && pre.style.display == 'none' ? 'block' : 'none';
	}
	</script>\n";

	// TZID => AS timezone blobs reported by various devices
	foreach(array(
		'Europe/Berlin'    => 'xP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAMAAAAAAAAAxP///w==',
		'Europe/Helsinki'  => 'iP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAQAAAAAAAAAxP///w==',
		'Asia/Tokyo'       => '5P3//wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAxP///w==',
		'Atlantic/Azores'  => 'PAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAIAAAAAAAAAxP///w==',
		'America/Los_Angeles' => '4AEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAABAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAACAAMAAAAAAAAAxP///w==',
		'America/New_York' => 'LAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAABAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAACAAMAAAAAAAAAxP///w==',
		'Pacific/Auckland' => 'MP3//wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAAAABAAIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAkAAAAFAAMAAAAAAAAAxP///w==',
		'Australia/Sydney' => 'qP3//wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAFAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAoAAAAFAAIAAAAAAAAAxP///w==',
		'Etc/GMT+3'        => 'TP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==',
	) as $tz => $sync_blob)
	{
		// get as timezone data for a given timezone
		$ical = calendar_timezones::tz2id($tz,'component');
		//echo "<pre>".print_r($ical,true)."</pre>\n";
		$ical_arr = calendar_activesync::ical2array($ical_tz=$ical);
		//echo "<pre>".print_r($ical_arr,true)."</pre>\n";
		$as_tz = calendar_activesync::tz2as($tz);
		//echo "$tz=<pre>".print_r($as_tz,true)."</pre>\n";

		$as_tz_org = calendar_activesync::_getTZFromSyncBlob(base64_decode($sync_blob));
		//echo "sync_blob=<pre>".print_r($as_tz_org,true)."</pre>\n";

		// find matching timezone from as data
		// this returns the FIRST match, which is in case of Pacific/Auckland eg. Antarctica/McMurdo ;-)
		$matched = calendar_activesync::as2tz($as_tz);
		//echo array2string($matched);

		echo "<tr><td><b onclick='toggle_display(this.nextSibling);' style='cursor:pointer;'>$tz</b><pre style='margin:0; font-size: 90%; display:none;'>$ical</pre></td><td>$as_tz_org[bias]<br/>$as_tz[bias]</td><td>$as_tz_org[dstbias]<br/>$as_tz[dstbias]</td>\n";
		foreach(array('dststart','dstend') as $prefix)
		{
			echo "<td>\n";
			foreach(array($as_tz_org,$as_tz) as $n => $arr)
			{
				$parts = array();
				foreach(array('year','month','day','week','hour','minute','second') as $postfix)
				{
					$failed = $n && $as_tz_org[$prefix.$postfix] !== $as_tz[$prefix.$postfix];
					$parts[] = ($failed?'<font color="red">':'').$arr[$prefix.$postfix].($failed?'</font>':'');
				}
				echo implode(' ', $parts).(!$n?'<br/>':'');
			}
			echo "</td>\n";
		}
		echo "<td>&nbsp;<br/>".($matched=='UTC'?'<font color="red">':'').$matched.($matched=='UTC'?'</font>':'')."</td></tr>\n";
	}
	echo "</tbody></table>\n";
	echo "</body></html>\n";
}
