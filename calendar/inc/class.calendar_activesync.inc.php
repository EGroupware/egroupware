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
	 * @var calendar
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
		
		$calendar = new calendar_bo;
		foreach ($calendar->list_cals() as $label => $entry)
			{
				$id = $entry['grantor'];
				$folderlist[] = $f = array(
				'id'	=>	$this->createID($id,$GLOBALS['egw']->accounts->id2name($id)),
				'mod'	=>	$GLOBALS['egw']->accounts->id2name($id),
				'parent'=>	'0',
				);
			};
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
		
		$private;
		$owner;
		
		$this->splitID($id, &$private, &$owner);
		
		$folderObj = new SyncFolder();
		$folderObj->serverid = $id;
		$folderObj->parentid = '0';
		$folderObj->displayname = $GLOBALS['egw']->accounts->id2name($owner);
		if ($owner == $GLOBALS['egw_info']->users->account_id) {
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
		$this->splitID($id, &$private, &$owner);
		
		$stat = array(
			'id'     => $id,
			'mod'    => $GLOBALS['egw']->accounts->id2name($owner),
			'parent' => '0',
		);

		return $stat;
	}
	
	public function getMessageList($folderID)
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
	

	/**
	 * Create a max. 32 hex letter ID, current 20 chars are used
	 *
	 * @param int $owner
	 * @param bool $private=false
	 * @return string
	 * @throws egw_exception_wrong_parameter
	 */
	private function createID($owner,$private=false)
	{

		$str = $this->backend->createID('calendar', (int)$private, $owner);

		//debugLog(__METHOD__."($owner,'$f',$id) type=$account, folder=$folder --> '$str'");

		return $str;
	}

	/**
	 * Split an ID string into $app, $folder and $id
	 *
	 * @param string $str
	 * @param string &$folder
	 * @param int &$id=null
	 * @throws egw_exception_wrong_parameter
	 */
	private function splitID($str,&$private,&$owner)
	{
		
		$this->backend->splitID($str, $app, $private, $owner);
		$pivate = (bool)$private;
		
		//debugLog(__METHOD__."('$str','$account','$folder',$id)");
	}

}
