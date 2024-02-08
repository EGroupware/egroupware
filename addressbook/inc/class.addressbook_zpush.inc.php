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

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * Addressbook activesync plugin
 */
class addressbook_zpush implements activesync_plugin_write, activesync_plugin_search_gal
{
	/**
	 * @var activesync_backend
	 */
	private $backend;

	/**
	 * Instance of addressbook_bo
	 *
	 * @var Api\Contacts
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
	 * @param activesync_backend $backend
	 */
	public function __construct(activesync_backend $backend)
	{
		$this->backend = $backend;
	}

	/**
	 * Get addressbooks (no extra private one and do some caching)
	 *
	 * Takes addessbook-abs and addressbook-all-in-one preference into account.
	 *
	 * @param int $account =null account_id of addressbook or null to get array of all addressbooks
	 * @param boolean $return_all_in_one =true if false and all-in-one pref is set, return all selected abs
	 * 	if true only the all-in-one ab is returned (with id of personal ab)
	 * @param booelan $ab_prefix =false prefix personal, private and accounts addressbook with lang('Addressbook').' '
	 * @return string|array addressbook name of array with int account_id => label pairs
	 */
	private function get_addressbooks($account=null,$return_all_in_one=true, $ab_prefix=false)
	{
		static $abs=null;

		if (!isset($abs) || !$return_all_in_one)
		{
			if ($return_all_in_one && !empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']))
			{
				$abs = array(
					$GLOBALS['egw_info']['user']['account_id'] => lang('All'),
				);
			}
			else
			{
				Api\Translation::add_app('addressbook');	// we need the addressbook translations

				if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

				$pref_abs = $GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-abs'] ?? [];
				if (!is_array($pref_abs))
				{
					$pref_abs = $pref_abs ? explode(',',$pref_abs) : [];
				}
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
		$ret = is_null($account) ? $abs :
			($ab_prefix && (!$account || (int)$account == (int)$GLOBALS['egw_info']['user']['account_id']) ?
				lang('Addressbook').' ' : '').$abs[$account];
		//error_log(__METHOD__."($account, $return_all_in_one, $ab_prefix) returning ".array2string($ret));
		return $ret;
	}

	/**
	 *  This function is analogous to GetMessageList.
	 *
	 *  @ToDo implement preference, include own private calendar
	 */
	public function GetFolderList()
	{
		// error_log(print_r($this->addressbook->get_addressbooks(Acl::READ),true));
		$folderlist = array();
		foreach ($this->get_addressbooks() as $account => $label)
		{
			$folderlist[] = array(
				'id'	=>	$this->backend->createID('addressbook',$account),
				'mod'	=>	$label,
				'parent'=>	'0',
			);
		}
		//ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."() returning ".array2string($folderlist));
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
		$type = $owner = null;
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
			ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."($id) returning ".array2string($folderObj));
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
		$type = $owner = null;
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
			ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$id') ".function_backtrace());
		}
*/
		//error_log(__METHOD__."('$id') returning ".array2string($stat));
		ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$id') returning ".array2string($stat));
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
	 * @param int $cutoffdate =null
	 * @return array
  	 */
	function GetMessageList($id, $cutoffdate=NULL)
	{
		unset($cutoffdate);	// not used, but required by function signature
		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

		$type = $user = null;
		$this->backend->splitID($id,$type,$user);
		$filter = array('owner' => $user);

		// handle all-in-one addressbook
		if (!empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']) &&
			$user == $GLOBALS['egw_info']['user']['account_id'])
		{
			$filter['owner'] = array_keys($this->get_addressbooks(null,false));	// false = return all selected abs
			// translate AS private AB ID to EGroupware one
			if (($key = array_search(self::PRIVATE_AB, $filter['owner'])) !== false)
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
		$criteria = null;
		if (($contacts =& $this->addressbook->search($criteria, 'contact_id,contact_etag', '', '', '',
			false, 'AND', false,$filter)))
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
	 * @param ContentParameters $contentparameters  parameters of the requested message (truncation, mimesupport etc)
	 *  object with attributes foldertype, truncation, rtftruncation, conflict, filtertype, bodypref, deletesasmoves, filtertype, contentclass, mimesupport, conversationmode
	 *  bodypref object with attributes: ]truncationsize, allornone, preview
	 * @return $messageobject|boolean false on error
	 */
	public function GetMessage($folderid, $id, $contentparameters)
	{
		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

		//$truncsize = Utils::GetTruncSize($contentparameters->GetTruncation());
		//$mimesupport = $contentparameters->GetMimeSupport();
		$bodypreference = $contentparameters->GetBodyPreference(); /* fmbiete's contribution r1528, ZP-320 */
		//ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$folderid', $id, ...) truncsize=$truncsize, mimesupport=$mimesupport, bodypreference=".array2string($bodypreference));

		$type = $account = null;
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
						if (strlen ($contact[$attr]) > 0)
						{
							$message->asbody = new SyncBaseBody();
							$this->backend->note2messagenote($contact[$attr], $bodypreference, $message->asbody);
						}
					}
					break;

				case 'jpegphoto':
					if (empty($contact[$attr]) && ($contact['files'] & Api\Contacts::FILES_BIT_PHOTO))
					{
						$contact[$attr] = file_get_contents(Api\Link::vfs_path('addressbook', $contact['id'], Api\Contacts::FILES_PHOTO));
					}
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
						$message->categories[] = Api\Categories::id2name($cat_id);
					}
					// for all addressbooks in one, add addressbook name itself as category
					if (!empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']))
					{
						$message->categories[] = $this->get_addressbooks($contact['owner'].($contact['private']?'p':''), false, true);
					}
					break;
				// HTC Desire needs at least one telefon number, otherwise sync of contact fails without error,
				// but will be retired forerver --> we always return work-phone xml element, even if it's empty
				// (Mircosoft ActiveSync Contact Class Protocol Specification says all phone-numbers are optional!)
				case 'tel_work':
					$message->$key = (string)$contact[$attr];
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
		unset($folderid);	// not used (contact_id is global), but required by function signaure
		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

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
		//ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$folderid',".array2string($id).") returning ".array2string($stat));
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
		unset($id, $oldid, $displayname, $type);	// not used, but required by function signature
		ZLog::Write(LOGLEVEL_DEBUG, __METHOD__." not implemented");
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
		unset($parentid, $id);
		ZLog::Write(LOGLEVEL_DEBUG, __METHOD__." not implemented");
	}

	/**
	 * Changes or adds a message on the server
	 *
	 * @param string $folderid
	 * @param int $id for change | empty for create new
	 * @param SyncContact $message object to SyncObject to create
	 * @param ContentParameters   $contentParameters
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
	public function ChangeMessage($folderid, $id, $message, $contentParameters)
	{
		unset($contentParameters);	// not used, but required by function signature
		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

		$type = $account = null;
		$this->backend->splitID($folderid, $type, $account);
		$is_private = false;
		if ($account == self::PRIVATE_AB)
		{
			$account = $GLOBALS['egw_info']['user']['account_id'];
			$is_private = true;

		}
		// error_log(__METHOD__. " Id " .$id. " Account ". $account . " FolderID " . $folderid);
		if ($type != 'addressbook') // || !($contact = $this->addressbook->read($id)))
		{
			ZLog::Write(LOGLEVEL_DEBUG, __METHOD__." Folder wrong or contact not existing");
			return false;
		}
		if ($account == 0)	// as a precausion, we currently do NOT allow to change Api\Accounts
		{
			ZLog::Write(LOGLEVEL_DEBUG, __METHOD__." Changing of Api\Accounts denied!");
			return false;			//no changing of Api\Accounts
		}
		$contact = array();
		if (empty($id) && ($this->addressbook->grants[$account] & Acl::EDIT) || ($contact = $this->addressbook->read($id)) && $this->addressbook->check_perms(Acl::EDIT, $contact))
		{
			// remove all fields supported by AS, leaving all unsupported fields unchanged
			$contact = array_diff_key($contact, array_flip(self::$mapping));
			foreach (self::$mapping as $key => $attr)
			{
				switch ($attr)
				{
					case 'note':
						$contact[$attr] = $this->backend->messagenote2note($message->body, $message->rtf, $message->asbody);
						break;

					case 'bday':	// zpush uses timestamp in servertime
						$contact[$attr] = $message->$key ? date('Y-m-d',$message->$key) : null;
						break;

					case 'jpegphoto':
						$contact[$attr] = base64_decode($message->$key);
						break;

					case 'cat_id':
						// for existing entries in all-in-one addressbook, remove addressbook name as category
						if ($contact && !empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']) && is_array($message->$key) &&
							($k=array_search($this->get_addressbooks($contact['owner'].($contact['private']?'p':''), false, true),$message->$key)))
						{
							unset($message->categories[$k]);
						}
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
							ZLog::Write(LOGLEVEL_DEBUG, __METHOD__. " Warning : php-imap not available");
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
			// Api\Contacts::save() keeps the owner or sets an appropriate one if none given
			if (!isset($contact['private'])) $contact['private'] = (int)$is_private;
			if (empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']))
			{
				$contact['owner'] = $account;
				$contact['private'] = (int)$is_private;
			}
			// if default addressbook for new contacts is NOT synced --> use personal addressbook
			elseif(!empty($GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default']) &&
				!in_array($GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default'],
					array_keys($this->get_addressbooks(null,false))))
			{
				$contact['owner'] = $GLOBALS['egw_info']['user']['account_id'];
			}
			if (!empty($id)) $contact['id'] = $id;
			$this->addressbook->fixup_contact($contact);
			$newid = $this->addressbook->save($contact);
			//error_log(__METHOD__."($folderid,$id) contact=".array2string($contact)." returning ".array2string($newid));
			return $this->StatMessage($folderid, $newid);
		}
		ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."($folderid, $id) returning false: Permission denied");
		return false;
	}

	/**
	 * Moves a message from one folder to another
	 *
	 * @param $folderid of the current folder
	 * @param $id of the message
	 * @param $newfolderid
     * @param ContentParameters   $contentParameters
	 *
	 * @return $newid as a string | boolean false on error
	 *
	 * After this call, StatMessage() and GetMessageList() should show the items
	 * to have a new parent. This means that it will disappear from GetMessageList() will not return the item
	 * at all on the source folder, and the destination folder will show the new message
	 *
	 * @ToDo: If this gets implemented, we have to take into account the 'addressbook-all-in-one' pref!
	 */
	public function MoveMessage($folderid, $id, $newfolderid, $contentParameters)
	{
		unset($contentParameters);	// not used, but required by function signature
		if (!empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']))
		{
			ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$folderid', $id, $newfolderid) NOT allowed for an all-in-one addressbook --> returning false");
			return false;
		}
		ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$folderid', $id, $newfolderid) NOT implemented --> returning false");
		return false;
	}


	/**
	 * Delete (really delete) a message in a folder
	 *
	 * @param $folderid
	 * @param $id
     * @param ContentParameters   $contentParameters
	 *
	 * @return boolean true on success, false on error, diffbackend does NOT use the returnvalue
	 *
	 * @DESC After this call has succeeded, a call to
	 * GetMessageList() should no longer list the message. If it does, the message will be re-sent to the PDA
	 * as it will be seen as a 'new' item. This means that if you don't implement this function, you will
	 * be able to delete messages on the PDA, but as soon as you sync, you'll get the item back
	 */
	public function DeleteMessage($folderid, $id, $contentParameters)
	{
		unset($contentParameters);	// not used, but required by function signature
		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

		$ret = $this->addressbook->delete($id);
		ZLog::Write(LOGLEVEL_DEBUG, __METHOD__."('$folderid', $id) delete($id) returned ".array2string($ret));
		return $ret;
	}

    /**
     * Changes the 'read' flag of a message on disk. The $flags
     * parameter can only be '1' (read) or '0' (unread). After a call to
     * SetReadFlag(), GetMessageList() should return the message with the
     * new 'flags' but should not modify the 'mod' parameter. If you do
     * change 'mod', simply setting the message to 'read' on the mobile will trigger
     * a full resync of the item from the server.
     *
     * @param string              $folderid            id of the folder
     * @param string              $id                  id of the message
     * @param int                 $flags               read flag of the message
     * @param ContentParameters   $contentParameters
     *
     * @access public
     * @return boolean                      status of the operation
     * @throws StatusException              could throw specific SYNC_STATUS_* exceptions
     */
	function SetReadFlag($folderid, $id, $flags, $contentParameters)
	{
		unset($folderid, $id, $flags, $contentParameters);
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
		unset($folderid, $id, $flags);
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
		$type = $owner = null;
		$this->backend->splitID($folderid, $type, $owner);

		if ($type != 'addressbook') return false;

		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();

		// handle all-in-one addressbook
		if (!empty($GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-all-in-one']) &&
			$owner == $GLOBALS['egw_info']['user']['account_id'])
		{
			$prefs_abs = $GLOBALS['egw_info']['user']['preferences']['activesync']['addressbook-abs'];
			if (!is_array($prefs_abs))
			{
				$prefs_abs = $prefs_abs ? explode(',', $prefs_abs) : [];
			}
			if (in_array('A', $prefs_abs))
			{
				$owner = null;	// all AB's
			}
			else
			{
				$owner = array_keys($this->get_addressbooks(null,false));	// false = return all selected abs
				// translate AS private AB ID to current user
				if (($key = array_search(self::PRIVATE_AB, $owner)) !== false)
				{
					unset($owner[$key]);
					if (!in_array($GLOBALS['egw_info']['user']['account_id'],$owner))
					{
						$owner[] = $GLOBALS['egw_info']['user']['account_id'];
					}
				}
			}
		}
		if ($owner == self::PRIVATE_AB)
		{
			$owner = $GLOBALS['egw_info']['user']['account_id'];
		}
		$ctag = $this->addressbook->get_ctag($owner);

		$changes = array();	// no change
		//$syncstate_was = $syncstate;

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
	 * @param array $searchquery value for keys 'query' and 'range' (eg. "0-50")
	 * @return array with just rows (no values for keys rows, status or global_search_status!)
	 * @todo search range not verified, limits might be a good idea
	 */
	function getSearchResultsGAL($searchquery)
	{
		if (!isset($this->addressbook)) $this->addressbook = new Api\Contacts();
		//error_log(__METHOD__.'('.array2string($searchquery).')');

		// only return items in given range, eg. "0-50"
		$range = false;
		if (isset($searchquery['range']) && preg_match('/^\d+-\d+$/', $searchquery['range']))
		{
			list($start,$end) = explode('-', $searchquery['range']);
			$range = array($start, $end-$start+1);	// array(start, num_entries)
		}
		//error_log(__METHOD__.'('.array2string($searchquery).') range='.array2string($range));

		$items = $filter = array();
		$filter['cols_to_search'] = array('n_fn', 'n_family', 'n_given',
						'room','org_name', 'title', 'role', 'tel_work', 'tel_home', 'tel_cell',
						'email', 'email_home');
		if (($contacts =& $this->addressbook->search($searchquery['query'], false, false, '', '%', false, 'OR', $range, $filter)))
		{
			foreach($contacts as $contact)
			{
				//$item[SYNC_GAL_ALIAS] = $contact['contact_id'];
			  	$item[SYNC_GAL_LASTNAME] = $contact['n_family'] ?? $contact['org_name'];
			  	$item[SYNC_GAL_FIRSTNAME] = $contact['n_given'];
				$item[SYNC_GAL_DISPLAYNAME] = $contact['n_fn'];
				if (!trim($item[SYNC_GAL_DISPLAYNAME])) $item[SYNC_GAL_DISPLAYNAME] = $contact['n_family'] ?: $contact['org_name'];
				$item[SYNC_GAL_EMAILADDRESS] = $contact['email'] ?? $contact['email_private'] ?? '';
				//$item['nameid'] = $searchquery;
				$item[SYNC_GAL_PHONE] = $contact['tel_work'] ?? '';
				$item[SYNC_GAL_HOMEPHONE] = $contact['tel_home'] ?? '';
				$item[SYNC_GAL_MOBILEPHONE] = $contact['tel_cell'] ?? '';
				$item[SYNC_GAL_COMPANY] = $contact['org_name'] ?? '';
				$item[SYNC_GAL_OFFICE] = $contact['room'];
				$item[SYNC_GAL_TITLE ] = $contact['title'];

			  	//do not return users without email
				if (!trim($item[SYNC_GAL_EMAILADDRESS])) continue;

				$items[] = $item;
			}
		}
		$items['searchtotal']=count($items);
		$items['range']=$searchquery['range'];
		return $items;
	}

	/**
	 * Populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	function egw_settings($hook_data)
	{
		$addressbooks = array();

		if (!isset($hook_data['setup']) && in_array($hook_data['type'], array('user', 'group')))
		{
			$user = $hook_data['account_id'];
			Api\Translation::add_app('addressbook');
			$addressbook_bo = new Api\Contacts();
			$addressbooks = $addressbook_bo->get_addressbooks(Acl::READ, null, $user);
			if ($user > 0)
			{
				unset($addressbooks[$user]);	// personal addressbook is allways synced
				if (isset($addressbooks[$user.'p']))
				{
					$addressbooks[self::PRIVATE_AB] = lang('Private');
				}
			}
			unset($addressbooks[$user.'p']);// private addressbook uses ID self::PRIVATE_AB
			$fileas_options = array('0' => lang('use addressbooks "own sorting" attribute'))+$addressbook_bo->fileas_options();
		}
		$addressbooks += array(
			'G'	=> lang('Primary Group'),
			'U' => lang('Accounts'),
			'A'	=> lang('All'),
		);
		// allow to force "none", to not show the prefs to the users
		if ($hook_data['type'] == 'forced')
		{
			$addressbooks['N'] = lang('None');
		}

		// rewriting owner=0 to 'U', as 0 get's always selected by prefs
		// not removing it for default or forced prefs based on current users pref
		if (!isset($addressbooks[0]) && (in_array($hook_data['type'], array('user', 'group')) ||
			$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '1'))
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