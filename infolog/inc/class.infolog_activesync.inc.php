<?php
/**
 * EGroupware: eSync: InfoLog plugin
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @subpackage esync
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * InfoLog activesync plugin
 */
class infolog_activesync implements activesync_plugin_write
{
	/**
	 * @var BackendEGW
	 */
	private $backend;

	/**
	 * Instance of infolog_bo
	 *
	 * @var infolog_bo
	 */
	private $infolog;

	/**
	 * Mapping of ActiveSync SyncContact attributes to EGroupware InfoLog array-keys
	 *
	 * @var array
	 */
	static public $mapping = array(
		'body'	=> 'info_des',
		'categories' => 'info_cat',	// infolog supports only a single category
		'complete' => 'info_status', 	// 0 or 1 <--> 'done', ....
		'datecompleted' => 'info_datecompleted',
		'duedate' => 'info_enddate',
		'importance' => 'info_priority',	// 0=Low, 1=Normal, 2=High (EGW additional 3=Urgent)
		'sensitivity' => 'info_access',	// 0=Normal, 1=Personal, 2=Private, 3=Confiential <--> 'public', 'private'
		'startdate' => 'info_startdate',
		'subject' => 'info_subject',
		//'recurrence' => EGroupware InfoLog does NOT support recuring tasks
		//'reminderset'/'remindertime' => EGroupware InfoLog does NOT support (custom) alarms
		//'utcduedate'/'utcstartdate' what's the difference to startdate/duedate?
	);

	/**
	 * Following status gets mapped to boolean AS completed
	 *
	 * @var array
	 */
	static public $done_status = array('done', 'billed');

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
	 * Get infolog(s) folder
	 *
	 * Currently we only return an own infolog
	 *
	 * @param int $account=null account_id of addressbook or null to get array of all addressbooks
	 * @return string|array folder name of array with int account_id => folder name pairs
	 */
	private function get_folders($account=null)
	{
		$folders = array(
			$GLOBALS['egw_info']['user']['account_id'] => lang('InfoLog'),
		);
		return $account ? $folders[$account] : $folders;
	}


