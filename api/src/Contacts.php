<?php
/**
 * EGroupware API: Contacts
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package api
 * @subpackage contacts
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api;

use calendar_bo;  // to_do: do NOT require it, just use if there
use libphonenumber\PhoneNumberUtil;

/**
 * Business object for contacts
 */
class Contacts extends Contacts\Storage
{

	/**
	 * Birthdays are read into the cache, cache is expired when a
	 * birthday changes, or after 10 days.
	 */
	const BIRTHDAY_CACHE_TIME = 864000; /* 10 days*/

	/**
	 * Custom ACL allowing to share into the AB / setting shared_with
	 */
	const ACL_SHARED = Acl::CUSTOM1;
	/**
	 * Mask to allow to share into the AB, at least one of the following need to be set:
	 * - custom ACL_SHARED
	 * - ACL::EDIT
	 */
	const CHECK_ACL_SHARED = Acl::EDIT|self::ACL_SHARED;

	/**
	 * @var int $now_su actual user (!) time
	 */
	var $now_su;

	/**
	 * @var array $timestamps timestamps
	 */
	var $timestamps = array('modified','created');

	/**
	 * @var array $fileas_types
	 */
	var $fileas_types = array(
		'org_name: n_family, n_given',
		'org_name: n_family, n_prefix',
		'org_name: n_given n_family',
		'org_name: n_fn',
		'org_name, org_unit: n_family, n_given',
		'org_name, adr_one_locality: n_family, n_given',
		'org_name, org_unit, adr_one_locality: n_family, n_given',
		'n_family, n_given: org_name',
		'n_family, n_given (org_name)',
		'n_family, n_prefix: org_name',
		'n_given n_family: org_name',
		'n_prefix n_family: org_name',
		'n_fn: org_name',
		'org_name',
		'org_name - org_unit',
		'n_given n_family',
		'n_prefix n_family',
		'n_family, n_given',
		'n_family, n_prefix',
		'n_fn',
		'n_family, n_given (bday)',
	);

	/**
	 * @var array $org_fields fields belonging to the (virtual) organisation entry
	 */
	var $org_fields = array(
		'org_name',
		'org_unit',
		'adr_one_street',
		'adr_one_street2',
		'adr_one_locality',
		'adr_one_region',
		'adr_one_postalcode',
		'adr_one_countryname',
		'adr_one_countrycode',
		'label',
		'tel_work',
		'tel_fax',
		'tel_assistent',
		'assistent',
		'email',
		'url',
		'tz',
	);

	/**
	 * Which fields is a (non-admin) user allowed to edit in his own account
	 *
	 * @var array
	 */
	var $own_account_acl;

	/**
	 * @var double $org_common_factor minimum percentage of the contacts with identical values to construct the "common" (virtual) org-entry
	 */
	var $org_common_factor = 0.6;

	var $contact_fields = array();
	var $business_contact_fields = array();
	var $home_contact_fields = array();

	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	var $logfile = '/tmp/log-addressbook_bo';

	/**
	 * Number and message of last error or false if no error, atm. only used for saving
	 *
	 * @var string/boolean
	 */
	var $error;
	/**
	 * Addressbook preferences of the user
	 *
	 * @var array
	 */
	var $prefs;
	/**
	 * Default addressbook for new contacts, if no addressbook is specified (user preference)
	 *
	 * @var int
	 */
	var $default_addressbook;
	/**
	 * Default addressbook is the private one
	 *
	 * @var boolean
	 */
	var $default_private;
	/**
	 * Use a separate private addressbook (former private flag), for contacts not shareable via regular read acl
	 *
	 * @var boolean
	 */
	var $private_addressbook = false;
	/**
	 * Categories object
	 *
	 * @var Categories
	 */
	var $categories;

	/**
	* Tracking changes
	*
	* @var Contacts\Tracking
	*/
	protected $tracking;

	/**
	* Keep deleted addresses, or really delete them
	* Set in Admin -> Addressbook -> Site Configuration
	* ''=really delete, 'history'=keep, only admins delete, 'userpurge'=keep, users delete
 	*
	* @var string
 	*/
	protected $delete_history = '';

