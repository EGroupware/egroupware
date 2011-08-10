<?php
/**
 * EGroupware: eSync: Addressbook plugin
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @subpackage esync
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Philip Herbert <philip@knauber.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Addressbook activesync plugin
 */
class addressbook_activesync implements activesync_plugin_write, activesync_plugin_search_gal
{
	/**
	 * @var BackendEGW
	 */
	private $backend;

	/**
	 * Instance of addressbook_bo
	 *
	 * @var addressbook_bo
	 */
	private $addressbook;

	/**
	 * Mapping of ActiveSync SyncContact attributes to EGroupware contact array-keys
	 *
	 * @var array
	 */
	static public $mapping = array(
		//'anniversary'	=> '',
		'assistantname'	=> 'assistent',
		'assistnamephonenumber'	=> 'tel_assistent',
		'birthday'	=>	'bday',
		'body'	=> 'note',
		//'bodysize'	=> '',
		//'bodytruncated'	=> '',
		'business2phonenumber'	=> 'tel_other',
		'businesscity'	=>	'adr_one_locality',
		'businesscountry'	=> 'adr_one_countryname',
		'businesspostalcode'	=> 'adr_one_postalcode',
		'businessstate'	=> 'adr_one_region',
		'businessstreet'	=> 'adr_one_street',
		'businessfaxnumber'	=> 'tel_fax',
		'businessphonenumber'	=> 'tel_work',
		'carphonenumber'	=> 'tel_car',
		'categories'	=> 'cat_id',
		//'children'	=> '',	// collection of 'child' elements
		'companyname'	=> 'org_name',
		'department'	=>	'org_unit',
		'email1address'	=> 'email',
		'email2address'	=> 'email_home',
		//'email3address'	=> '',
		'fileas'	=>	'n_fileas',
		'firstname'	=>	'n_given',
		'home2phonenumber'	=> 'tel_cell_private',
		'homecity'	=> 'adr_two_locality',
		'homecountry'	=> 'adr_two_countryname',
		'homepostalcode'	=> 'adr_two_postalcode',
		'homestate'	=> 'adr_two_region',
		'homestreet'	=>	'adr_two_street',
		'homefaxnumber'	=> 'tel_fax_home',
		'homephonenumber'	=>	'tel_home',
		'jobtitle'	=>	'title',	// unfortunatly outlook only has title & jobtitle, while EGw has 'n_prefix', 'title' & 'role',
		'lastname'	=> 'n_family',
		'middlename'	=> 'n_middle',
		'mobilephonenumber'	=> 'tel_cell',
		'officelocation'	=> 'room',
		//'othercity'	=> '',
		//'othercountry'	=> '',
		//'otherpostalcode'	=> '',
		//'otherstate'	=> '',
		//'otherstreet'	=> '',
		'pagernumber'	=> 'tel_pager',
		//'radiophonenumber'	=> '',
		//'spouse'	=> '',
		'suffix'	=>	'n_suffix',
		'title'	=> 'n_prefix',
		'webpage'	=> 'url',
		//'yomicompanyname'	=> '',
		//'yomifirstname'	=>	'',
		//'yomilastname'	=>	'',
		//'rtf'	=> '',
		'picture'	=> 'jpegphoto',
		//'nickname'	=>	'',
		//'airsyncbasebody'	=>	'',
	);
	/**
	 * ID of private addressbook
	 *
	 * @var int
	 */
	const PRIVATE_AB = 0x7fffffff;

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
	 * Get addressbooks (no extra private one and do some caching)
	 *
	 * Takes addessbook-abs and addressbook-all-in-one preference into account.
	 *
	 * @param int $account=null account_id of addressbook or null to get array of all addressbooks
	 * @param boolean $return_all_in_one=true if false and all-in-one pref is set, return all selected abs
	 * 	if true only the all-in-one ab is returned (with id of personal ab)
	 * @return string|array addressbook name of array with int account_id => label pairs
	 */
	private function get_addressbooks($account=null,$return_all_in_one=true)
	{
		static $abs;

		if (!isset($abs) || !$resolve_all_in_one)
		{
			if ($return_all_in_one && $GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one'])
			{
				$abs = array(
					$GLOBALS['egw_info']['user']['account_id'] => lang('All'),
				);
			}
			else
			{
				translation::add_app('addressbook');	// we need the addressbook translations

				if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

				// error_log(print_r($this->addressbook->get_addressbooks(EGW_ACL_READ),true));
				$pref_abs = $GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-abs'];
				$pref_abs = (string)$pref_abs !== '' ? explode(',',$pref_abs) : array();

				foreach ($this->addressbook->get_addressbooks() as $account_id => $label)
				{
					if ((string)$account_id == $GLOBALS['egw_info']['user']['account_id'].'p')
					{
						$account_id = self::PRIVATE_AB;
					}
					if ($account_id && in_array($account_id,$pref_abs) || in_array('A',$pref_abs) ||
						$account_id == 0 && in_array('U',$pref_abs) ||
						$account_id == $GLOBALS['egw_info']['user']['account_id'] ||	// allways sync pers. AB
						$account_id == $GLOBALS['egw_info']['user']['account_primary_group'] && in_array('G',$pref_abs))
					{
						$abs[$account_id] = $label;
					}
				}
			}
		}
		//error_log(__METHOD__."($account) returning ".array2string(is_null($account) ? $abs : $abs[$account]));
		return is_null($account) ? $abs : $abs[$account];
	}

	/**
	 *  This function is analogous to GetMessageList.
	 *
	 *  @ToDo implement preference, include own private calendar
	 */
	public function GetFolderList()
	{
		// error_log(print_r($this->addressbook->get_addressbooks(EGW_ACL_READ),true));
		$folderlist = array();
		foreach ($this->get_addressbooks() as $account => $label)
		{
			$folderlist[] = array(
				'id'	=>	$this->backend->createID('addressbook',$account),
				'mod'	=>	$label,
				'parent'=>	'0',
			);
		}
		//debugLog(__METHOD__."() returning ".array2string($folderlist));
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
		$folderObj->displayname = $this->get_addressbooks($owner);

		if ($owner == $GLOBALS['egw_info']['user']['account_id'])
		{
			$folderObj->type = SYNC_FOLDER_TYPE_CONTACT;
		}
		else
		{
			$folderObj->type = SYNC_FOLDER_TYPE_USER_CONTACT;
		}
/*
		// not existing folder requested --> return false
		if (is_null($folderObj->displayname))
		{
			$folderObj = false;
			debugLog(__METHOD__."($id) returning ".array2string($folderObj));
		}
*/
		//error_log(__METHOD__."('$id') returning ".array2string($folderObj));
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
			'mod'	=> $this->get_addressbooks($owner),
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
	 * @todo if AB supports an extra private addressbook and AS prefs want an all-in-one AB, the private AB is always included, even if not selected in the prefs
	 * @param string $id folder id
	 * @param int $cutoffdate=null
	 * @return array
  	 */
	function GetMessageList($id, $cutoffdate=NULL)
	{
		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		$this->backend->splitID($id,$type,$user);
		$filter = array('owner' => $user);

		// handle all-in-one addressbook
		if ($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one'] &&
			$user == $GLOBALS['egw_info']['user']['account_id'])
		{
			$filter['owner'] = array_keys($this->get_addressbooks(null,false));	// false = return all selected abs
			// translate AS private AB ID to EGroupware one
			if (($key == array_search(self::PRIVATE_AB, $filter['owner'])) !== false)
			{
				$filter['owner'][$key] = $GLOBALS['egw_info']['user']['account_id'].'p';
			}
		}
		// handle private/personal addressbooks
		elseif ($this->addressbook->private_addressbook &&
			($user == self::PRIVATE_AB || $user == $GLOBALS['egw_info']['user']['account_id']))
		{
			$filter['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			$filter['private'] = (int)($user == self::PRIVATE_AB);
		}

		$messagelist = array();
		if (($contacts =& $this->addressbook->search($criteria,'contact_id,contact_etag',$order_by='',$extra_cols='',$wildcard='',
			$empty=false,$op='AND',$start=false,$filter)))
		{
			foreach($contacts as $contact)
			{
				$messagelist[] = $this->StatMessage($id, $contact);
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
		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		debugLog (__METHOD__."('$folderid', $id, truncsize=$truncsize, bodyprefence=$bodypreference, mimesupport=$mimesupport)");
		$this->backend->splitID($folderid, $type, $account);
		if ($type != 'addressbook' || !($contact = $this->addressbook->read($id)))
		{
			error_log(__METHOD__."('$folderid',$id,...) Folder wrong (type=$type, account=$account) or contact not existing (read($id)=".array2string($contact).")! returning false");
			return false;
		}
		$emailname = isset($contact['n_given']) ? $contact['n_given'].' ' : '';
		$emailname .= isset($contact['n_middle']) ? $contact['n_middle'].' ' : '';
		$emailname .= isset($contact['n_family']) ? $contact['n_family']: '';
		$message = new SyncContact();
		foreach(self::$mapping as $key => $attr)
		{
			switch ($attr)
			{
				case 'note':
					if ($bodypreference == false)
					{
						$message->body = $contact[$attr];
						$message->bodysize = strlen($message->body);
						$message->bodytruncated = 0;
					}
					else
					{
						debugLog("airsyncbasebody!");
						$message->airsyncbasebody = new SyncAirSyncBaseBody();
						$message->airsyncbasenativebodytype=1;
						$this->backend->note2messagenote($contact[$attr], $bodypreference, $message->airsyncbasebody);
					}
					break;

					case 'jpegphoto':
					if (!empty($contact[$attr])) $message->$key = base64_encode($contact[$attr]);
					break;

				case 'bday':	// zpush seems to use a timestamp in utc (at least vcard backend does)
					if (!empty($contact[$attr]))
					{
            			$tz = date_default_timezone_get();
            			date_default_timezone_set('UTC');
            			$message->birthday = strtotime($contact[$attr]);
            			date_default_timezone_set($tz);
					}
					break;

				case 'cat_id':
					$message->$key = array();
					foreach($contact[$attr] ? explode(',',$contact[$attr]) : array() as $cat_id)
					{
						$message->categories[] = categories::id2name($cat_id);
					}
					break;
				case 'email':
				case 'email_home':
					if (!empty($contact[$attr]))
					{
						$message->$key = ('"'.$emailname.'"'." <$contact[$attr]>");
					}
					break;
				case 'n_fileas':
					if ($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-force-fileas'])
					{
						$message->$key = $this->addressbook->fileas($contact,$GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-force-fileas']);
						break;
					}
					// fall through
				default:
					if (!empty($contact[$attr])) $message->$key = $contact[$attr];
			}
		}
		//error_log(__METHOD__."(folder='$folderid',$id,...) returning ".array2string($message));
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
	 * @param int|array $contact contact id or array
	 * @return array
	 */
	public function StatMessage($folderid, $contact)
	{
		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		if (!is_array($contact)) $contact = $this->addressbook->read($contact);

		if (!$contact)
		{
			$stat = false;
		}
		else
		{
			$stat = array(
				'mod' => $contact['etag'],
				'id' => $contact['id'],
				'flags' => 1,
			);
		}
		//debugLog (__METHOD__."('$folderid',".array2string($id).") returning ".array2string($stat));
		//error_log(__METHOD__."('$folderid',$contact) returning ".array2string($stat));
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
		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		$this->backend->splitID($folderid, $type, $account);
		// error_log(__METHOD__. " Id " .$id. " Account ". $account . " FolderID " . $folderid);
		if ($type != 'addressbook') // || !($contact = $this->addressbook->read($id)))
		{
			debugLog(__METHOD__." Folder wrong or contact not existing");
			return false;
		}
		if ($account == 0)	// as a precausion, we currently do NOT allow to change accounts
		{
			debugLog(__METHOD__." Changing of accounts denied!");
			return false;			//no changing of accounts
		}
		$contact = array();
		if ((empty($id) && ($this->addressbook->grants[$account] & EGW_ACL_EDIT)) || ( $contact = $this->addressbook->read($id) && $this->addressbook->check_perms(EGW_ACL_EDIT, $id)))
		{
			$contact = array();
			foreach (self::$mapping as $key => $attr)
			{
				switch ($attr)
				{
					case 'note':
						$contact[$attr] = $this->backend->messagenote2note($message->body, $message->rtf, $message->airsyncbasebody);
						break;

					case 'bday':	// zpush uses timestamp in servertime
						$contact[$attr] = $message->$key ? date('Y-m-d',$message->$key) : null;
						break;

					case 'jpegphoto':
						$contact[$attr] = base64_decode($message->$key);
						break;

					case 'cat_id':
						if (is_array($message->$key))
						{
							$contact[$attr] = implode(',', array_filter($this->addressbook->find_or_add_categories($message->$key, $id),'strlen'));
						}
						break;
					case 'email':
					case 'email_home':
						if (function_exists ('imap_rfc822_parse_adrlist'))
						{
							$email_array = array_shift(imap_rfc822_parse_adrlist($message->$key,""));
							if (!empty($email_array->mailbox) && $email_array->mailbox != 'INVALID_ADDRESS' && !empty($email_array->host))
							{
								$contact[$attr] = $email_array->mailbox.'@'.$email_array->host;
							}
							else
							{
								$contact[$attr] = $message->$key;
							}
						}
						else
						{
							debugLog(__METHOD__. " Warning : php-imap not available");
							$contact[$attr] = $message->$key;
						}
						break;
					case 'n_fileas':	// only change fileas, if not forced on the client
						if (!$GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-force-fileas'])
						{
							$contact[$attr] = $message->$key;
						}
						break;
					case 'title':	// as ol jobtitle mapping changed in egw from role to title, do NOT overwrite title with value of role
						if ($id && $message->$key == $contact['role']) break;
						// fall throught
					default:
						$contact[$attr] = $message->$key;
						break;
				}
			}
			// for all-in-one addressbook, account is meaningless and wrong!
			// addressbook_bo::save() keeps the owner or sets an appropriate one if none given
			if (!$GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one'])
			{
				$contact['owner'] = $account;
			}
			if (!empty($id)) $contact['id'] = $id;
			$this->addressbook->fixup_contact($contact);
			$newid = $this->addressbook->save($contact);
			// error_log(__METHOD__."($folderid,$id) addressbook(".array2string($contact).") returning ".array2string($newid));
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
		if ($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one'])
		{
			debugLog(__METHOD__."('$folderid', $id, $newfolderid) NOT allowed for an all-in-one addressbook --> returning false");
			return false;
		}
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
		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		$ret = $this->addressbook->delete($id);
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

		if ($type != 'addressbook') return false;

		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		// handle all-in-one addressbook
		if ($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one'] &&
			$owner == $GLOBALS['egw_info']['user']['account_id'])
		{
			if (strpos($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-abs'],'A') !== false)
			{
				$owner = null;	// all AB's
			}
			else
			{
				$owner = array_keys($this->get_addressbooks(null,false));	// false = return all selected abs
				// translate AS private AB ID to current user
				if (($key == array_search(self::PRIVATE_AB, $owner)) !== false)
				{
					unset($owner[$key]);
					if (!in_array($GLOBALS['egw_info']['user']['account_id'],$owner))
					{
						$owner[] = $GLOBALS['egw_info']['user']['account_id'];
					}
				}
			}
		}
		$ctag = $this->addressbook->get_ctag($owner);

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

	/**
	 * Search global address list for a given pattern
	 *
	 * @param string $searchquery
	 * @return array with just rows (no values for keys rows, status or global_search_status!)
	 * @todo search range not verified, limits might be a good idea
	 */
	function getSearchResultsGAL($searchquery)
	{
		if (!isset($this->addressbook)) $this->addressbook = new addressbook_bo();

		$items = array();
		if (($contacts =& $this->addressbook->search($searchquery['query'], false, false, '', '%', false, 'OR')))
		{
			foreach($contacts as $contact)
			{
			  	$item['username'] = $contact['n_family'];
				$item['fullname'] = $contact['n_fn'];
				if (!trim($item['fullname'])) $item['fullname'] = $item['username'];
				$item['emailaddress'] = $contact['email'] ? $contact['email'] : (string)$contact['email_private'] ;
				$item['nameid'] = $searchquery;
				$item['phone'] = (string)$contact['tel_work'];
				$item['homephone'] = (string)$contact['tel_home'];
				$item['mobilephone'] = (string)$contact['tel_cell'];
				$item['company'] = (string)$contact['org_name'];
				$item['office'] = $contact['room'];
				$item['title'] = $contact['title'];

			  	//do not return users without email
				if (!trim($item['emailaddress'])) continue;

				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	function settings($hook_data)
	{
		$addressbooks = array();

		if (!isset($hook_data['setup']))
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
			$addressbook_bo = new addressbook_bo();
			$addressbooks = $addressbook_bo->get_addressbooks(EGW_ACL_READ);
			unset($addressbooks[$user]);	// personal addressbook is allways synced
			unset($addressbooks[$user.'p']);// private addressbook uses ID self::PRIVATE_AB

			$fileas_options = array('0' => lang('use addressbooks "own sorting" attribute'))+$addressbook_bo->fileas_options();
		}
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['private_addressbook'])
		{
			$addressbooks[self::PRIVATE_AB] = lang('Private');
		}
		$addressbooks += array(
			'G'	=> lang('Primary Group'),
			'U' => lang('Accounts'),
			'A'	=> lang('All'),
		);
		// allow to force "none", to not show the prefs to the users
		if ($GLOBALS['type'] == 'forced')
		{
			$addressbooks['N'] = lang('None');
		}

		// rewriting owner=0 to 'U', as 0 get's always selected by prefs
		if (!isset($addressbooks[0]))
		{
			unset($addressbooks['U']);
		}
		else
		{
			unset($addressbooks[0]);
		}

		$settings['addressbook-abs'] = array(
			'type'   => 'multiselect',
			'label'  => 'Additional addressbooks to sync',
			'name'   => 'addressbook-abs',
			'help'   => 'Global address search always searches in all addressbooks, so you dont need to sync all addressbooks to be able to access them, if you are online.',
			'values' => $addressbooks,
			'xmlrpc' => True,
			'admin'  => False,
		);

		$settings['addressbook-all-in-user'] = array(
			'type'   => 'check',
			'label'  => 'Sync all addressbooks as one',
			'name'   => 'addressbook-all-in-one',
			'help'   => 'Not all devices support multiple addressbooks, so you can choose to sync all above selected addressbooks as one.',
			'xmlrpc' => true,
			'admin'  => false,
			'default' => '0',
		);

		$settings['addressbook-force-fileas'] = array(
			'type'   => 'select',
			'label'  => 'Force sorting on device to',
			'name'   => 'addressbook-force-fileas',
			'help'   => 'Some devices (eg. Windows Mobil, but not iOS) sort by addressbooks "own sorting" attribute, which might not be what you want on the device. With this setting you can force the device to use a different sorting for all contacts, without changing it in addressbook.',
			'values' => $fileas_options,
			'xmlrpc' => true,
			'admin'  => false,
			'default' => '0',
		);
		return $settings;
	}
}