	/**
	 *  This function is analogous to GetMessageList.
	 *
	 *  @ToDo implement preference, include own private calendar
	 */
	public function GetFolderList()
	{
		$folderlist = array();
		foreach ($this->get_folders() as $account => $label)
		{
			$folderlist[] = array(
				'id'	=>	$this->backend->createID('infolog',$account),
				'mod'	=>	$label,
				'parent'=>	'0',
			);
		}
		//debugLog(__METHOD__."() returning ".array2string($folderlist));
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
		$folderObj->displayname = $this->get_folders($owner);

		if ($owner == $GLOBALS['egw_info']['user']['account_id'])
		{
			$folderObj->type = SYNC_FOLDER_TYPE_TASK;
		}
		else
		{
			$folderObj->type = SYNC_FOLDER_TYPE_USER_TASK;
		}
/*
		// not existing folder requested --> return false
		if (is_null($folderObj->displayname))
		{
			$folderObj = false;
			debugLog(__METHOD__."($id) returning ".array2string($folderObj));
		}
*/
		//debugLog(__METHOD__."('$id') returning ".array2string($folderObj));
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
		$this->backend->splitID($id, $type, $owner);

		$stat = array(
			'id'	 => $id,
			'mod'	=> $this->get_folders($owner),
			'parent' => '0',
		);
/*
		// not existing folder requested --> return false
		if (is_null($stat['mod']))
		{
			$stat = false;
			debugLog(__METHOD__."('$id') ".function_backtrace());
		}
*/
		//error_log(__METHOD__."('$id') returning ".array2string($stat));
		debugLog(__METHOD__."('$id') returning ".array2string($stat));
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
		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		$this->backend->splitID($id,$type,$user);
		if (!($infolog_types = $GLOBALS['egw_info']['user']['preferences']['activesync']['infolog-types']))
		{
			$infolog_types = 'task';
		}
		$filter = array(
			'filter' => $user == $GLOBALS['egw_info']['user']['account_id'] ? 'own' : 'user'.$user,
			'col_filter' => array('info_type' => explode(',', $infolog_types)),
			'date_format' => 'server',
		);

		$messagelist = array();
		if (($infologs =& $this->infolog->search($filter)))
		{
			foreach($infologs as $infolog)
			{
				$messagelist[] = $this->StatMessage($id, $infolog);
			}
		}
		//error_log(__METHOD__."('$id', $cutoffdate) filter=".array2string($filter)." returning ".count($messagelist).' entries');
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
	public function GetMessage($folderid, $id, $truncsize, $bodypreference=false, $optionbodypreference=false, $mimesupport = 0)
	{
		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		debugLog (__METHOD__."('$folderid', $id, truncsize=$truncsize, bodyprefence=$bodypreference, mimesupport=$mimesupport)");
		$this->backend->splitID($folderid, $type, $account);
		if ($type != 'infolog' || !($infolog = $this->infolog->read($id, true, 'server')))
		{
			error_log(__METHOD__."('$folderid',$id,...) Folder wrong (type=$type, account=$account) or contact not existing (read($id)=".array2string($infolog).")! returning false");
			return false;
		}
		$message = new SyncTask();
		foreach(self::$mapping as $key => $attr)
		{
			switch ($attr)
			{
				case 'info_des':
					if ($bodypreference == false)
					{
						$message->body = $infolog[$attr];
						$message->bodysize = strlen($message->body);
						$message->bodytruncated = 0;
					}
					else
					{
						debugLog("airsyncbasebody!");
						$message->airsyncbasebody = new SyncAirSyncBaseBody();
						$message->airsyncbasenativebodytype=1;
						$this->backend->note2messagenote($infolog[$attr], $bodypreference, $message->airsyncbasebody);
					}
					break;

				case 'info_cat':
					$message->$key = array();
					foreach($infolog[$attr] ? explode(',',$infolog[$attr]) : array() as $cat_id)
					{
						$message->categories[] = categories::id2name($cat_id);
					}
					break;

				case 'info_access':	// 0=Normal, 1=Personal, 2=Private, 3=Confiential <--> 'public', 'private'
					$message->$key = $infolog[$attr] == 'private' ? 2 : 0;
					break;

				case 'info_status': 	// 0 or 1 <--> 'done', ....
					$message->key = (int)(in_array($infolog[$attr], self::$done_status));
					break;

				case 'info_priority':
					if ($infolog[$attr] > 2) $infolog[$attr] = 2;	// AS does not know 3=Urgent (only 0=Low, 1=Normal, 2=High)
					// fall through
				default:
					if (!empty($infolog[$attr])) $message->$key = $infolog[$attr];
			}
		}
		//debugLog(__METHOD__."(folder='$folderid',$id,...) returning ".array2string($message));
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
	 * @param int|array $infolog info_id or array with data
	 * @return array
	 */
	public function StatMessage($folderid, $infolog)
	{
		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		if (!is_array($infolog)) $infolog = $this->infolog->read($infolog, true, 'server');

		if (!$infolog)
		{
			$stat = false;
		}
		else
		{
			$stat = array(
				'mod' => $infolog['info_datemodified'],
				'id' => $infolog['info_id'],
				'flags' => 1,
			);
		}
		//debugLog (__METHOD__."('$folderid',".array2string($id).") returning ".array2string($stat));
		//error_log(__METHOD__."('$folderid',$infolog) returning ".array2string($stat));
		return $stat;
	}

	/**
	 *  Creates or modifies a folder
	 *
	 * @param $id of the parent folder
	 * @param $oldid => if empty -> new folder created, else folder is to be renamed
	 * @param $displayname => new folder name (to be created, or to be renamed to)
	 * @param type => folder type, ignored in IMAP
	 *
	 * @return stat | boolean false on error
	 *
	 */
	public function ChangeFolder($id, $oldid, $displayname, $type)
	{
		debugLog(__METHOD__." not implemented");
	}

	/**
	 * Deletes (really delete) a Folder
	 *
	 * @param $parentid of the folder to delete
	 * @param $id of the folder to delete
	 *
	 * @return
	 * @TODO check what is to be returned
	 *
	 */
	public function DeleteFolder($parentid, $id)
	{
		debugLog(__METHOD__." not implemented");
	}

	/**
	 * Changes or adds a message on the server
	 *
	 * @param string $folderid
	 * @param int $id for change | empty for create new
	 * @param SyncContact $message object to SyncObject to create
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
		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		$this->backend->splitID($folderid, $type, $account);
		//debugLog(__METHOD__. " Id " .$id. " Account ". $account . " FolderID " . $folderid);
		if ($type != 'infolog') // || !($infolog = $this->addressbook->read($id)))
		{
			debugLog(__METHOD__." Folder wrong or infolog not existing");
			return false;
		}
		$infolog = array();
		if (empty($id) && $this->infolog->check_access(0, EGW_ACL_EDIT, $account) ||
			($infolog = $this->infolog->read($id)) && $this->infolog->check_access($infolog, EGW_ACL_EDIT))
		{
			if (!$infolog) $infolog = array();
			foreach (self::$mapping as $key => $attr)
			{
				switch ($attr)
				{
					case 'info_des':
						$infolog[$attr] = $this->backend->messagenote2note($message->body, $message->rtf, $message->airsyncbasebody);
						break;

					case 'info_cat':
						if (is_array($message->$key))
						{
							$infolog[$attr] = implode(',', array_filter($this->infolog->find_or_add_categories($message->$key, $id),'strlen'));
						}
						break;

					case 'info_access':	// 0=Normal, 1=Personal, 2=Private, 3=Confiential <--> 'public', 'private'
						$infolog[$attr] = $message->$key ? 'public' : 'private';
						break;

					case 'info_status':	// 0 or 1 in AS --> do NOT change infolog status, if it maps to identical completed boolean value
						if (in_array($infolog[$attr], self::$done_status) !== (boolean)$message->key)
						{
							$infolog[$attr] = $message->key ? 'done' : 'not-started';
						}
						break;

					case 'info_priority':	// AS does not know 3=Urgent (only 0=Low, 1=Normal, 2=High)
						if ($infolog[$attr] == 3 && $message->key == 2) break;	// --> do NOT change Urgent, if AS reports High
						// fall through
					default:
						$infolog[$attr] = $message->$key;
						break;
				}
			}
			// $infolog['info_owner'] = $account;
			if (!empty($id)) $infolog['info_id'] = $id;
			$newid = $this->infolog->write($infolog);
			debugLog(__METHOD__."($folderid,$id) infolog(".array2string($infolog).") returning ".array2string($newid));
			return $this->StatMessage($folderid, $newid);
		}
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
	 *
	 * @ToDo: If this gets implemented, we have to take into account the 'addressbook-all-in-one' pref!
	 */
	public function MoveMessage($folderid, $id, $newfolderid)
	{
		debugLog(__METHOD__."('$folderid', $id, $newfolderid) NOT implemented --> returning false");
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
		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		$ret = $this->infolog->delete($id);
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
		return false;
	}

	/**
	 * modify olflags (outlook style) flag of a message
	 *
	 * @param $folderid
	 * @param $id
	 * @param $flags
	 *
	 *
	 * @DESC The $flags parameter must contains the poommailflag Object
	 */
	function ChangeMessageFlag($folderid, $id, $flags)
	{
		return false;
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

		if ($type != 'infolog') return false;

		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		if (!($infolog_types = $GLOBALS['egw_info']['user']['preferences']['activesync']['infolog-types']))
		{
			$infolog_types = 'task';
		}

		$ctag = $this->infolog->getctag(array(
			'filter' => $owner == $GLOBALS['egw_info']['user']['account_id'] ? 'own' : 'user'.$owner,
			'info_type' => explode(',', $infolog_types),
		));

		$changes = array();	// no change
		$syncstate_was = $syncstate;

		if ($ctag !== $syncstate)
		{
			$syncstate = $ctag;
			$changes = array(array('type' => 'fakeChange'));
		}
		//debugLog(__METHOD__."('$folderid','$syncstate_was') syncstate='$syncstate' returning ".array2string($changes));
		return $changes;
	}

	/**
	 * Populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	function settings($hook_data)
	{
		translation::add_app('infolog');
		if (!isset($this->infolog)) $this->infolog = new infolog_bo();

		if (!($types = $this->infolog->enums['type']))
		{
			$types = array(
				'task' => 'Tasks',
			);
		}

		$settings['infolog-types'] = array(
			'type'   => 'multiselect',
			'label'  => 'InfoLog types to sync',
			'name'   => 'infolog-types',
			'help'   => 'Which InfoLog types should be synced with the device, default only tasks.',
			'values' => $types,
			'default' => 'task',
			'xmlrpc' => True,
			'admin'  => False,
		);

		return $settings;
	}
}