	/**
	 * Constructor
	 *
	 * @param string $contact_app ='addressbook' used for acl->get_grants()
	 * @param Db $db =null
	 */
	function __construct($contact_app='addressbook',Db $db=null)
	{
		parent::__construct($contact_app,$db);
		if ($this->log)
		{
			$this->logfile = $GLOBALS['egw_info']['server']['temp_dir'].'/log-addressbook_bo';
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($contact_app)\n", 3 ,$this->logfile);
		}

		$this->now_su = DateTime::to('now','ts');

		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['addressbook'];
		if(!isset($this->prefs['hide_accounts']))
		{
			$this->prefs['hide_accounts'] = '0';
		}
		// get the default addressbook from the users prefs
		$this->default_addressbook = !empty($GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default']) ?
			(int)$GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default'] : $this->user;
		$this->default_private = substr($GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default'] ?? '',-1) == 'p';
		if ($this->default_addressbook > 0 && $this->default_addressbook != $this->user &&
			($this->default_private ||
			$this->default_addressbook == (int)($GLOBALS['egw']->preferences->forced['addressbook']['add_default'] ?? 0) ||
			$this->default_addressbook == (int)($GLOBALS['egw']->preferences->default['addressbook']['add_default'] ?? 0)))
		{
			$this->default_addressbook = $this->user;	// admin set a default or forced pref for personal addressbook
		}
		$this->private_addressbook = self::private_addressbook($this->contact_repository == 'sql', $this->prefs);

		$this->contact_fields = array(
			'id'                   => lang('Contact ID'),
			'tid'                  => lang('Type'),
			'owner'                => lang('Addressbook'),
			'private'              => lang('private'),
			'cat_id'               => lang('Category'),
			'n_prefix'             => lang('prefix'),
			'n_given'              => lang('first name'),
			'n_middle'             => lang('middle name'),
			'n_family'             => lang('last name'),
			'n_suffix'             => lang('suffix'),
			'n_fn'                 => lang('full name'),
			'n_fileas'             => lang('own sorting'),
			'bday'                 => lang('birthday'),
			'org_name'             => lang('Organisation'),
			'org_unit'             => lang('Department'),
			'title'                => lang('Position'),
			'role'                 => lang('role'),
			'assistent'            => lang('Assistent'),
			'room'                 => lang('Room'),
			'adr_one_street'       => lang('business street'),
			'adr_one_street2'      => lang('business address line 2'),
			'adr_one_locality'     => lang('business city'),
			'adr_one_region'       => lang('business state'),
			'adr_one_postalcode'   => lang('business zip code'),
			'adr_one_countryname'  => lang('business country'),
			'adr_one_countrycode'  => lang('business country code'),
			'label'                => lang('label'),
			'adr_two_street'       => lang('street (private)'),
			'adr_two_street2'      => lang('address line 2 (private)'),
			'adr_two_locality'     => lang('city (private)'),
			'adr_two_region'       => lang('state (private)'),
			'adr_two_postalcode'   => lang('zip code (private)'),
			'adr_two_countryname'  => lang('country (private)'),
			'adr_two_countrycode'  => lang('country code (private)'),
			'tel_work'             => lang('work phone'),
			'tel_cell'             => lang('mobile phone'),
			'tel_fax'              => lang('business fax'),
			'tel_assistent'        => lang('assistent phone'),
			'tel_car'              => lang('car phone'),
			'tel_pager'            => lang('pager'),
			'tel_home'             => lang('home phone'),
			'tel_fax_home'         => lang('fax (private)'),
			'tel_cell_private'     => lang('mobile phone (private)'),
			'tel_other'            => lang('other phone'),
			'tel_prefer'           => lang('preferred phone'),
			'email'                => lang('business email'),
			'email_home'           => lang('email (private)'),
			'url'                  => lang('url (business)'),
			'url_home'             => lang('url (private)'),
			'freebusy_uri'         => lang('Freebusy URI'),
			'calendar_uri'         => lang('Calendar URI'),
			'note'                 => lang('note'),
			'tz'                   => lang('time zone'),
			'geo'                  => lang('geo'),
			'pubkey'               => lang('public key'),
			'created'              => lang('created'),
			'creator'              => lang('created by'),
			'modified'             => lang('last modified'),
			'modifier'             => lang('last modified by'),
			'jpegphoto'            => lang('photo'),
			'account_id'           => lang('Account ID'),
		);
		$this->business_contact_fields = array(
			'org_name'             => lang('Company'),
			'org_unit'             => lang('Department'),
			'title'                => lang('Title'),
			'role'                 => lang('Role'),
			'n_prefix'             => lang('prefix'),
			'n_given'              => lang('first name'),
			'n_middle'             => lang('middle name'),
			'n_family'             => lang('last name'),
			'n_suffix'             => lang('suffix'),
			'adr_one_street'       => lang('street').' ('.lang('business').')',
			'adr_one_street2'      => lang('address line 2').' ('.lang('business').')',
			'adr_one_locality'     => lang('city').' ('.lang('business').')',
			'adr_one_region'       => lang('state').' ('.lang('business').')',
			'adr_one_postalcode'   => lang('zip code').' ('.lang('business').')',
			'adr_one_countryname'  => lang('country').' ('.lang('business').')',
		);
		$this->home_contact_fields = array(
			'org_name'             => lang('Company'),
			'org_unit'             => lang('Department'),
			'title'                => lang('Title'),
			'role'                 => lang('Role'),
			'n_prefix'             => lang('prefix'),
			'n_given'              => lang('first name'),
			'n_middle'             => lang('middle name'),
			'n_family'             => lang('last name'),
			'n_suffix'             => lang('suffix'),
			'adr_two_street'       => lang('street').' ('.lang('business').')',
			'adr_two_street2'      => lang('address line 2').' ('.lang('business').')',
			'adr_two_locality'     => lang('city').' ('.lang('business').')',
			'adr_two_region'       => lang('state').' ('.lang('business').')',
			'adr_two_postalcode'   => lang('zip code').' ('.lang('business').')',
			'adr_two_countryname'  => lang('country').' ('.lang('business').')',
		);
		//_debug_array($this->contact_fields);
		$this->own_account_acl = $GLOBALS['egw_info']['server']['own_account_acl'] ?? null;
		if (!is_array($this->own_account_acl)) $this->own_account_acl = json_php_unserialize($this->own_account_acl, true);
		// we have only one acl (n_fn) for the whole name, as not all backends store every part in an own field
		if ($this->own_account_acl && in_array('n_fn',$this->own_account_acl))
		{
			$this->own_account_acl = array_merge($this->own_account_acl,array('n_prefix','n_given','n_middle','n_family','n_suffix'));
		}
		if (!empty($GLOBALS['egw_info']['server']['org_fileds_to_update']))
		{
			$this->org_fields =  $GLOBALS['egw_info']['server']['org_fileds_to_update'];
			if (!is_array($this->org_fields)) $this->org_fields = unserialize($this->org_fields);

			// Set country code if country name is selected
			$supported_fields = $this->get_fields('supported',null,0);
			if(in_array('adr_one_countrycode', $supported_fields) && in_array('adr_one_countryname',$this->org_fields))
			{
				$this->org_fields[] = 'adr_one_countrycode';
			}
			if(in_array('adr_two_countrycode', $supported_fields) && in_array('adr_two_countryname',$this->org_fields))
			{
				$this->org_fields[] = 'adr_two_countrycode';
			}
		}
		$this->categories = new Categories($this->user,'addressbook');

		$this->delete_history = $GLOBALS['egw_info']['server']['history'] ?? null;
	}

	/**
	 * Do we use a private addressbook (in comparison to a personal one)
	 *
	 * Used to set $this->private_addressbook for current user.
	 *
	 * @param string $contact_repository
	 * @param array $prefs addressbook preferences
	 * @return boolean
	 */
	public static function private_addressbook($contact_repository, array $prefs=null)
	{
		return $contact_repository == 'sql' && $prefs['private_addressbook'];
	}

	/**
	 * Get the availible addressbooks of the user
	 *
	 * @param int $required =Acl::READ required rights on the addressbook or multiple rights or'ed together,
	 * 	to return only addressbooks fullfilling all the given rights
	 * @param ?string $extra_label first label if given (already translated)
	 * @param ?int $user =null account_id or null for current user
	 * @param boolean $check_all =true false: only require any of the given right-bits is set
	 * @return array with owner => label pairs
	 */
	function get_addressbooks($required=Acl::READ,$extra_label=null,$user=null,$check_all=true)
	{
		if (is_null($user))
		{
			$user = $this->user;
			$preferences = $GLOBALS['egw_info']['user']['preferences'];
			$grants = $this->grants;
		}
		else
		{
			$prefs_obj = new Preferences($user);
			$preferences = $prefs_obj->read_repository();
			$grants = $this->get_grants($user, 'addressbook', $preferences);
		}

		$addressbooks = $to_sort = array();
		if ($extra_label) $addressbooks[''] = $extra_label;
		if ($user > 0)
		{
			$addressbooks[$user] = lang('Personal');
		}
		// add all group addressbooks the user has the necessary rights too
		foreach($grants as $uid => $rights)
		{
			if (self::is_set($rights, $required, $check_all) && $GLOBALS['egw']->accounts->get_type($uid) == 'g')
			{
				$to_sort[$uid] = lang('Group %1',$GLOBALS['egw']->accounts->id2name($uid));
			}
		}
		if ($to_sort)
		{
			asort($to_sort);
			$addressbooks += $to_sort;
		}
		if ($required != Acl::ADD &&	// do NOT allow to set accounts as default addressbook (AB can add accounts)
			$preferences['addressbook']['hide_accounts'] !== '1' && (
				($grants[0] & $required) == $required ||
				$preferences['common']['account_selection'] == 'groupmembers' &&
				$this->account_repository != 'ldap' && ($required & Acl::READ)))
		{
			$addressbooks[0] = lang('Accounts');
		}
		// add all other user addressbooks the user has the necessary rights too
		$to_sort = array();
		foreach($grants as $uid => $rights)
		{
			if ($uid != $user && self::is_set($rights, $required, $check_all) && $GLOBALS['egw']->accounts->get_type($uid) == 'u')
			{
				$to_sort[$uid] = Accounts::username($uid);
			}
		}
		if ($to_sort)
		{
			asort($to_sort);
			$addressbooks += $to_sort;
		}
		if ($user > 0 && self::private_addressbook($this->contact_repository, $preferences['addressbook']))
		{
			$addressbooks[$user.'p'] = lang('Private');
		}
		return $addressbooks;
	}

	/**
	 * Check rights for one or more required rights
	 * @param int $rights
	 * @param int $required
	 * @param boolean $check_all =true false: only require any of the given right-bits is set
	 * @return bool
	 */
	private static function is_set($rights, $required, $check_all=true)
	{
		$result = $rights & $required;
		return $check_all ? $result == $required : $result !== 0;
	}

	/**
	 * calculate the file_as string from the contact and the file_as type
	 *
	 * @param array $contact
	 * @param string $type =null file_as type, default null to read it from the contact, unknown/not set type default to the first one
	 * @param boolean $isUpdate =false If true, reads the old record for any not set fields
	 * @return string
	 */
	function fileas($contact,$type=null, $isUpdate=false)
	{
		if (is_null($type)) $type = $contact['fileas_type'] ?? null;
		if (!$type) $type = $this->prefs['fileas_default'] ? $this->prefs['fileas_default'] : $this->fileas_types[0];

		if (strpos($type,'n_fn') !== false) $contact['n_fn'] = $this->fullname($contact);

		if($isUpdate)
		{
			$fileas_fields = array('n_prefix','n_given','n_middle','n_family','n_suffix','n_fn','org_name','org_unit','adr_one_locality','bday');
			$old = null;
			foreach($fileas_fields as $field)
			{
				if(!isset($contact[$field]))
				{
					if(is_null($old)) $old = $this->read($contact['id']);
					$contact[$field] = $old[$field];
				}
			}
			unset($old);
		}

		// removing empty delimiters, caused by empty contact fields
		$fileas = str_replace(array(', , : ',', : ',': , ',', , ',': : ',' ()'),
			array(': ',': ',': ',', ',': ',''),
			strtr($type, array(
				'n_prefix' => $contact['n_prefix'],
				'n_given'  => $contact['n_given'],
				'n_middle' => $contact['n_middle'],
				'n_family' => $contact['n_family'],
				'n_suffix' => $contact['n_suffix'],
				'n_fn'     => $contact['n_fn'],
				'org_name' => $contact['org_name'],
				'org_unit' => $contact['org_unit'],
				'adr_one_locality' => $contact['adr_one_locality'],
				'bday'     => (int)$contact['bday'] ? DateTime::to($contact['bday'], true) : $contact['bday'],
			)));

		while (!empty($fileas) && ($fileas[0] == ':' ||  $fileas[0] == ','))
		{
			$fileas = substr($fileas,2);
		}
		while (substr($fileas,-2) == ': ' || substr($fileas,-2) == ', ')
		{
			$fileas = substr($fileas,0,-2);
		}
		return $fileas;
	}

	/**
	 * determine the file_as type from the file_as string and the contact
	 *
	 * @param array $contact
	 * @param string $file_as =null file_as type, default null to read it from the contact, unknown/not set type default to the first one
	 * @return string
	 */
	function fileas_type($contact,$file_as=null)
	{
		if (is_null($file_as)) $file_as = $contact['n_fileas'];

		if ($file_as)
		{
			foreach($this->fileas_types as $type)
			{
				if ($this->fileas($contact,$type) === $file_as)
				{
					return $type;
				}
			}
		}
		// if none of our types matches, but a non-empty value is set, keep it
		return $file_as ?: $this->prefs['fileas_default'] ?: $this->fileas_types[0];
	}

	/**
	 * get selectbox options for the customfields
	 *
	 * @param array $field =null
	 * @return array with options:
	 */
	public static function cf_options()
	{
		$cf_fields = Storage\Customfields::get('addressbook',TRUE);
		foreach ($cf_fields as $key => $value )
		{
			$options[$key]= $value['label'];
		}
		return $options;
	}

	/**
	 * get selectbox options for the fileas types with translated labels, or real content
	 *
	 * @param array $contact =null real content to use, default none
	 * @return array with options: fileas type => label pairs
	 */
	function fileas_options($contact=null)
	{
		$labels = array(
			'n_prefix' => lang('prefix'),
			'n_given'  => lang('first name'),
			'n_middle' => lang('middle name'),
			'n_family' => lang('last name'),
			'n_suffix' => lang('suffix'),
			'n_fn'     => lang('full name'),
			'org_name' => lang('company'),
			'org_unit' => lang('department'),
			'adr_one_locality' => lang('city'),
			'bday'     => lang('Birthday'),
		);
		foreach(array_keys($labels) as $name)
		{
			if ($contact[$name]) $labels[$name] = $contact[$name];
		}
		$found = false;
		foreach($this->fileas_types as $fileas_type)
		{
			$options[$fileas_type] = $value = $this->fileas($labels,$fileas_type);
			$found = $found || $value === $contact['n_fileas'];
		}
		// if we have a custom fileAs for an existing entry, add it as first option to keep it
		if (!empty($contact['n_fileas']) && !$found && !empty($contact['id']))
		{
			$options = array_merge([$contact['n_fileas'] => lang('Custom').': '.$contact['n_fileas']], $options);
		}
		return $options;
	}

	/**
	 * Set n_fileas (and n_fn) in contacts of all users  (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 * If $all all fileas fields will be set, if !$all only empty ones
	 *
	 * @param string $fileas_type '' or type of $this->fileas_types
	 * @param int $all =false update all contacts or only ones with empty values
	 * @param int &$errors=null on return number of errors
	 * @return int|boolean number of contacts updated, false for wrong fileas type
	 */
	function set_all_fileas($fileas_type,$all=false,&$errors=null,$ignore_acl=false)
	{
		if ($fileas_type != '' && !in_array($fileas_type, $this->fileas_types))
		{
			return false;
		}
		if ($ignore_acl)
		{
			unset($this->somain->grants);	// to NOT limit search to contacts readable by current user
		}
		// to be able to work on huge contact repositories we read the contacts in chunks of 100
		for($n = $updated = $errors = 0; ($contacts = parent::search($all ? array() : array(
			'n_fileas IS NULL',
			"n_fileas=''",
			'n_fn IS NULL',
			"n_fn=''",
		),false,'','','',false,'OR',array($n*100,100))); ++$n)
		{
			foreach($contacts as $contact)
			{
				$old_fn     = $contact['n_fn'];
				$old_fileas = $contact['n_fileas'];
				$contact['n_fn'] = $this->fullname($contact);
				// only update fileas if type is given AND (all should be updated or n_fileas is empty)
				if ($fileas_type && ($all || empty($contact['n_fileas'])))
				{
					$contact['n_fileas'] = $this->fileas($contact,$fileas_type);
				}
				if ($old_fileas != $contact['n_fileas'] || $old_fn != $contact['n_fn'])
				{
					// only specify/write updated fields plus "keys"
					$contact = array_intersect_key($contact,array(
						'id' => true,
						'owner' => true,
						'private' => true,
						'account_id' => true,
						'uid' => true,
					)+($old_fileas != $contact['n_fileas'] ? array('n_fileas' => true) : array())+($old_fn != $contact['n_fn'] ? array('n_fn' => true) : array()));
					if ($this->save($contact,$ignore_acl))
					{
						$updated++;
					}
					else
					{
						$errors++;
					}
				}
			}
		}
		return $updated;
	}

	/**
	 * Cleanup all contacts db fields of all users  (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 * Cleanup means to truncate all unnecessary chars like whitespaces or tabs,
	 * remove unneeded carriage returns or set empty fields to NULL
	 *
	 * @param int &$errors=null on return number of errors
	 * @return int|boolean number of contacts updated
	 */
	function set_all_cleanup(&$errors=null,$ignore_acl=false)
	{
		if ($ignore_acl)
		{
			unset($this->somain->grants);	// to NOT limit search to contacts readable by current user
		}

		// fields that must not be touched
		$fields_exclude = array(
			'id'			=> true,
			'tid'			=> true,
			'owner'			=> true,
			'private'		=> true,
			'created'		=> true,
			'creator'		=> true,
			'modified'		=> true,
			'modifier'		=> true,
			'account_id'	=> true,
			'etag'			=> true,
			'uid'			=> true,
			'freebusy_uri'	=> true,
			'calendar_uri'	=> true,
			'photo'			=> true,
		);

		// to be able to work on huge contact repositories we read the contacts in chunks of 100
		for($n = $updated = $errors = 0; ($contacts = parent::search(array(),false,'','','',false,'OR',array($n*100,100))); ++$n)
		{
			foreach($contacts as $contact)
			{
				$fields_to_update = array();
				foreach($contact as $field_name => $field_value)
				{
					if($fields_exclude[$field_name] === true) continue; // dont touch specified field

					if (is_string($field_value) && $field_name != 'pubkey' && $field_name != 'jpegphoto')
					{
						// check if field has to be trimmed
						if (strlen($field_value) != strlen(trim($field_value)))
						{
							$fields_to_update[$field_name] = $field_value = trim($field_value);
						}
						// check if field contains a carriage return - exclude notes
						if ($field_name != 'note' && strpos($field_value,"\x0D\x0A") !== false)
						{
							$fields_to_update[$field_name] = $field_value = str_replace("\x0D\x0A"," ",$field_value);
						}
					}
					// check if a field contains an empty string
					if (is_string($field_value) && strlen($field_value) == 0)
					{
						$fields_to_update[$field_name] = $field_value = null;
					}
					// check for valid birthday date
					if ($field_name == 'bday' && $field_value != null &&
						!preg_match('/^(18|19|20|21|22)\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/',$field_value))
					{
						$fields_to_update[$field_name] = $field_value = null;
					}
				}

				if(count($fields_to_update) > 0)
				{
					$contact_to_save = array(
						'id' => $contact['id'],
						'owner' => $contact['owner'],
						'private' => $contact['private'],
						'account_id' => $contact['account_id'],
						'uid' => $contact['uid']) + $fields_to_update;

					if ($this->save($contact_to_save,$ignore_acl))
					{
						$updated++;
					}
					else
					{
						$errors++;
					}
				}
			}
		}
		return $updated;
	}

	/**
	 * Get full name from the name-parts using "n_fn_parts" preference
	 *
	 * @param array $contact
	 * @return string full name
	 */
	static function fullname($contact)
	{
		if (empty($contact['n_family']) && empty($contact['n_given']))
		{
			$cparts = ['org_name'];
		}
		else
		{
			$cparts = explode(' ', $GLOBALS['egw_info']['user']['preferences']['addressbook']['n_fn_parts'] ??
				'n_prefix n_given n_middle n_family n_suffix');
		}
		$parts = [];
		foreach($cparts as $n)
		{
			foreach(explode('-or-', $n) as $p)
			{
				if (!empty($contact[$p]))
				{
					$parts[] = $contact[$p];
					break;
				}
			}
		}
		return implode(' ', $parts);
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * it gets called everytime when data is read from the db
	 * This function needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 * @param $date_format ='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 *
	 * @return array updated data
	 */
	function db2data($data, $date_format='ts')
	{
		static $fb_url = false;

		// convert timestamps from server-time in the db to user-time
		foreach ($this->timestamps as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = DateTime::server2user($data[$name], $date_format);
			}
		}
		$photo = $this->photo_src($data['id'] ?? null,
			// do NOT replace with self::hasPhoto($data) as it also checks file is non-empty in VFS and breaks
			!empty($data['jpegphoto']) || (($data['files']??0) & self::FILES_BIT_PHOTO), '', $data['etag'] ?? null);
		if($photo && (((int)$data['files'] ?? 0) & self::FILES_BIT_PHOTO))
		{
			$data['photo'] = $photo;
		}

		// set freebusy_uri for accounts
		if (empty($data['freebusy_uri']) && empty($data['owner']) && !empty($data['account_id']) && empty($GLOBALS['egw_setup']))
		{
			if ($fb_url || @is_dir(EGW_SERVER_ROOT.'/calendar/inc'))
			{
				$fb_url = true;
				$user = isset($data['account_lid']) ? $data['account_lid'] : $GLOBALS['egw']->accounts->id2name($data['account_id']);
				$data['freebusy_uri'] = calendar_bo::freebusy_url($user);
			}
		}
		if (!empty($GLOBALS['egw_info']['user']['preferences']['addressbook']['n_fn_parts']) || empty($data['n_fn']))
		{
			$data['n_fn'] = self::fullname($data);
		}
		return $data;
	}

	/**
	 * src for photo: returns array with linkparams if jpeg exists or the $default image-name if not
	 * @param int $id contact_id
	 * @param boolean $jpeg =false jpeg exists or not
	 * @param string $default ='' image-name to use if !$jpeg, eg. 'template'
	 * @param string $etag =null etag to set in url to allow caching with Expires header
	 * @return string
	 */
	function photo_src($id,$jpeg,$default='',$etag=null)
	{
		//error_log(__METHOD__."($id, ..., etag=$etag) ".  function_backtrace());
		return $jpeg || !$default ? Egw::link('/api/avatar.php', array(
			'contact_id' => $id,
		)+(isset($etag) ? array(
			'etag'       => $etag
		) : array())) : $default;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches
	 * this needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 * @param $date_format ='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 *
	 * @return array upated data
	 */
	function data2db($data, $date_format='ts')
	{
		// convert timestamps from user-time to server-time in the db
		foreach ($this->timestamps as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = DateTime::user2server($data[$name], $date_format);
			}
		}
		return $data;
	}

	/**
	* deletes contact in db
	*
	* @param mixed &$contact contact array with key id or (array of) id(s)
	* @param boolean $deny_account_delete =true if true never allow to delete accounts
	* @param int $check_etag =null
	* @param bool $no_permission_check =false true: do not call check_perms and error out on missing rights
	* @return boolean|int true on success or false on failure, 0 if etag does not match
	*/
	function delete($contact,$deny_account_delete=true,$check_etag=null,$no_permission_check=false)
	{
		if (is_array($contact) && isset($contact['id']))
		{
			$contact = array($contact);
		}
		elseif (!is_array($contact))
		{
			$contact = array($contact);
		}
		foreach($contact as $c)
		{
			$id = is_array($c) ? $c['id'] : $c;

			$ok = false;
			if ($no_permission_check || $this->check_perms(Acl::DELETE,$c,$deny_account_delete))
			{
				if (!($old = $this->read($id))) return false;
				// already marked as deleted item and accounts are always really deleted
				// we can't mark accounts as deleted, as no such thing exists for accounts!
				if ($old['owner'] && $old['tid'] !== self::DELETED_TYPE)
				{
					$delete = $old;
					$delete['tid'] = self::DELETED_TYPE;
					if ($check_etag) $delete['etag'] = $check_etag;
					if (($ok = $this->save($delete))) $ok = true;	// we have to return true or false
					Link::unlink(0,'addressbook',$id,'','','',true);
				}
				elseif (($ok = parent::delete($id,$check_etag)))
				{
					Link::unlink(0,'addressbook',$id);
				}

				// Don't notify of final purge
				if ($ok && $old['tid'] != self::DELETED_TYPE)
				{
					if (!isset($this->tracking)) $this->tracking = new Contacts\Tracking($this);
					$this->tracking->track(array('id' => $id), array('id' => $id), null, true);
				}
			}
			else
			{
				break;
			}
		}
		//error_log(__METHOD__.'('.array2string($contact).', deny_account_delete='.array2string($deny_account_delete).', check_etag='.array2string($check_etag).' returning '.array2string($ok));
		return $ok;
	}

	/**
	* saves contact to db
	*
	* @param array &$contact contact array from etemplate::exec
	* @param boolean $ignore_acl =false should the acl be checked or not
	* @param boolean $touch_modified =true should modified/r be updated
	* @return int/string/boolean id on success, false on failure, the error-message is in $this->error
	*/
	function save(&$contact, $ignore_acl=false, $touch_modified=true)
	{
		$update_type = "update";

		// Make sure photo remains unchanged unless its purposely set to be false
		// which means photo has changed.
		if (!array_key_exists('photo_unchanged',$contact)) $contact['photo_unchanged'] = true;

		// remember if we add or update an entry
		if (($isUpdate = $contact['id'] ?? null))
		{
			if (!isset($contact['owner']) || !isset($contact['private']))	// owner/private not set on update, eg. SyncML
			{
				if (($old = $this->read($contact['id'], $ignore_acl) ?: null))	// --> try reading the old entry and set it from there
				{
					if(!isset($contact['owner']))
					{
						$contact['owner'] = $old['owner'];
					}
					if(!isset($contact['private']))
					{
						$contact['private'] = $old['private'];
					}
				}
				else	// entry not found --> create a new one
				{
					$isUpdate = $contact['id'] = null;
					$update_type = "add";
				}
			}
		}
		else
		{
			// if no owner/addressbook set use the setting of the add_default prefs (if set, otherwise the users personal addressbook)
			if (!isset($contact['owner'])) $contact['owner'] = $this->default_addressbook;
			if (!isset($contact['private'])) $contact['private'] = (int)$this->default_private;
			// do NOT allow to create new accounts via addressbook, they are broken without an account_id
			if (!$contact['owner'] && empty($contact['account_id']))
			{
				$contact['owner'] = $this->default_addressbook ? $this->default_addressbook : $this->user;
			}
			// allow admins to import contacts with creator / created date set
			if (empty($contact['creator']) || !$ignore_acl && !$this->is_admin($contact)) $contact['creator'] = $this->user;
			if (empty($contact['created']) || !$ignore_acl && !$this->is_admin($contact)) $contact['created'] = $this->now_su;

			if (empty($contact['tid'])) $contact['tid'] = 'n';
			$update_type = "add";
		}
		// ensure accounts and group addressbooks are never private!
		if ($contact['owner'] <= 0)
		{
			$contact['private'] = 0;
		}
		if(!$ignore_acl && !$this->check_perms($isUpdate ? Acl::EDIT : Acl::ADD,$contact))
		{
			$this->error = 'access denied';
			return false;
		}
		// resize image to 60px width
		if (!empty($contact['jpegphoto']))
		{
			$contact['jpegphoto'] = $this->resize_photo($contact['jpegphoto']);
		}
		// convert categories
		if (isset($contact['cat_id']) && is_array($contact['cat_id']))
		{
			$contact['cat_id'] = implode(',',$contact['cat_id']) ?: null;
		}

		// Update country codes
		foreach(array('adr_one_', 'adr_two_') as $c_prefix) {
			if($contact[$c_prefix.'countryname'] && !$contact[$c_prefix.'countrycode'] &&
				$code = Country::country_code($contact[$c_prefix.'countryname']))
			{
				if(strlen($code) == 2)
				{
					$contact[$c_prefix.'countrycode'] = $code;
				}
				else
				{
					$contact[$c_prefix.'countrycode'] = null;
				}
			}
			if(isset($contact[$c_prefix.'countrycode']))
			{
				$contact[$c_prefix.'countryname'] = null;
			}
		}

		// last modified
		if ($touch_modified)
		{
			$contact['modifier'] = $this->user;
			$contact['modified'] = $this->now_su;
		}
		// set full name and fileas from the content
		if (!isset($contact['n_fn']) || !empty($GLOBALS['egw_info']['user']['preferences']['addressbook']['n_fn_parts']))
		{
			$contact['n_fn'] = $this->fullname($contact);
		}
		if (isset($contact['org_name'])) $contact['n_fileas'] = $this->fileas($contact, null, false);

		// Get old record for tracking changes
		if (!isset($old) && $isUpdate)
		{
			$old = $this->read($contact['id']) ?: null;
		}
		$to_write = $contact;
		// (non-admin) user editing his own account, make sure he does not change fields he is not allowed to (eg. via SyncML or xmlrpc)
		if (!$ignore_acl && !$contact['owner'] && !($this->is_admin($contact) || $this->allow_account_edit()))
		{
			foreach(array_keys($contact) as $field)
			{
				if (!in_array($field,$this->own_account_acl) && !in_array($field,array('id','owner','account_id','modified','modifier', 'photo_unchanged')))
				{
					// user is not allowed to change that
					if ($old)
					{
						$to_write[$field] = $contact[$field] = $old[$field];
					}
					else
					{
						unset($to_write[$field]);
					}
				}
			}
		}

		// IF THE OLD ENTRY IS A ACCOUNT, dont allow to change the owner/location
		// maybe we need that for id and account_id as well.
		if (isset($old) && is_array($old) && (!isset($old['owner']) || empty($old['owner'])))
		{
			if (isset($to_write['owner']) && !empty($to_write['owner']))
			{
				error_log(__METHOD__.__LINE__." Trying to change account to owner:". $to_write['owner'].' Account affected:'.array2string($old).' Data send:'.array2string($to_write));
				unset($to_write['owner']);
			}
		}

		if(!($this->error = parent::save($to_write)))
		{
			$contact['id'] = $to_write['id'];
			$contact['uid'] = $to_write['uid'];
			$contact['etag'] = $to_write['etag'];
			$contact['files'] = $to_write['files'];

			// Clear any files saved with new entries
			// They've been dealt with already and they cause errors with linking
			foreach(array_keys($this->customfields) as $field)
			{
				if(is_array($to_write[Storage::CF_PREFIX.$field]))
				{
					unset($to_write[Storage::CF_PREFIX.$field]);
				}
			}

			// if contact is an account and account-relevant data got updated, handle it like account got updated
			if (!empty($contact['account_id']) && $isUpdate &&
				($old['email'] != $contact['email'] || $old['n_family'] != $contact['n_family'] || $old['n_given'] != $contact['n_given']))
			{
				// invalidate the cache of the accounts class
				$GLOBALS['egw']->accounts->cache_invalidate($contact['account_id']);
				// call edit-accout hook, to let other apps know about changed account (names or email)
				$GLOBALS['hook_values'] = (array)$GLOBALS['egw']->accounts->read($contact['account_id']);
				Hooks::process($GLOBALS['hook_values']+array(
					'location' => 'editaccount',
				),False,True);	// called for every app now, not only enabled ones)
			}
			// notify interested apps about changes in the account-contact data
			if (!$to_write['owner'] && $to_write['account_id'] && $isUpdate)
			{
				$to_write['location'] = 'editaccountcontact';
				Hooks::process($to_write,False,True);	// called for every app now, not only enabled ones));
			}

			// Check for restore of deleted contact, restore held links
			if(isset($old) && $old['tid'] == self::DELETED_TYPE && $contact['tid'] != self::DELETED_TYPE)
			{
				Link::restore('addressbook', $contact['id']);
			}

			// Record change history for sql - doesn't work for LDAP accounts
			$deleted = (isset($old) && $old['tid'] == self::DELETED_TYPE || $contact['tid'] == self::DELETED_TYPE);
			if(empty($contact['account_id']) || $contact['account_id'] && $this->account_repository == 'sql')
			{
				if (!isset($this->tracking)) $this->tracking = new Contacts\Tracking($this);
				$this->tracking->track($to_write, $old, null, $deleted);
			}

			// Notify linked apps about changes in the contact data
			Link::notify_update('addressbook',  $contact['id'], $contact, $deleted ? 'delete' : $update_type);

			// Expire birthday cache for this year and next if birthday changed
			if($isUpdate && $old['bday'] !== $to_write['bday'] || !$isUpdate && $to_write['bday'])
			{
				$year = (int) date('Y',time());
				$this->clear_birthday_cache($year, $to_write['owner']);
				$year++;
				$this->clear_birthday_cache($year, $to_write['owner']);
			}
		}

		return $this->error ? false : $contact['id'];
	}

	/**
	 * Since birthdays are cached for the instance for BIRTHDAY_CACHE_TIME, we
	 * need to clear them if a birthday changes.
	 *
	 * @param type $year
	 */
	protected function clear_birthday_cache($year, $owner)
	{
		// Cache is kept per-language, so clear them all
		foreach(array_keys(Translation::get_installed_langs()) as $lang)
		{
			Cache::unsetInstance(__CLASS__,"birthday-$year-{$owner}-$lang");
		}
	}

	/**
	 * Resize photo down to 240pixel width and returns it
	 *
	 * Also makes sures photo is a JPEG.
	 *
	 * @param string|FILE $photo string with image or open filedescribtor
	 * @param int $dst_w =240 max width to resize to
	 * @return string with resized jpeg photo, null on error
	 */
	public static function resize_photo($photo, $dst_w=240)
	{
		if (is_resource($photo))
		{
			$photo = stream_get_contents($photo);
		}
		if (empty($photo) || !($image = imagecreatefromstring($photo)))
		{
			error_log(__METHOD__."() invalid image!");
			return null;
		}
		$src_w = imagesx($image);
		$src_h = imagesy($image);
		//error_log(__METHOD__."() got image $src_w * $src_h, is_jpeg=".array2string(substr($photo,0,2) === "\377\330"));

		// if $photo is to width or not a jpeg image --> resize it
		if ($src_w > $dst_w || cut_bytes($photo,0,2) !== "\377\330")
		{
			//error_log(__METHOD__."(,dst_w=$dst_w) src_w=$src_w, cut_bytes(photo,0,2)=".array2string(cut_bytes($photo,0,2)).' --> resizing');
			// scale the image to a width of 60 and a height according to the proportion of the source image
			$resized = imagecreatetruecolor($dst_w,$dst_h = round($src_h * $dst_w / $src_w));
			imagecopyresized($resized,$image,0,0,0,0,$dst_w,$dst_h,$src_w,$src_h);

			ob_start();
			imagejpeg($resized,null,90);
			$photo = ob_get_contents();
			ob_end_clean();

			imagedestroy($resized);
			//error_log(__METHOD__."() resized image $src_w*$src_h to $dst_w*$dst_h");
		}
		//else error_log(__METHOD__."(,dst_w=$dst_w) src_w=$src_w, cut_bytes(photo,0,2)=".array2string(cut_bytes($photo,0,2)).' --> NOT resizing');

		imagedestroy($image);

		return $photo;
	}

	/**
	* reads contacts matched by key and puts all cols in the data array
	*
	* @param int|string $contact_id
	* @param boolean $ignore_acl =false true: no acl check
	* @return array|boolean array with contact data, null if not found or false on no view perms
	*/
	function read($contact_id, $ignore_acl=false)
	{
		// get so_sql_cf to read private customfields too, if we ignore acl
		if ($ignore_acl && is_a($this->somain, __CLASS__.'\\Sql'))
		{
			$cf_backup = (array)$this->somain->customfields;
			$this->somain->customfields = Storage\Customfields::get('addressbook', true);
		}
		if (!($data = parent::read($contact_id)))
		{
			$data = null;	// not found
		}
		elseif (!$ignore_acl && !$this->check_perms(Acl::READ,$data))
		{
			$data = false;	// no view perms
		}
		else
		{
			// determine the file-as type
			$data['fileas_type'] = $this->fileas_type($data);

			// Update country name from code
			if($data['adr_one_countrycode'] != null) {
				$data['adr_one_countryname'] = Country::get_full_name($data['adr_one_countrycode'], true);
			}
			if($data['adr_two_countrycode'] != null) {
				$data['adr_two_countryname'] = Country::get_full_name($data['adr_two_countrycode'], true);
			}
		}
		if (isset($cf_backup))
		{
			$this->somain->customfields = $cf_backup;
		}
		//error_log(__METHOD__.'('.array2string($contact_id).') returning '.array2string($data));
		return $data;
	}

	/**
	 * Checks if the current user has the necessary ACL rights
	 *
	 * If the access of a contact is set to private, one need a private grant for a personal addressbook
	 * or the group membership for a group-addressbook
	 *
	 * @param int $needed necessary ACL right: Acl::{READ|EDIT|DELETE}
	 * @param mixed $contact contact as array or the contact-id
	 * @param boolean $deny_account_delete =false if true never allow to delete accounts
	 * @param ?int $user =null for which user to check, default current user
	 * @param int $check_shared =3 limits the nesting level of sharing checks, use 0 to NOT check sharing
	 * @return ?boolean|"shared" true permission granted, false for permission denied, null for contact does not exist
	 *  "shared" if permission is from sharing
	 */
	function check_perms($needed,$contact,$deny_account_delete=false,$user=null,$check_shared=3)
	{
		if (!$user) $user = $this->user;
		if ($user == $this->user)
		{
			$grants = $this->grants;
			$memberships = $this->memberships;
		}
		else
		{
			$grants = $this->get_grants($user);
			$memberships =  $GLOBALS['egw']->accounts->memberships($user,true);
		}

		if ((!is_array($contact) || !isset($contact['owner'])) &&

			!($contact = parent::read(is_array($contact) ? $contact['id'] : $contact)))
		{
			return null;
		}
		$owner = $contact['owner'];

		// allow the user to read and edit his own account
		if (!$owner && in_array($needed, [Acl::READ, Acl::EDIT]) && $contact['account_id'] == $user && $this->own_account_acl)
		{
			$access = true;
		}
		// don't allow to delete own account (as admin handles it too)
		elseif (!$owner && $needed == Acl::DELETE && ($deny_account_delete || $contact['account_id'] == $user))
		{
			$access = false;
		}
		// for reading accounts (owner == 0) and account_selection == groupmembers, check if current user and contact are groupmembers
		elseif ($owner == 0 && $needed == Acl::READ &&
			$GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'groupmembers' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$access = !!array_intersect($memberships,$GLOBALS['egw']->accounts->memberships($contact['account_id'],true));
		}
		elseif (!empty($contact['id']) && $GLOBALS['egw']->acl->check('A'.$contact['id'], $needed, 'addressbook'))
		{
			$access = true;
		}
		else
		{
			$access = ($grants[$owner] & $needed) &&
				(!$contact['private'] || ($grants[$owner] & Acl::PRIVAT) || in_array($owner,$memberships));
		}
		// check if we might have access via sharing (not for delete)
		if ($access === false && !empty($contact['shared']) && $needed != Acl::DELETE && $check_shared > 0)
		{
			foreach($contact['shared'] as $shared)
			{
				if (isset($grants[$shared['shared_with']]) && (!($needed & Acl::EDIT) ||
					// if shared writable, we check if the one who shared the contact still has edit rights
					$shared['shared_writable'] && $this->check_perms($needed, $contact, $deny_account_delete, $shared['shared_by'], $check_shared-1)))
				{
					$access = "shared";
					//error_log(__METHOD__."($needed,$contact[id],$deny_account_delete,$user,$check_shared) shared=".json_encode($shared)." returning ".array2string($access));
					break;
				}
			}
		}
		//error_log(__METHOD__."($needed,$contact[id],$deny_account_delete,$user,$check_shared) returning ".array2string($access));
		return $access;
	}

	/**
	 * Check if user has right to share with / into given AB
	 *
	 * @param array[]& $shared_with array of arrays with values for keys "shared_with", "shared_by", ...
	 * @param ?string& $error on return error-message
	 * @return array entries removed from $shared_with because current user is not allowed to share into (key is preserved)
	 */
	function check_shared_with(array &$shared_with=null, &$error=null)
	{
		$removed = [];
		foreach((array)$shared_with as $key => $shared)
		{
			if (!empty($shared['shared_by']) && $shared['shared_by'] != $this->user)
			{
				$grants = $this->get_grants($shared['shared_by']);
			}
			else
			{
				$grants = $this->grants;
			}
			if (!($grants[$shared['shared_with']] & self::CHECK_ACL_SHARED))
			{
				$removed[$key] = $shared;
				unset($shared_with[$key]);
			}
		}
		// allow apps to modifiy
		$results = [];
		foreach(Hooks::process([
			'location' => 'check_shared_with',
			'shared_with' => &$shared_with,
			'removed' => &$removed,
		], true) as $result)
		{
			if ($result)
			{
				$results = array_merge($results, $result);
			}
		}
		if ($results) $error = implode("\n", $results);

		return $removed;
	}

	/**
	 * Check access to the file store
	 *
	 * @param int|array $id id of entry or entry array
	 * @param int $check Acl::READ for read and Acl::EDIT for write or delete access
	 * @param string $rel_path =null currently not used in InfoLog
	 * @param int $user =null for which user to check, default current user
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path=null,$user=null)
	{
		unset($rel_path);	// not used, but required by function signature

		return $this->check_perms($check,$id,false,$user);
	}

	/**
	 * Read (virtual) org-entry (values "common" for most contacts in the given org)
	 *
	 * @param string $org_id org_name:oooooo|||org_unit:uuuuuuuuu|||adr_one_locality:lllllll (org_unit and adr_one_locality are optional)
	 * @return array/boolean array with common org fields or false if org not found
	 */
	function read_org($org_id)
	{
		if (!$org_id) return false;
		if (strpos($org_id,'*AND*')!== false) $org_id = str_replace('*AND*','&',$org_id);
		$org = array();
		foreach(explode('|||',$org_id) as $part)
		{
			list($name,$value) = explode(':',$part,2);
			$org[$name] = $value;
		}
		$csvs = array('cat_id');	// fields with comma-separated-values

		// split regular fields and custom fields
		$custom_fields = $regular_fields = array();
		foreach($this->org_fields as $name)
		{
			if ($name[0] != '#')
			{
				$regular_fields[] = $name;
			}
			else
			{
				$custom_fields[] = $name = substr($name,1);
				$regular_fields['id'] = 'id';
				if (substr($this->customfields[$name]['type'],0,6)=='select' && $this->customfields[$name]['rows'] ||	// multiselection
					$this->customfields[$name]['type'] == 'radio')
				{
					$csvs[] = '#'.$name;
				}
			}
		}
		// read the regular fields
		$contacts = parent::search('',$regular_fields,'','','',false,'AND',false,$org);
		if (!$contacts) return false;

		// if we have custom fields, read and merge them in
		if ($custom_fields)
		{
			foreach($contacts as $contact)
			{
				$ids[] = $contact['id'];
			}
			if (($cfs = $this->read_customfields($ids,$custom_fields)))
			{
				foreach ($contacts as &$contact)
				{
					$id = $contact['id'];
					if (isset($cfs[$id]))
					{
						foreach($cfs[$id] as $name => $value)
						{
							$contact['#'.$name] = $value;
						}
					}
				}
				unset($contact);
			}
		}

		// create a statistic about the commonness of each fields values
		$fields = array();
		foreach($contacts as $contact)
		{
			foreach($contact as $name => $value)
			{
				if (!in_array($name,$csvs))
				{
					$fields[$name][$value]++;
				}
				else
				{
					// for comma separated fields, we have to use each single value
					foreach(explode(',',$value) as $val)
					{
						$fields[$name][$val]++;
					}
				}
			}
		}
		foreach($fields as $name => $values)
		{
			if (!in_array($name,$this->org_fields)) continue;

			arsort($values,SORT_NUMERIC);
			$value = key($values);
			$num = current($values);
			if ($value && $num / (double) count($contacts) >= $this->org_common_factor)
			{
				if (!in_array($name,$csvs))
				{
					$org[$name] = $value;
				}
				else
				{
					$org[$name] = array();
					foreach ($values as $value => $num)
					{
						if ($value && $num / (double) count($contacts) >= $this->org_common_factor)
						{
							$org[$name][] = $value;
						}
					}
					$org[$name] = implode(',',$org[$name]);
				}
			}
		}
		return $org;
	}

	/**
	 * Return all org-members with same content in one or more of the given fields (only org_fields are counting)
	 *
	 * @param string $org_name
	 * @param array $fields field-name => value pairs
	 * @return array with contacts
	 */
	function org_similar($org_name,$fields)
	{
		$criteria = array();
		foreach($this->org_fields as $name)
		{
			if (isset($fields[$name]))
			{
				if (empty($fields[$name]))
				{
					$criteria[] = "($name IS NULL OR $name='')";
				}
				else
				{
					$criteria[$name] = $fields[$name];
				}
			}
		}
		return parent::search($criteria,false,'n_family,n_given','','',false,'OR',false,array('org_name'=>$org_name));
	}

	/**
	 * Return the changed fields from two versions of a contact (not modified or modifier)
	 *
	 * @param array $from original/old version of the contact
	 * @param array $to changed/new version of the contact
	 * @param boolean $only_org_fields =true check and return only org_fields, default true
	 * @return array with field-name => value from $from
	 */
	function changed_fields($from,$to,$only_org_fields=true)
	{
		// we only care about countryname, if contrycode is empty
		foreach(array(
			'adr_one_countryname' => 'adr_one_countrycode',
			'adr_two_countryname' => 'adr_one_countrycode',
		) as $name => $code)
		{
			if (!empty($from[$code])) $from[$name] = '';
			if (!empty($to[$code])) $to[$name] = '';
		}
		$changed = array();
		foreach($only_org_fields ? $this->org_fields : array_keys($this->contact_fields) as $name)
		{
			if (in_array($name,array('modified','modifier')))	// never count these
			{
				continue;
			}
			if ((string) $from[$name] != (string) $to[$name])
			{
				$changed[$name] = $from[$name];
			}
		}
		return $changed;
	}

	/**
	 * Change given fields in all members of the org with identical content in the field
	 *
	 * @param string $org_name
	 * @param array $from original/old version of the contact
	 * @param array $to changed/new version of the contact
	 * @param array $members =null org-members to change, default null --> function queries them itself
	 * @return array/boolean (changed-members,changed-fields,failed-members) or false if no org_fields changed or no (other) members matching that fields
	 */
	function change_org($org_name,$from,$to,$members=null)
	{
		if (!($changed = $this->changed_fields($from,$to,true))) return false;

		if (is_null($members) || !is_array($members))
		{
			$members = $this->org_similar($org_name,$changed);
		}
		if (!$members) return false;

		$ids = array();
		foreach($members as $member)
		{
			$ids[] = $member['id'];
		}
		$customfields = $this->read_customfields($ids);

		$changed_members = $changed_fields = $failed_members = 0;
		foreach($members as $member)
		{
			if (isset($customfields[$member['id']]))
			{
				foreach(array_keys($this->customfields) as $name)
				{
					$member['#'.$name] = $customfields[$member['id']][$name];
				}
			}
			$fields = 0;
			foreach($changed as $name => $value)
			{
				if ((string)$value == (string)$member[$name])
				{
					$member[$name] = $to[$name];
					++$fields;
				}
			}
			if ($fields)
			{
				if (!$this->check_perms(Acl::EDIT,$member) || !$this->save($member))
				{
					++$failed_members;
				}
				else
				{
					++$changed_members;
					$changed_fields += $fields;
				}
			}
		}
		return array($changed_members,$changed_fields,$failed_members);
	}

	/**
	 * get title for a contact identified by $contact
	 *
	 * Is called as hook to participate in the linking. The format is determined by the link_title preference.
	 *
	 * @param int|string|array $contact int/string id or array with contact
	 * @return string/boolean string with the title, null if contact does not exitst, false if no perms to view it
	 */
	function link_title($contact)
	{
		if (!is_array($contact) && $contact)
		{
			$contact = $this->read($contact);
		}
		if (!is_array($contact))
		{
			return $contact;
		}
		$type = $this->prefs['link_title'];
		if (!$type || $type === 'n_fileas')
		{
			if ($contact['n_fileas']) return $contact['n_fileas'];
			$type = null;
		}
		$title =  $this->fileas($contact,$type);

		if (!empty($this->prefs['link_title_cf']))
		{
			$field_list = is_string($this->prefs['link_title_cf']) ? explode(',', $this->prefs['link_title_cf']) : $this->prefs['link_title_cf'];
			foreach ($field_list as $field)
			{
				if($contact['#'.$field])
				{
				   $title .= ', ' . $contact['#'.$field];
				}
			}
		}
		return $title ;
	}

	/**
	 * get title for multiple contacts identified by $ids
	 *
	 * Is called as hook to participate in the linking. The format is determined by the link_title preference.
	 *
	 * @param array $ids array with contact-id's
	 * @return array with titles, see link_title
	 */
	function link_titles(array $ids)
	{
		$titles = array();
		if (($contacts =& $this->search(array('contact_id' => $ids),false,'',$extra_cols='','',False,'AND',False,array('tid'=>null))))
		{
			$ids = array();
			foreach($contacts as $contact)
			{
				$ids[] = $contact['id'];
			}
			$cfs = $this->read_customfields($ids);
			foreach($contacts as $contact)
			{
			   	$titles[$contact['id']] = $this->link_title($contact+(array)$cfs[$contact['id']]);
			}
		}
		// we assume all not returned contacts are not readable for the user (as we report all deleted contacts to egw_link)
		foreach($ids as $id)
		{
			if (!isset($titles[$id]))
			{
				$titles[$id] = false;
			}
		}
		return $titles;
	}

	/**
	 * query addressbook for contacts matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string|array $pattern pattern to search, or an array with a 'search' key
	 * @param array $options Array of options for the search
	 * @return array with id - title pairs of the matching entries
	 */
	function link_query($pattern, Array &$options = array())
	{
		$result = $criteria = array();
		$limit = false;
		if ($pattern)
		{
			$criteria = is_array($pattern) ? $pattern['search'] : $pattern;
		}
		if($options['start'] || $options['num_rows'])
		{
			$limit = array($options['start'], $options['num_rows']);
		}
		$filter = (array)$options['filter'];
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '1') $filter['account_id'] = null;
		if (($contacts =& parent::search($criteria,false,'org_name,n_family,n_given,cat_id,contact_email','','%',false,'OR', $limit, $filter)))
		{
			$ids = array();
			foreach($contacts as $contact)
			{
				$ids[] = $contact['id'];
			}
			$cfs = $this->read_customfields($ids);
			foreach($contacts as $contact)
			{
				$result[$contact['id']] = $this->link_title($contact+(array)$cfs[$contact['id']]);
				// make sure to return a correctly quoted rfc822 address, if requested
				if (isset($options['type']) && $options['type'] === 'email')
				{
					$args = explode('@', $contact['email']);
					$args[] = $result[$contact['id']];
					$result[$contact['id']] = call_user_func_array('imap_rfc822_write_address', $args);
				}
				if (!is_array($result[$contact['id']]))
				{
					$result[$contact['id']] = ['label' => $result[$contact['id']]];
				}
				// if we have a real photo, add avatar.php URL
				if (self::hasPhoto($contact))
				{
					$result[$contact['id']] += [
						'icon' => Framework::link('/api/avatar.php', [
							'contact_id' => $contact['id'],
							'modified'   => $contact['modified'],
						])
					];
				}
				// else add Lavatar information lname and fname
				else
				{
					$result[$contact['id']] += [
						'lname' => $contact['n_family'],
						'fname' => $contact['n_given'],
					];
				}
				// show category color
				if ($contact['cat_id'] && ($color = Categories::cats2color($contact['cat_id'])))
				{
					$result[$contact['id']] += [
						'style.backgroundColor' => $color,
					];
				}
			}
		}
		$options['total'] = $this->total;
		return $result;
	}

	/**
	 * Query for subtype email (returns only contacts with email address set)
	 *
	 * @param string|array $pattern
	 * @param array $options
	 * @return Ambigous <multitype:, string, multitype:Ambigous <multitype:, string> string >
	 */
	function link_query_email($pattern, Array &$options = array())
	{
		if (isset($options['filter']) && !is_array($options['filter']))
		{
			$options['filter'] = (array)$options['filter'];
		}
		// return only contacts with email set
		$options['filter'][] = "contact_email ".$this->db->capabilities[Db::CAPABILITY_CASE_INSENSITIV_LIKE]." '%@%'";

		// let link query know, to append email to list
		$options['type'] = 'email';

		return $this->link_query($pattern,$options);
	}

	/**
	 * returns info about contacts for calender
	 *
	 * @param int|array $ids single contact-id or array of id's
	 * @return array
	 */
	function calendar_info($ids)
	{
		if (!$ids) return null;

		$data = array();
		foreach(!is_array($ids) ? array($ids) : $ids as $id)
		{
			if (!($contact = $this->read($id))) continue;

			$data[] = array(
				'res_id' => $id,
				'email' => $contact['email'] ? $contact['email'] : $contact['email_home'],
				'rights' => Acl::CUSTOM1|Acl::CUSTOM3,	// calendar_bo::ACL_READ_FOR_PARTICIPANTS|ACL_INVITE
				'name' => $this->link_title($contact),
				'cn' => trim($contact['n_given'].' '.$contact['n_family']),
			);
		}
		return $data;
	}

	/**
	 * Read the next and last event of given contacts
	 *
	 * @param array $uids participant IDs.  Contacts should be c<contact_id>, user accounts <account_id>
	 * @param boolean $extra_title =true if true, use a short date only title and put the full title as extra_title (tooltip)
	 * @return array
	 */
	function read_calendar($uids,$extra_title=true)
	{
		if (empty($GLOBALS['egw_info']['user']['apps']['calendar']) ||
				$GLOBALS['egw_info']['server']['disable_event_column'] == 'True')
		{
			return array();
		}

		$split_uids = array();
		$events = array();

		foreach($uids as $id => $uid)
		{
			$type = is_numeric($uid[0]) ? 'u' : $uid[0];
			if($GLOBALS['egw_info']['server']['disable_event_column'] !== 'false' && $type === 'u')
			{
				continue;
			}
			$split_uids[$type][$id] = str_replace($type, '', $uid);
		}

		foreach($split_uids as $type => $s_uids)
		{
			$events += $this->read_calendar_type($s_uids, $type, $extra_title);
		}
		return $events;
	}

	private function read_calendar_type($uids, $type='c', $extra_title = true)
	{
		$calendars = array();
		$bocal = new calendar_bo();
		$type_field = $type=='u' ? 'account_id' : 'contact_id';
		$type_field_varchar = $this->db->to_varchar($type_field);
		$concat_start_id_recurrance = $this->db->concat('cal_start',"':'",'egw_cal_user.cal_id',"':'",'cal_recur_date');
		$now = $this->db->unix_timestamp('NOW()');
		$sql = "SELECT n_fn,org_name,$type_field AS user_id,
			(
				SELECT $concat_start_id_recurrance
				FROM egw_cal_user
				JOIN egw_cal_dates on egw_cal_dates.cal_id=egw_cal_user.cal_id and (cal_recur_date=0 or cal_recur_date=cal_start)
				JOIN egw_cal ON egw_cal.cal_id=egw_cal_user.cal_id AND egw_cal.cal_deleted IS NULL
				WHERE cal_user_type='$type' and cal_user_id=$type_field_varchar and cal_start < $now";
		if ( !$GLOBALS['egw_info']['user']['preferences']['calendar']['show_rejected'])
		{
			$sql .= " AND egw_cal_user.cal_status != 'R'";
		}
		$sql .= "
				order by cal_start DESC Limit 1
			) as last_event,
			(
				SELECT $concat_start_id_recurrance
				FROM egw_cal_user
				JOIN egw_cal_dates on egw_cal_dates.cal_id=egw_cal_user.cal_id and (cal_recur_date=0 or cal_recur_date=cal_start)
				JOIN egw_cal ON egw_cal.cal_id=egw_cal_user.cal_id AND egw_cal.cal_deleted IS NULL
				WHERE cal_user_type='$type' and cal_user_id=$type_field_varchar and cal_start > $now";
		if ( !$GLOBALS['egw_info']['user']['preferences']['calendar']['show_rejected'])
		{
			$sql .= " AND egw_cal_user.cal_status != 'R'";
		}
		$sql .= ' order by cal_recur_date ASC, cal_start ASC Limit 1

			) as next_event
			FROM egw_addressbook
			WHERE '.$this->db->expression('egw_addressbook', array($type_field => $uids));


		$contacts = $this->db->query($sql, __LINE__, __FILE__);

		if (!$contacts) return array();

		// Extract the event info and generate what is needed for next/last event
		$do_event = function($key, $contact) use (&$bocal, &$calendars, $type, $extra_title)
		{
			list($start, $cal_id, $recur_date) = explode(':', $contact[$key.'_event']);

			$link = array(
				'id' => $cal_id,//.':'.$start,
				'app' => 'calendar',
				'title' => $bocal->link_title($cal_id . ($start ? '-'.$start : '')),
				'extra_args' => array(
					'date' => DateTime::server2user($start,DateTime::ET2),
					'exception'=> 1
				),
			);
			if ($extra_title)
			{
				$link['extra_title'] = $link['title'];
				$link['title'] = DateTime::server2user($start, true);
			}
			$user_id = ($type == 'u' ? '' : $type) . $contact['user_id'];
			$calendars[$user_id][$key.'_event'] = $start;
			$calendars[$user_id][$key.'_link'] = $link;
		};

		foreach($contacts as $contact)
		{
			if($contact['last_event'])
			{
				$do_event('last', $contact);
			}
			if($contact['next_event'])
			{
				$do_event('next', $contact);
			}
		}
		return $calendars;
	}

	/**
	 * Read the holidays (birthdays) from the given addressbook, either from the
	 * instance cache, or read them & cache for next time.  Cached for HOLIDAY_CACHE_TIME.
	 *
	 * @param int $addressbook - Addressbook to search.  We cache them separately in the instance.
	 * @param int $year
	 */
	public function read_birthdays($addressbook, $year)
	{
		if (($birthdays = Cache::getInstance(__CLASS__,"birthday-$year-$addressbook-".$GLOBALS['egw_info']['user']['preferences']['common']['lang'])) !== null)
		{
			return $birthdays;
		}

		$birthdays = array();
		$filter = array(
			'owner' => (int)$addressbook,
			'n_family' => "!''",
			'bday' => "!''",
		);
		$bdays =& $this->search('',array('id','n_family','n_given','n_prefix','n_middle','bday'),
			'contact_bday ASC', '', '', false, 'AND', false, $filter);

		if ($bdays)
		{
			// sort by month and day only
			usort($bdays, function($a, $b)
			{
				return (int) $a['bday'] == (int) $b['bday'] ?
					strcmp($a['bday'], $b['bday']) :
					(int) $a['bday'] - (int) $b['bday'];
			});
			foreach($bdays as $pers)
			{
				if (empty($pers['bday']) || $pers['bday']=='0000-00-00 0' || $pers['bday']=='0000-00-00' || $pers['bday']=='0.0.00')
				{
					//error_log(__METHOD__.__LINE__.' Skipping entry for invalid birthday:'.array2string($pers));
					continue;
				}
				list($y,$m,$d) = explode('-',$pers['bday']);
				if ($y > $year)
				{
					// not yet born
					continue;
				}
				$birthdays[sprintf('%04d%02d%02d',$year,$m,$d)][] = array(
					'day'       => $d,
					'month'     => $m,
					'occurence' => 0,
					'name'      => implode(' ', array_filter(array(lang('Birthday'),($pers['n_given'] ? $pers['n_given'] : $pers['n_prefix']), $pers['n_middle'],
						$pers['n_family'], ($GLOBALS['egw_info']['server']['hide_birthdays'] == 'age' ? ($year - $y): '')))).
						($y && in_array($GLOBALS['egw_info']['server']['hide_birthdays'], array('','age')) ? ' ('.$y.')' : ''),
					'birthyear' => $y,	// this can be used to identify birthdays from holidays
				);
			}
		}
		Cache::setInstance(__CLASS__,"birthday-$year-$addressbook-".$GLOBALS['egw_info']['user']['preferences']['common']['lang'], $birthdays, self::BIRTHDAY_CACHE_TIME);
		return $birthdays;
	}

	/**
	 * Called by delete-account hook, when an account get deleted --> deletes/moves the personal addressbook
	 *
	 * @param array $data
	 */
	function deleteaccount($data)
	{
		// delete/move personal addressbook
		parent::deleteaccount($data);
	}

	/**
	 * Called by delete_category hook, when a category gets deleted.
	 * Removes the category from addresses
	 */
	function delete_category($data)
	{
		// get all cats if you want to drop sub cats
		$drop_subs = ($data['drop_subs'] && !$data['modify_subs']);
		if($drop_subs)
		{
			$cats = new Categories('', 'addressbook');
			$cat_ids = $cats->return_all_children($data['cat_id']);
		}
		else
		{
			$cat_ids = array($data['cat_id']);
		}

		// Get addresses that use the category
		@set_time_limit( 0 );
		foreach($cat_ids as $cat_id)
		{
			if (($ids = $this->search(array('cat_id' => $cat_id), false)))
			{
				foreach($ids as &$info)
				{
					$info['cat_id'] = implode(',',array_diff(explode(',',$info['cat_id']), $cat_ids));
					$this->save($info);
				}
			}
		}
	}

	/**
	 * Merges some given addresses into the first one and delete the others
	 *
	 * If one of the other addresses is an account, everything is merged into the account.
	 * If two accounts are in $ids, the function fails (returns false).
	 *
	 * @param array $ids contact-id's to merge
	 * @return int number of successful merged contacts, false on a fatal error (eg. cant merge two accounts)
	 */
	function merge($ids)
	{
		$this->error = false;
		$account = null;
		$custom_fields = Storage\Customfields::get('addressbook', true);
		$custom_field_list = $this->read_customfields($ids);
		foreach(parent::search(array('id'=>$ids),false) as $contact)	// $this->search calls the extended search from ui!
		{
			if ($contact['account_id'])
			{
				if (!is_null($account))
				{
					echo $this->error = 'Can not merge more then one account!';
					return false;	// we dont deal with two accounts!
				}
				$account = $contact;
				continue;
			}
			// Add in custom fields
			if (is_array($custom_field_list[$contact['id']])) $contact = array_merge($contact, $custom_field_list[$contact['id']]);

			$pos = array_search($contact['id'],$ids);
			$contacts[$pos] = $contact;
		}
		if (!is_null($account))	// we found an account, so we merge the contacts into it
		{
			$target = $account;
			unset($account);
		}
		else					// we found no account, so we merge all but the first into the first
		{
			$target = $contacts[0];
			unset($contacts[0]);
		}
		if (!$this->check_perms(Acl::EDIT,$target))
		{
			echo $this->error = 'No edit permission for the target contact!';
			return 0;
		}
		foreach($contacts as $contact)
		{
			foreach($contact as $name => $value)
			{
				if (!$value) continue;

				switch($name)
				{
					case 'id':
					case 'tid':
					case 'owner':
					case 'private':
					case 'etag';
						break;	// ignored

					case 'cat_id':	// cats are all merged together
						if (!is_array($target['cat_id'])) $target['cat_id'] = $target['cat_id'] ? explode(',',$target['cat_id']) : array();
						$target['cat_id'] = array_unique(array_merge($target['cat_id'],is_array($value)?$value:explode(',',$value)));
						break;

					default:
						// Multi-select custom fields can also be merged
						if($name[0] == '#') {
							$c_name = substr($name, 1);
							if($custom_fields[$c_name]['type'] == 'select' && $custom_fields[$c_name]['rows'] > 1) {
								if (!is_array($target[$name])) $target[$name] = $target[$name] ? explode(',',$target[$name]) : array();
								$target[$name] = implode(',',array_unique(array_merge($target[$name],is_array($value)?$value:explode(',',$value))));
							}
						}
						if (!$target[$name]) $target[$name] = $value;
						break;
				}
			}

			// Merge distribution lists
			$lists = $this->read_distributionlist(array($contact['id']));
			foreach($lists[$contact['id']] as $list_id => $list_name)
			{
				parent::add2list($target['id'], $list_id);
			}
		}
		if (!$this->save($target)) return 0;

		$success = 1;
		foreach($contacts as $contact)
		{
			if (!$this->check_perms(Acl::DELETE,$contact))
			{
				continue;
			}
			foreach(Link::get_links('addressbook',$contact['id']) as $data)
			{
				//_debug_array(array('function'=>__METHOD__,'line'=>__LINE__,'app'=>'addressbook','id'=>$contact['id'],'data:'=>$data,'target'=>$target['id']));
				// info_from and info_link_id (main link)
				$newlinkID = Link::link('addressbook',$target['id'],$data['app'],$data['id'],$data['remark'],$target['owner']);
				//_debug_array(array('newLinkID'=>$newlinkID));
				if ($newlinkID)
				{
					// update egw_infolog set info_link_id=$newlinkID where info_id=$data['id'] and info_link_id=$data['link_id']
					if ($data['app']=='infolog')
					{
						$this->db->update('egw_infolog',array(
								'info_link_id' => $newlinkID
							),array(
								'info_id' => $data['id'],
								'info_link_id' => $data['link_id']
							),__LINE__,__FILE__,'infolog');
					}
					unset($newlinkID);
				}
			}
			// Update calendar
			$this->merge_calendar('c'.$contact['id'], $target['account_id'] ? 'u'.$target['account_id'] : 'c'.$target['id']);

			if ($this->delete($contact['id'])) $success++;
		}
		return $success;
	}

	/**
	 * Change the contact ID in any calendar events from the old contact ID
	 * to the new merged ID
	 *
	 * @param int $old_id
	 * @param int $new_id
	 */
	protected function merge_calendar($old_id, $new_id)
	{
		static $bo;
		if(!is_object($bo))
		{
			$bo = new \calendar_boupdate();
		}

		// Find all events with this contact
		$events = $bo->search(array('users' => $old_id, 'ignore_acl' => true));

		foreach($events as $event)
		{
			$event['participants'][$new_id] = $event['participants'][$old_id];
			unset($event['participants'][$old_id]);

			// Quietly update, ignoring ACL & no notifications
			$bo->update($event, true, true, true, true, $messages, true);
		}
	}

	/**
	 * Some caching for lists within request
	 *
	 * @var array
	 */
	private static $list_cache = array();

	/**
	 * Check if user has required rights for a list or list-owner
	 *
	 * @param int $list
	 * @param int $required
	 * @param int $owner =null
	 * @return boolean
	 */
	function check_list($list,$required,$owner=null)
	{
		if ($list && ($list_data = $this->read_list($list)))
		{
			$owner = $list_data['list_owner'];
		}
		//error_log(__METHOD__."($list, $required, $owner) grants[$owner]=".$this->grants[$owner]." returning ".array2string(!!($this->grants[$owner] & $required)));
		return !!($this->grants[$owner] & $required);
	}

	/**
	 * Adds / updates a distribution list
	 *
	 * @param string|array $keys list-name or array with column-name => value pairs to specify the list
	 * @param int $owner user- or group-id
	 * @param array $contacts =array() contacts to add (only for not yet existing lists!)
	 * @param array &$data=array() values for keys 'list_uid', 'list_carddav_name', 'list_name'
	 * @return int|boolean integer list_id or false on error
	 */
	function add_list($keys,$owner,$contacts=array(),array &$data=array())
	{
		if (!$this->check_list(null,Acl::ADD|Acl::EDIT,$owner)) return false;

		try {
			$ret = parent::add_list($keys,$owner,$contacts,$data);
			if ($ret) unset(self::$list_cache[$ret]);
		}
		// catch sql error, as creating same name&owner list gives a sql error doublicate key
		catch(Db\Exception\InvalidSql $e) {
			unset($e);	// not used
			return false;
		}
		return $ret;
	}

	/**
	 * Adds contacts to a distribution list
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list list-id
	 * @param array $existing =null array of existing contact-id(s) of list, to not reread it, eg. array()
	 * @return false on error
	 */
	function add2list($contact,$list,array $existing=null)
	{
		if (!$this->check_list($list,Acl::EDIT)) return false;

		unset(self::$list_cache[$list]);

		return parent::add2list($contact,$list,$existing);
	}

	/**
	 * Removes one contact from distribution list(s)
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list list-id
	 * @return false on error
	 */
	function remove_from_list($contact,$list=null)
	{
		if ($list && !$this->check_list($list,Acl::EDIT)) return false;

		if ($list)
		{
			unset(self::$list_cache[$list]);
		}
		else
		{
			self::$list_cache = array();
		}

		return parent::remove_from_list($contact,$list);
	}

	/**
	 * Deletes a distribution list (incl. it's members)
	 *
	 * @param int|array $list list_id(s)
	 * @return number of members deleted or false if list does not exist
	 */
	function delete_list($list)
	{
		foreach((array)$list as $l)
		{
			if (!$this->check_list($l, Acl::DELETE)) return false;

			unset(self::$list_cache[$l]);
		}

		return parent::delete_list($list);
	}

	/**
	 * Read data of a distribution list
	 *
	 * @param int $list list_id
	 * @return array of data or false if list does not exist
	 */
	function read_list($list)
	{
		if (isset(self::$list_cache[$list])) return self::$list_cache[$list];

		return self::$list_cache[$list] = parent::read_list($list);
	}

	/**
	 * Get the address-format of a country
	 *
	 * This is a good reference where I got nearly all information, thanks to mikaelarhelger-AT-gmail.com
	 * http://www.bitboost.com/ref/international-address-formats.html
	 *
	 * Mail me (RalfBecker-AT-outdoor-training.de) if you want your nation added or fixed.
	 *
	 * @param string $country
	 * @return string 'city_state_postcode' (eg. US) or 'postcode_city' (eg. DE)
	 */
	function addr_format_by_country($country)
	{
		$code = Country::country_code($country);

		switch($code)
		{
			case 'AU':
			case 'CA':
			case 'GB':	// not exactly right, postcode is in separate line
			case 'HK':	// not exactly right, they have no postcode
			case 'IN':
			case 'ID':
			case 'IE':	// not exactly right, they have no postcode
			case 'JP':	// not exactly right
			case 'KR':
			case 'LV':
			case 'NZ':
			case 'TW':
			case 'SA':	// not exactly right, postcode is in separate line
			case 'SG':
			case 'US':
				$adr_format = 'city_state_postcode';
				break;

			case 'AR':
			case 'AT':
			case 'BE':
			case 'CH':
			case 'CZ':
			case 'DK':
			case 'EE':
			case 'ES':
			case 'FI':
			case 'FR':
			case 'DE':
			case 'GL':
			case 'IS':
			case 'IL':
			case 'IT':
			case 'LT':
			case 'LU':
			case 'MY':
			case 'MX':
			case 'NL':
			case 'NO':
			case 'PL':
			case 'PT':
			case 'RO':
			case 'RU':
			case 'SE':
				$adr_format = 'postcode_city';
				break;

			default:
				$adr_format = $this->prefs['addr_format'] ? $this->prefs['addr_format'] : 'postcode_city';
		}
		return $adr_format;
	}

	/**
	 * Find existing categories in database by name or add categories that do not exist yet
	 * currently used for vcard import
	 *
	 * @param array $catname_list names of the categories which should be found or added
	 * @param int $contact_id =null match against existing contact and expand the returned category ids
	 *  by the ones the user normally does not see due to category permissions - used to preserve categories
	 * @return array category ids (found, added and preserved categories)
	 */
	function find_or_add_categories($catname_list, $contact_id=null)
	{
		if ($contact_id && $contact_id > 0 && ($old_contact = $this->read($contact_id)))
		{
			// preserve categories without users read access
			$old_categories = explode(',',$old_contact['cat_id']);
			$old_cats_preserve = array();
			if (is_array($old_categories) && count($old_categories) > 0)
			{
				foreach ($old_categories as $cat_id)
				{
					if (!$this->categories->check_perms(Acl::READ, $cat_id))
					{
						$old_cats_preserve[] = $cat_id;
					}
				}
			}
		}

		$cat_id_list = array();
		foreach ((array)$catname_list as $cat_name)
		{
			$cat_name = trim($cat_name);
			$cat_id = $this->categories->name2id($cat_name, 'X-');
			if (!$cat_id)
			{
				// some SyncML clients (mostly phones) add an X- to the category names
				if (strncmp($cat_name, 'X-', 2) == 0)
				{
					$cat_name = substr($cat_name, 2);
				}
				$cat_id = $this->categories->add(array('name' => $cat_name, 'descr' => $cat_name, 'access' => 'private'));
			}

			if ($cat_id)
			{
				$cat_id_list[] = $cat_id;
			}
		}

		if (is_array($old_cats_preserve) && count($old_cats_preserve) > 0)
		{
			$cat_id_list = array_merge($cat_id_list, $old_cats_preserve);
		}

		if (count($cat_id_list) > 1)
		{
			$cat_id_list = array_unique($cat_id_list);
			sort($cat_id_list, SORT_NUMERIC);
		}

		//error_log(__METHOD__."(".array2string($catname_list).", $contact_id) returning ".array2string($cat_id_list));
		return $cat_id_list;
	}

	function get_categories($cat_id_list)
	{
		if (!is_object($this->categories))
		{
			$this->categories = new Categories($this->user,'addressbook');
		}

		if (!is_array($cat_id_list))
		{
			$cat_id_list = explode(',',$cat_id_list);
		}
		$cat_list = array();
		foreach($cat_id_list as $cat_id)
		{
			if ($cat_id && $this->categories->check_perms(Acl::READ, $cat_id) &&
					($cat_name = $this->categories->id2name($cat_id)) && $cat_name != '--')
			{
				$cat_list[] = $cat_name;
			}
		}

		return $cat_list;
	}

	function fixup_contact(&$contact)
	{
		if (empty($contact['n_fn']))
		{
			$contact['n_fn'] = $this->fullname($contact);
		}

		if (empty($contact['n_fileas']))
		{
			$contact['n_fileas'] = $this->fileas($contact);
		}
	}

	/**
	 * Try to find a matching db entry
	 *
	 * @param array $contact   the contact data we try to find
	 * @param boolean $relax =false if asked to relax, we only match against some key fields
	 * @return array od matching contact_ids
	 */
	function find_contact($contact, $relax=false)
	{
		$empty_addr_one = $empty_addr_two = true;

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
				. '('. ($relax ? 'RELAX': 'EXACT') . ')[ContactData]:'
				. array2string($contact)
				. "\n", 3, $this->logfile);
		}

