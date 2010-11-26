<?php
/**
 * EGroupware: ActiveSync access: FMail plugin
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @subpackage activesync
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Philip Herbert <philip@knauber.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.calendar_activesync.inc.php  $
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
		error_log(__METHOD__."() returning ".array2string($folderlist));
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
		$folderObj->displayname = $GLOBALS['egw']->accounts->id2name($owner);
		if ($owner == $GLOBALS['egw_info']['user']['account_id'])
		{
			$folderObj->type = SYNC_FOLDER_TYPE_USER_APPOINTMENT;
		}
		else
		{
			$folderObj->type = SYNC_FOLDER_TYPE_APPOINTMENT;
		}
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

	/* Should return a list (array) of messages, each entry being an associative array
     * with the same entries as StatMessage(). This function should return stable information; ie
     * if nothing has changed, the items in the array must be exactly the same. The order of
     * the items within the array is not important though.
     *
     * The cutoffdate is a date in the past, representing the date since which items should be shown.
     * This cutoffdate is determined by the user's setting of getting 'Last 3 days' of e-mail, etc. If
     * you ignore the cutoffdate, the user will not be able to select their own cutoffdate, but all
     * will work OK apart from that.
  	 */
	function GetMessageList($id, $cutoffdate=NULL)
	{
		error_log (__METHOD__);
		$messagelist = array();

		return ($messagelist);

	}

	/**
	 * Get specified item from specified folder.
	 * @param string $folderid
	 * @param string $id
	 * @param int $truncsize
	 * @param int $bodypreference
	 * @param bool $mimesupport
	 * @return $messageobject|boolean false on error
	*/
	public function GetMessage($folderid, $id, $truncsize, $bodypreference=false, $mimesupport = 0)
	{
		debugLog (__METHOD__);
		$message = new SyncAppointment();
/*		$message->dtstamp;
		$message->starttime;
		$message->subject;
		$message->uid;
		$message->organizername;
		$message->organizeremail;
		$message->location;
		$message->endtime;
		$message->recurrence;		// SYNC RECURRENCE;
		$message->sensitivity;
		$message->busystatus;
		$message->alldayevent;
		$message->reminder;
		$message->meetingstatus;
		$message->attendees;	// SYNC ATTENDEE
		$message->exceptions;	// SYNC APPOINTMENTS;
		$message->deleted;
		$message->exceptionstarttime;
		$message->categories;

		if(isset($protocolversion) && $protocolversion < 12.0) {
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

	public function StatMessage($folderid, $id) {
		debugLog (__METHOD__);
	}
}