		$matchingContacts = array();
		if ($contact['id'] && ($found = $this->read($contact['id'])))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. '()[ContactID]: ' . $contact['id']
					. "\n", 3, $this->logfile);
			}
			// We only do a simple consistency check
			if (!$relax || ((empty($found['n_family']) || $found['n_family'] == $contact['n_family'])
					&& (empty($found['n_given']) || $found['n_given'] == $contact['n_given'])
					&& (empty($found['org_name']) || $found['org_name'] == $contact['org_name'])))
			{
				return array($found['id']);
			}
		}
		unset($contact['id']);

		if (!$relax && !empty($contact['uid']))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. '()[ContactUID]: ' . $contact['uid']
					. "\n", 3, $this->logfile);
			}
			// Try the given UID first
			$criteria = array ('contact_uid' => $contact['uid']);
			if (($foundContacts = parent::search($criteria)))
			{
				foreach ($foundContacts as $egwContact)
				{
					$matchingContacts[] = $egwContact['id'];
				}
			}
			return $matchingContacts;
		}
		unset($contact['uid']);

		$columns_to_search = array('n_family', 'n_given', 'n_middle', 'n_prefix', 'n_suffix',
						'bday', 'org_name', 'org_unit', 'title', 'role',
						'email', 'email_home');
		$tolerance_fields = array('n_middle', 'n_prefix', 'n_suffix',
					  'bday', 'org_unit', 'title', 'role',
					  'email', 'email_home');
		$addr_one_fields = array('adr_one_street', 'adr_one_locality',
					 'adr_one_region', 'adr_one_postalcode');
		$addr_two_fields = array('adr_two_street', 'adr_two_locality',
					 'adr_two_region', 'adr_two_postalcode');

		if (!empty($contact['owner']))
		{
			$columns_to_search += array('owner');
		}

		$criteria = array();

		foreach ($columns_to_search as $field)
		{
			if ($relax && in_array($field, $tolerance_fields)) continue;

			if (empty($contact[$field]))
			{
				// Not every device supports all fields
				if (!in_array($field, $tolerance_fields))
				{
					$criteria[$field] = '';
				}
			}
			else
			{
				$criteria[$field] = $contact[$field];
			}
		}

		if (!$relax)
		{
			// We use addresses only for strong matching

			foreach ($addr_one_fields as $field)
			{
				if (empty($contact[$field]))
				{
					$criteria[$field] = '';
				}
				else
				{
					$empty_addr_one = false;
					$criteria[$field] = $contact[$field];
				}
			}

			foreach ($addr_two_fields as $field)
			{
				if (empty($contact[$field]))
				{
					$criteria[$field] = '';
				}
				else
				{
					$empty_addr_two = false;
					$criteria[$field] = $contact[$field];
				}
			}
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
				. '()[Addressbook FIND Step 1]: '
				. 'CRITERIA = ' . array2string($criteria)
				. "\n", 3, $this->logfile);
		}

		// first try full match
		if (($foundContacts = parent::search($criteria, true, '', '', '', true)))
		{
			foreach ($foundContacts as $egwContact)
			{
				$matchingContacts[] = $egwContact['id'];
			}
		}

		// No need for more searches for relaxed matching
		if ($relax || count($matchingContacts)) return $matchingContacts;


		if (!$empty_addr_one && $empty_addr_two)
		{
			// try given address and ignore the second one in EGW
			foreach ($addr_two_fields as $field)
			{
				unset($criteria[$field]);
			}

			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. '()[Addressbook FIND Step 2]: '
					. 'CRITERIA = ' . array2string($criteria)
					. "\n", 3, $this->logfile);
			}

			if (($foundContacts = parent::search($criteria, true, '', '', '', true)))
			{
				foreach ($foundContacts as $egwContact)
				{
					$matchingContacts[] = $egwContact['id'];
				}
			}
			else
			{
				// try address as home address -- some devices don't qualify addresses
				foreach ($addr_two_fields as $key => $field)
				{
					$criteria[$field] = $criteria[$addr_one_fields[$key]];
					unset($criteria[$addr_one_fields[$key]]);
				}

				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
						. '()[Addressbook FIND Step 3]: '
						. 'CRITERIA = ' . array2string($criteria)
						. "\n", 3, $this->logfile);
				}

				if (($foundContacts = parent::search($criteria, true, '', '', '', true)))
				{
					foreach ($foundContacts as $egwContact)
					{
						$matchingContacts[] = $egwContact['id'];
					}
				}
			}
		}
		elseif (!$empty_addr_one && !$empty_addr_two)
		{ // try again after address swap

			foreach ($addr_one_fields as $key => $field)
			{
				$_temp = $criteria[$field];
				$criteria[$field] = $criteria[$addr_two_fields[$key]];
				$criteria[$addr_two_fields[$key]] = $_temp;
			}
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. '()[Addressbook FIND Step 4]: '
					. 'CRITERIA = ' . array2string($criteria)
					. "\n", 3, $this->logfile);
			}
			if (($foundContacts = parent::search($criteria, true, '', '', '', true)))
			{
				foreach ($foundContacts as $egwContact)
				{
					$matchingContacts[] = $egwContact['id'];
				}
			}
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
				. '()[FOUND]: ' . array2string($matchingContacts)
				. "\n", 3, $this->logfile);
		}
		return $matchingContacts;
	}

	/**
	 * Get a ctag (collection tag) for one addressbook or all addressbooks readable by a user
	 *
	 * Currently implemented as maximum modification date (1 seconde granularity!)
	 *
	 * We have to include deleted entries, as otherwise the ctag will not change if an entry gets deleted!
	 * (Only works if tracking of deleted entries / history is switched on!)
	 *
	 * @param int|array $owner =null 0=accounts, null=all addressbooks or integer account_id of user or group
	 * @return string
	 */
	public function get_ctag($owner=null)
	{
		$filter = array('tid' => null);	// tid=null --> use all entries incl. deleted (tid='D')
		// show addressbook of a single user?
		if (!is_null($owner)) $filter['owner'] = $owner;

		// should we hide the accounts addressbook
		if (!$owner && $GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] === '1')
		{
			$filter['account_id'] = null;
		}
		$result = $this->search(array(),'contact_modified','contact_modified DESC','','',false,'AND',array(0,1),$filter);

		if (!$result || !isset($result[0]['modified']))
		{
			$ctag = 'empty';	// ctag for empty addressbook
		}
		else
		{
			// need to convert modified time back to server-time (was converted to user-time by search)
			// as we use it direct in server-queries eg. CardDAV sync-report and to be consistent with CalDAV
			$ctag = DateTime::user2server($result[0]['modified']);
		}
		//error_log(__METHOD__.'('.array2string($owner).') returning '.array2string($ctag));
		return $ctag;
	}

	/**
	 * Check if given contact has a real photo attached
	 *
	 * @param array $contact
	 * @param string|null &$url on return vfs URL of photo
	 * @param int|null &$size on return size of photo
	 * @return bool
	 */
	public static function hasPhoto(array $contact, string &$url=null, int &$size=null)
	{
		return !empty($contact['jpegphoto']) ||                           // LDAP/AD (not updated SQL)
			(($contact['files'] ?? 0) & self::FILES_BIT_PHOTO) && // new SQL in VFS
				($size = filesize($url = Link::vfs_path('addressbook', $contact['id'], self::FILES_PHOTO)));
	}

	/**
	 * download photo of the given ($_GET['contact_id'] or $_GET['account_id']) contact
	 */
	function photo()
	{
		ob_start();

		$contact_id = isset($_GET['contact_id']) ? $_GET['contact_id'] :
			(isset($_GET['account_id']) ? 'account:'.$_GET['account_id'] : null);

		if (substr($contact_id,0,8) == 'account:')
		{
			$id = substr($contact_id, 8);
			$contact_id = $GLOBALS['egw']->accounts->id2name(substr($contact_id, 8), 'person_id');
		}

		if (!$contact_id && !empty($_GET['email']))
		{
			$email = strtolower(current(Mail::stripRFC822Addresses([$_GET['email']])));

			$contact = current($this->search(
				['contact_email' => $email, 'contact_email_home' => $email],
				['contact_id', 'email', 'email_home', 'n_fn', 'n_given', 'n_family', 'contact_files', 'etag'],
				'contact_files & ' . self::FILES_BIT_PHOTO . ' DESC',
				!empty($GLOBALS['egw_info']['user']['preferences']['common']['avatar_display']) ? ['account_lid'] : [],
				'', false, 'OR', [0, 1]
			) ?: []);
			if(!$contact || $_GET['no_gen'] && !((int)$contact['files'] & self::FILES_BIT_PHOTO))
			{
				Session::cache_control(86400 * 10);    // cache for 10 days
				header('Content-type: image/jpeg');
				http_response_code(404);
				exit;
			}
		}
		else
		{
			$contact = $this->read($contact_id);
		}

		if (!$contact || !self::hasPhoto($contact, $url, $size))
		{
			if(!$contact_id && isset($id) && $id < 0)
			{
				$group = $GLOBALS['egw']->accounts->read($id);
				$contact = array(
					'id'      => $id,
					'n_given' => $group['account_firstname']
				);
			}
			if(is_array($contact))
			{
				$use_account_lid = empty($contact['account_lid']) && empty($contact['account_id']) ? 0 :
					$GLOBALS['egw_info']['user']['preferences']['common']['avatar_display'] ?? 0;

				header('Content-type: image/jpeg');
				$contact['jpegphoto'] =  Contacts\Lavatar::generate([
					'id' => $contact['id'],
					'firstname' => $contact['n_given'],
					'lastname' => $contact['n_family'],
					'account_lid' => $contact['account_lid'] ??
						(empty($contact['account_id']) ? null : Accounts::getInstance()->id2name($contact['account_id'])),
				], null, 128, $use_account_lid);
			}
		}

		// use an etag over the image mapp
		$etag = '"'.$contact['id'].':'.$contact['etag'].'"';
		if (!ob_get_contents())
		{
			header('Content-type: image/jpeg');
			header('ETag: '.$etag);
			// if etag parameter given in url, we can allow browser to cache picture via an Expires header
			// different url with different etag parameter will force a reload
			if (isset($_GET['etag']))
			{
				Session::cache_control(30*86400);	// cache for 30 days
			}
			else
			{
				Session::cache_control(7*86400);	// cache for 7 days
			}
			// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
			{
				header("HTTP/1.1 304 Not Modified");
			}
			elseif(!empty($contact['jpegphoto']))
			{
				header('Content-length: '.bytes($contact['jpegphoto']));
				echo $contact['jpegphoto'];
			}
			elseif ($url)
			{
				header('Content-length: '.$size);
				readfile($url);
			}
			exit();
		}
		Egw::redirect(Image::find('addressbook','photo'));
	}

	/**
	 * Regular expression to search for an exact phone number match instead of regular search
	 *
	 * Requires a leading + or digit and only numbers (ignores ./-() and space) plus minimum length of 9 chars
	 */
	const PHONE_PREG = '/^(\+|\d)[0-9 ()\/.-]{8,}$/';

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * @param array|string $criteria array of key and data cols, OR string to search over all standard search fields
	 * @param boolean|string $only_keys =true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 *  $filter['cols_to_search'] limit search columns to given columns, otherwise $this->columns_to_search is used
	 * @param string $join ='' sql to do a join (only used by sql backend!), eg. " RIGHT JOIN egw_accounts USING(account_id)"
	 * @param boolean $ignore_acl =false true: no acl check
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria, $only_keys = True, $order_by = '', $extra_cols = '', $wildcard = '', $empty = False, $op = 'AND', $start = false, $filter = null, $join = '', $ignore_acl = false)
	{
		if (is_string($criteria) && preg_match(self::PHONE_PREG, $criteria))
		{
			try {
				return $this->phoneSearch($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join, $ignore_acl);
			}
			catch (\Exception $e) {
				// try regular search
			}
		}
		return parent::search($criteria, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join, $ignore_acl);
	}

	/**
	 * searches contacts containing a given phone-number
	 *
	 * @param string $criteria phone-number
	 * @param boolean|string $only_keys =true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 *  $filter['cols_to_search'] limit search columns to given columns, otherwise $this->columns_to_search is used
	 * @param string $join ='' sql to do a join (only used by sql backend!), eg. " RIGHT JOIN egw_accounts USING(account_id)"
	 * @param boolean $ignore_acl =false true: no acl check
	 * @param null|string|array $fields_to_search =null which phone-numbers to search, default all (only honored for Sql backend!)
	 * @return array of matching rows (the row is an array of the cols) or False
	 * @throws Exception\WrongParameter|\libphonenumber\NumberParseException if $critera is not a string with a valid phone-number
	 * @throws Exception\NotFound if no contact matches the phone-number in $criteria
	 */
	function &phoneSearch($criteria, $only_keys = false, $order_by = 'contact_modified DESC', $extra_cols = '',
		$wildcard = '', $empty = False, $op = 'AND', $start = false, $filter = null, $join = '', $ignore_acl = false,
		$fields_to_search=null)
	{
		$phoneNumberUtil = PhoneNumberUtil::getInstance();
		$region = $GLOBALS['egw_info']['user']['preferences']['common']['country'] ?: 'DE';
		$number = $phoneNumberUtil->parse($criteria, $region);
		if (!$phoneNumberUtil->isValidNumber($number))
		{
			throw new Exception\WrongParameter('Not a valid phone-number!');
		}
		if ($only_keys === true) $only_keys = false;
		$start = false;	// no pagination
		// returns "+49 123 4567890" or "+1 123-456-7890"
		list($country, $area, $rest) = preg_split('/[ -]/',
			$phoneNumberUtil->format($number, \libphonenumber\PhoneNumberFormat::INTERNATIONAL), 3);
		$rest_without_space = preg_replace('/[^0-9]/', '', $rest);
		/** @var Contacts\Sql */
		$backend = $this->get_backend(null, $filter['owner'] ?? null);
		// SQL Backend supporting regexp_replace (MySQL 8.0+ or MariaDB 10.0+ or PostgreSQL)
		if (is_a($backend, Contacts\Sql::class) && $this->db->regexp_replace('test', '', '') !== 'test')
		{
			$patterns = [$area.$rest_without_space];
		}
		// LDAP or AD backend (were EGroupware does NOT support boolean search) only search for local part hopefully stored without any formatting
		elseif (is_a($backend, Contacts\Ldap::class))
		{
			$patterns = [$rest_without_space];
		}
		// older SQL databases
		else
		{
			$patterns = [
				$area . ' +' . $rest_without_space,
				// strip last 4 digits off, in case they are written as extension or formatted like 123 45 67
				$area . ' +' . substr($rest_without_space, 0, -4),
				// use first 2 digit from rest, in case they are written as extension or formatted like 12 3...
				$area . ' +' . substr($rest_without_space, 0, 2),
				'"'.$criteria.'"',	// try exact match
			];
		}
		if (empty($fields_to_search))
		{
			$fields_to_search = ['tel_work', 'tel_cell', 'tel_fax', 'tel_car', 'tel_pager',
				'tel_home', 'tel_fax_home', 'tel_cell_private', 'tel_other',
				'tel_assistent', // match tel_assistent last, as it moves contacts to the end of the search result
			];
		}
		foreach($patterns as $pattern)
		{
			if (is_a($backend, Contacts\Sql::class))
			{
				$pattern = $backend->search2criteria($pattern, $wildcard, $op, null,
					(array)$fields_to_search, false);
			}
			else
			{
				$filter['cols_to_search'] = $fields_to_search;
			}
			$rows = parent::search($pattern, $only_keys, $order_by, $extra_cols, $wildcard, $empty, $op, $start, $filter, $join, $ignore_acl) ?: [];
			foreach($rows as $key => $row)
			{
				$found = false;
				foreach($row as $name => $value)
				{
					if (substr($name, 0, 4) === 'tel_' && !empty($value))
					{
						try {
							// we sanitize the number a little, as phoneNumberUtil e.g. chokes on pipe char ("|") when used in phone numbers
							$tel = $phoneNumberUtil->parse(preg_replace('/[^+0-9()\/ -]+/', '', $value),
								// prefer region of contact, to eg. be able to parse US numbers starting direct with areacode but no leading 0
								$row[substr($name, -5) === '_home' ? 'adr_two_countrycode' : 'adr_one_countrycode'] ?:
								$row['adr_one_countrycode'] ?: $region);

							if (($found = $tel->equals($number)))
							{
								$rows[$key]['tel_matching'] = $name;
								$rows[$key]['org_order'] = $key;
								break;
							}
						}
						catch (\Exception $e) {
							// ignore broken numbers
						}
					}
				}
				//if ($found) error_log(__METHOD__."('$criteria') found #$row[id]: $row[n_fn], $name: $value");
				if (!$found) unset($rows[$key]);
			}
			if ($rows)
			{
				$this->total = count($rows);
				$rows = array_values($rows);

				// sort assistent phone matches to end of list
				usort($rows, static function($a, $b)
				{
					$a_assistent = $a['tel_matching'] === 'tel_assistent';
					$b_assistent = $b['tel_matching'] === 'tel_assistent';
					if ($a_assistent !== $b_assistent)
					{
						return (int)$a_assistent - (int)$b_assistent;
					}
					return $a['org_order'] - $b['org_order'];
				});
				//error_log(__METHOD__."('$criteria') returning $this->total rows: ".json_encode($rows));
				return $rows;
			}
		}
		throw new Exception\NotFound("No contacts with phone number '$criteria' found!");
	}

	/**
	 * Open CRM view for a calling number by sending a push requestion to the user
	 *
	 * @param string $from phone-number
	 * @param ?array& $found =null on return found contacts
	 * @throws Exception\WrongParameter|\libphonenumber\NumberParseException if $critera is not a string with a valid phone-number
	 * @throws Exception\NotFound if no contact matches the phone-number in $criteria
	 */
	function openCrmView($from, array &$found=null)
	{
		$found = $this->phoneSearch($from);
		// ToDo: select best match from multiple matches containing the number
		$contact = current($found);
		$push = new Json\Push($this->user);
		// check user preference which CRM view to use
		$prefs = (new Preferences($this->user))->read_repository();
		$crm_list = $prefs['addressbook']['crm_list'];
		if (empty($crm_list) || $crm_list === '~edit~') $crm_list = 'infolog';
		if ($crm_list === 'infolog' && count($found) > 1 && !empty($contact['org_name']))
		{
			$crm_list = 'infolog-organisation';
		}
		$push->call($func='app.addressbook.openCRMview', $arg=[
			'contact_id' => (int)$contact['id'],
			'crm_list' => $crm_list,
			'title' => count($found) > 1 && $contact['org_name'] ?
				$contact['org_name'] : $contact['n_fn'].' ('.lang($crm_list).')',
			'icon' => $contact['photo'],
		]);
		$arg = json_encode($arg, JSON_UNESCAPED_SLASHES);
		error_log("crm.php: calling push(#$this->user)->call('$func', $arg)");
		return "calling push(#$this->user)->call('$func', $arg)";
	}

	/**
	 * Check if current user is allowed to see the account-name column
	 *
	 * @return boolean
	 */
	public static function allowAccountName()
	{
		if (!empty($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			return true;
		}
		return !empty($GLOBALS['egw_info']['server']['allow_account_name']) &&
			array_intersect((array)$GLOBALS['egw_info']['server']['allow_account_name'],
				Accounts::getInstance()->memberships($GLOBALS['egw_info']['user']['account_id']));
	}
}