<?php
/**
 * Addressbook - General business object
 *
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package addressbook
 * @copyright (c) 2005-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * General business object of the adressbook
 */
class addressbook_bo extends addressbook_so
{
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
	 * @var categories
	 */
	var $categories;

	/**
	* Tracking changes
	*
	* @var addressbook_tracking
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
	 * @param string $contact_app='addressbook' used for acl->get_grants()
	 * @param egw_db $db=null
	 */
	function __construct($contact_app='addressbook',egw_db $db=null)
	{
		parent::__construct($contact_app,$db);
		if ($this->log)
		{
			$this->logfile = $GLOBALS['egw_info']['server']['temp_dir'].'/log-addressbook_bo';
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($contact_app)\n", 3 ,$this->logfile);
		}

		$this->now_su = egw_time::to('now','ts');

		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['addressbook'];
		// get the default addressbook from the users prefs
		$this->default_addressbook = $GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default'] ?
			(int)$GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default'] : $this->user;
		$this->default_private = substr($GLOBALS['egw_info']['user']['preferences']['addressbook']['add_default'],-1) == 'p';
		if ($this->default_addressbook > 0 && $this->default_addressbook != $this->user &&
			($this->default_private ||
			$this->default_addressbook == (int)$GLOBALS['egw']->preferences->forced['addressbook']['add_default'] ||
			$this->default_addressbook == (int)$GLOBALS['egw']->preferences->default['addressbook']['add_default']))
		{
			$this->default_addressbook = $this->user;	// admin set a default or forced pref for personal addressbook
		}
		$this->private_addressbook = $this->contact_repository == 'sql' && $this->prefs['private_addressbook'];

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
			'org_name'             => lang('Company'),
			'org_unit'             => lang('Department'),
			'title'                => lang('title'),
			'role'                 => lang('role'),
			'assistent'            => lang('Assistent'),
			'room'                 => lang('Room'),
			'adr_one_street'       => lang('street').' ('.lang('business').')',
			'adr_one_street2'      => lang('address line 2').' ('.lang('business').')',
			'adr_one_locality'     => lang('city').' ('.lang('business').')',
			'adr_one_region'       => lang('state').' ('.lang('business').')',
			'adr_one_postalcode'   => lang('zip code').' ('.lang('business').')',
			'adr_one_countryname'  => lang('country').' ('.lang('business').')',
			'adr_one_countrycode'  => lang('country code').' ('.lang('business').')',
			'label'                => lang('label'),
			'adr_two_street'       => lang('street').' ('.lang('private').')',
			'adr_two_street2'      => lang('address line 2').' ('.lang('private').')',
			'adr_two_locality'     => lang('city').' ('.lang('private').')',
			'adr_two_region'       => lang('state').' ('.lang('private').')',
			'adr_two_postalcode'   => lang('zip code').' ('.lang('private').')',
			'adr_two_countryname'  => lang('country').' ('.lang('private').')',
			'adr_two_countrycode'  => lang('country code').' ('.lang('private').')',
			'tel_work'             => lang('work phone'),
			'tel_cell'             => lang('mobile phone'),
			'tel_fax'              => lang('fax').' ('.lang('business').')',
			'tel_assistent'        => lang('assistent phone'),
			'tel_car'              => lang('car phone'),
			'tel_pager'            => lang('pager'),
			'tel_home'             => lang('home phone'),
			'tel_fax_home'         => lang('fax').' ('.lang('private').')',
			'tel_cell_private'     => lang('mobile phone').' ('.lang('private').')',
			'tel_other'            => lang('other phone'),
			'tel_prefer'           => lang('preferred phone'),
			'email'                => lang('email').' ('.lang('business').')',
			'email_home'           => lang('email').' ('.lang('private').')',
			'url'                  => lang('url').' ('.lang('business').')',
			'url_home'             => lang('url').' ('.lang('private').')',
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
		$this->own_account_acl = $GLOBALS['egw_info']['server']['own_account_acl'];
		if (!is_array($this->own_account_acl)) $this->own_account_acl = unserialize($this->own_account_acl);
		// we have only one acl (n_fn) for the whole name, as not all backends store every part in an own field
		if ($this->own_account_acl && in_array('n_fn',$this->own_account_acl))
		{
			$this->own_account_acl = array_merge($this->own_account_acl,array('n_prefix','n_given','n_middle','n_family','n_suffix'));
		}
		if ($GLOBALS['egw_info']['server']['org_fileds_to_update'])
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
		$this->categories = new categories($this->user,'addressbook');

		$this->tracking = new addressbook_tracking($this);

		$this->delete_history = $GLOBALS['egw_info']['server']['history'];
	}

	/**
	 * Get the availible addressbooks of the user
	 *
	 * @param int $required=EGW_ACL_READ required rights on the addressbook or multiple rights or'ed together,
	 * 	to return only addressbooks fullfilling all the given rights
	 * @param string $extra_label first label if given (already translated)
	 * @return array with owner => label pairs
	 */
	function get_addressbooks($required=EGW_ACL_READ,$extra_label=null)
	{
		//echo "uicontacts::get_addressbooks($required,$include_all) grants="; _debug_array($this->grants);

		$addressbooks = $to_sort = array();
		if ($extra_label) $addressbooks[''] = $extra_label;
		$addressbooks[$this->user] = lang('Personal');
		// add all group addressbooks the user has the necessary rights too
		foreach($this->grants as $uid => $rights)
		{
			if (($rights & $required) == $required && $GLOBALS['egw']->accounts->get_type($uid) == 'g')
			{
				$to_sort[$uid] = lang('Group %1',$GLOBALS['egw']->accounts->id2name($uid));
			}
		}
		if ($to_sort)
		{
			asort($to_sort);
			$addressbooks += $to_sort;
		}
		if (!$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] && (
				($this->grants[0] & $required) == $required ||
				$GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'groupmembers' &&
				$this->account_repository != 'ldap' && ($required & EGW_ACL_READ)))
		{
			$addressbooks[0] = lang('Accounts');
		}
		// add all other user addressbooks the user has the necessary rights too
		$to_sort = array();
		foreach($this->grants as $uid => $rights)
		{
			if ($uid != $this->user && ($rights & $required) == $required && $GLOBALS['egw']->accounts->get_type($uid) == 'u')
			{
				$to_sort[$uid] = $GLOBALS['egw']->common->grab_owner_name($uid);
			}
		}
		if ($to_sort)
		{
			asort($to_sort);
			$addressbooks += $to_sort;
		}
		if ($this->private_addressbook)
		{
			$addressbooks[$this->user.'p'] = lang('Private');
		}
		//echo "<p>".__METHOD__."($required,'$extra_label')"; _debug_array($addressbooks);
		return $addressbooks;
	}

	/**
	 * calculate the file_as string from the contact and the file_as type
	 *
	 * @param array $contact
	 * @param string $type=null file_as type, default null to read it from the contact, unknown/not set type default to the first one
	 * @param boolean $update=false If true, reads the old record for any not set fields
	 * @return string
	 */
	function fileas($contact,$type=null, $isUpdate=false)
	{
		if (is_null($type)) $type = $contact['fileas_type'];
		if (!$type) $type = $this->prefs['fileas_default'] ? $this->prefs['fileas_default'] : $this->fileas_types[0];

		if (strpos($type,'n_fn') !== false) $contact['n_fn'] = $this->fullname($contact);

		if($isUpdate)
		{
			$fileas_fields = array('n_prefix','n_given','n_middle','n_family','n_suffix','n_fn','org_name','org_unit','adr_one_locality');
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

		$fileas = str_replace(array('n_prefix','n_given','n_middle','n_family','n_suffix','n_fn','org_name','org_unit','adr_one_locality'),
			array($contact['n_prefix'],$contact['n_given'],$contact['n_middle'],$contact['n_family'],$contact['n_suffix'],
				$contact['n_fn'],$contact['org_name'],$contact['org_unit'],$contact['adr_one_locality']),$type);

		// removing empty delimiters, caused by empty contact fields
		$fileas = str_replace(array(', , : ',', : ',': , ',', , ',': : ',' ()'),array(': ',': ',': ',', ',': ',''),$fileas);
		while ($fileas[0] == ':' ||  $fileas[0] == ',') $fileas = substr($fileas,2);
		while (substr($fileas,-2) == ': ' || substr($fileas,-2) == ', ') $fileas = substr($fileas,0,-2);

		//echo "<p align=right>bocontacts::fileas(,$type)='$fileas'</p>\n";
		return $fileas;
	}

	/**
	 * determine the file_as type from the file_as string and the contact
	 *
	 * @param array $contact
	 * @param string $type=null file_as type, default null to read it from the contact, unknown/not set type default to the first one
	 * @return string
	 */
	function fileas_type($contact,$file_as=null)
	{
		if (is_null($file_as)) $file_as = $contact['n_fileas'];

		if ($file_as)
		{
			foreach($this->fileas_types as $type)
			{
				if ($this->fileas($contact,$type) == $file_as)
				{
					return $type;
				}
			}
		}
		return $this->prefs['fileas_default'] ? $this->prefs['fileas_default'] : $this->fileas_types[0];
	}

	/**
	 * get selectbox options for the fileas types with translated labels, or real content
	 *
	 * @param array $contact=null real content to use, default none
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
		);
		foreach($labels as $name => $label)
		{
			if ($contact[$name]) $labels[$name] = $contact[$name];
		}
		foreach($this->fileas_types as $fileas_type)
		{
			$options[$fileas_type] = $this->fileas($labels,$fileas_type);
		}
		return $options;
	}

	/**
	 * Set n_fileas (and n_fn) in contacts of all users  (called by Admin >> Addressbook >> Site configuration (Admin only)
	 *
	 * If $all all fileas fields will be set, if !$all only empty ones
	 *
	 * @param string $fileas_type '' or type of $this->fileas_types
	 * @param int $all=false update all contacts or only ones with empty values
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
					//echo "<p>('$old_fileas' != '{$contact['n_fileas']}' || '$old_fn' != '{$contact['n_fn']}')=".array2string($old_fileas != $contact['n_fileas'] || $old_fn != $contact['n_fn'])."</p>\n";
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
							$fields_to_update[$field_name] = $field_value = str_replace("\x0D\x0A"," ",$field_value);;
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
	 * get full name from the name-parts
	 *
	 * @param array $contact
	 * @return string full name
	 */
	function fullname($contact)
	{
		if (empty($contact['n_family']) && empty($contact['n_given'])) {
			$cpart = array('org_name');
		} else {
			$cpart = array('n_prefix','n_given','n_middle','n_family','n_suffix');
		}
		$parts = array();
		foreach($cpart as $n)
		{
			if ($contact[$n]) $parts[] = $contact[$n];
		}
		return implode(' ',$parts);
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * it gets called everytime when data is read from the db
	 * This function needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 * @param $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
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
				$data[$name] = egw_time::server2user($data[$name], $date_format);
			}
		}
		$data['photo'] = $this->photo_src($data['id'],$data['jpegphoto']);

		// set freebusy_uri for accounts
		if (!$data['freebusy_uri'] && !$data['owner'] && $data['account_id'] && !is_object($GLOBALS['egw_setup']))
		{
			if ($fb_url || @is_dir(EGW_SERVER_ROOT.'/calendar/inc'))
			{
				$fb_url = true;
				$user = isset($data['account_lid']) ? $data['account_lid'] : $GLOBALS['egw']->accounts->id2name($data['account_id']);
				$data['freebusy_uri'] = calendar_bo::freebusy_url($user);
			}
		}
		return $data;
	}

	/**
	 * src for photo: returns array with linkparams if jpeg exists or the $default image-name if not
	 * @param int $id contact_id
	 * @param boolean $jpeg=false jpeg exists or not
	 * @param string $default='' image-name to use if !$jpeg, eg. 'template'
	 * @return string/array
	 */
	function photo_src($id,$jpeg,$default='')
	{
		return $jpeg ? array(
			'menuaction' => 'addressbook.addressbook_ui.photo',
			'contact_id' => $id,
		) : $default;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches
	 * this needs to be reimplemented in the derived class
	 *
	 * @param array $data
	 * @param $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
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
				$data[$name] = egw_time::server2user($data[$name], $date_format);
			}
		}
		return $data;
	}

	/**
	* deletes contact in db
	*
	* @param mixed &$contact contact array with key id or (array of) id(s)
	* @param boolean $deny_account_delete=true if true never allow to delete accounts
	* @param int $check_etag=null
	* @return boolean|int true on success or false on failiure, 0 if etag does not match
	*/
	function delete($contact,$deny_account_delete=true,$check_etag=null)
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
			if ($this->check_perms(EGW_ACL_DELETE,$c,$deny_account_delete))
			{
				if (!($old = $this->read($id))) return false;
				if ($this->delete_history != '' && $old['tid'] != addressbook_so::DELETED_TYPE)
				{
					$delete = $old;
					$delete['tid'] = addressbook_so::DELETED_TYPE;
					$ok = $this->save($delete);
					egw_link::unlink(0,'addressbook',$id,'','','',true);
				}
				elseif (($ok = parent::delete($id,$check_etag)))
				{
					egw_link::unlink(0,'addressbook',$id);
				}

				// Don't notify of final purge
				if ($ok && $old['tid'] != addressbook_so::DELETED_TYPE)
				{
					$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $id, 'delete', time());
					$this->tracking->track(array('id' => $id), array('id' => $id), null, true);
				}
			}
			else
			{
				return $ok;
			}
		}
		return true;
	}

	/**
	* saves contact to db
	*
	* @param array &$contact contact array from etemplate::exec
	* @param boolean $ignore_acl=false should the acl be checked or not
	* @return int/string/boolean id on success, false on failure, the error-message is in $this->error
	*/
	function save(&$contact,$ignore_acl=false)
	{
		// remember if we add or update a entry
		if (($isUpdate = $contact['id']))
		{
			if (!isset($contact['owner']) || !isset($contact['private']))	// owner/private not set on update, eg. SyncML
			{
				if (($old = $this->read($contact['id'])))	// --> try reading the old entry and set it from there
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
				}
			}
		}
		else
		{
			// if no owner/addressbook set use the setting of the add_default prefs (if set, otherwise the users personal addressbook)
			if (!isset($contact['owner'])) $contact['owner'] = $this->default_addressbook;
			if (!isset($contact['private'])) $contact['private'] = (int)$this->default_private;
			// allow admins to import contacts with creator / created date set
			if (!$contact['creator'] || !$this->is_admin($contact)) $contact['creator'] = $this->user;
			if (!$contact['created'] || !$this->is_admin($contact)) $contact['created'] = $this->now_su;

			if (!$contact['tid']) $contact['tid'] = 'n';
		}
		// ensure accounts and group addressbooks are never private!
		if ($contact['owner'] <= 0)
		{
			$contact['private'] = 0;
		}
		if(!$ignore_acl && !$this->check_perms($isUpdate ? EGW_ACL_EDIT : EGW_ACL_ADD,$contact))
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
		if (is_array($contact['cat_id']))
		{
			$contact['cat_id'] = implode(',',$contact['cat_id']);
		}

		// Update country codes
		foreach(array('adr_one_', 'adr_two_') as $c_prefix) {
			if($contact[$c_prefix.'countryname'] && !$contact[$c_prefix.'countrycode'] &&
				$code = $GLOBALS['egw']->country->country_code($contact[$c_prefix.'countryname']))
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
			if($contact[$c_prefix.'countrycode'] != null)
			{
				$contact[$c_prefix.'countryname'] = null;
			}
		}

		// last modified
		$contact['modifier'] = $this->user;
		$contact['modified'] = $this->now_su;
		// set full name and fileas from the content
		if (!isset($contact['n_fn']))
		{
			$contact['n_fn'] = $this->fullname($contact);
		}
		if (isset($contact['org_name'])) $contact['n_fileas'] = $this->fileas($contact, null, false);

		$to_write = $contact;
		// (non-admin) user editing his own account, make sure he does not change fields he is not allowed to (eg. via SyncML or xmlrpc)
		if (!$ignore_acl && !$contact['owner'] && !$this->is_admin($contact))
		{
			foreach($contact as $field => $value)
			{
				if (!in_array($field,$this->own_account_acl) && !in_array($field,array('id','owner','account_id','modified','modifier')))
				{
					unset($to_write[$field]);	// user is now allowed to change that
				}
			}
		}

		// Get old record for tracking changes
		if (!isset($old) && $isUpdate)
		{
			$old = $this->read($contact['id']);
		}
		// IF THE OLD ENTRY IS A ACCOUNT, dont allow to change the owner/location
		// maybe we need that for id and account_id as well.
		if (is_array($old) && (!isset($old['owner']) || empty($old['owner'])))
		{
			if (isset($to_write['owner']) && !empty($to_write['owner']))
			{
				error_log(__METHOD__.__LINE__." Trying to change account to owner:". $to_write['owner'].' Account affected:'.array2string($old).' Data send:'.array2string($to_write));
				unset($to_write['owner']);
			}
		}
		// we dont update the content-history, if we run inside setup (admin-account-creation)
		if(!($this->error = parent::save($to_write)) && is_object($GLOBALS['egw']->contenthistory))
		{
			$contact['id'] = $to_write['id'];
			$contact['uid'] = $to_write['uid'];
			$contact['etag'] = $to_write['etag'];
			$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $contact['id'],$isUpdate ? 'modify' : 'add', time());

			if ($contact['account_id'])	// invalidate the cache of the accounts class
			{
				$GLOBALS['egw']->accounts->cache_invalidate($contact['account_id']);
			}
			// notify interested apps about changes in the account-contact data
			if (!$to_write['owner'] && $to_write['account_id'])
			{
				$to_write['location'] = 'editaccountcontact';
				$GLOBALS['egw']->hooks->process($to_write,False,True);	// called for every app now, not only enabled ones));
			}
			// Notify linked apps about changes in the contact data
			egw_link::notify_update('addressbook',  $contact['id'], $contact);

			// Check for restore of deleted contact, restore held links
			if($old && $old['tid'] == addressbook_so::DELETED_TYPE && $contact['tid'] != addressbook_so::DELETED_TYPE)
			{
				egw_link::restore('addressbook', $contact['id']);
			}

			// Record change history for sql - doesn't work for LDAP accounts
			if(!$contact['account_id'] || $contact['account_id'] && $this->account_repository == 'sql') {
				$deleted = ($old['tid'] == addressbook_so::DELETED_TYPE || $contact['tid'] == addressbook_so::DELETED_TYPE);
				$this->tracking->track($to_write, $old ? $old : null, null, $deleted);
			}
		}

		return $this->error ? false : $contact['id'];
	}

	/**
	 * Resizes photo to 60*80 pixel and returns it
	 *
	 * @param string|FILE $photo string with image or open filedescribtor
	 * @param int $dst_w=60 max width to resize to
	 * @return string with resized jpeg photo, null on error
	 */
	public static function resize_photo($photo,$dst_w=60)
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
			imagejpeg($resized,'',90);
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
	* @return array|boolean array with contact data, null if not found or false on no view perms
	*/
	function read($contact_id)
	{
		if (!($data = parent::read($contact_id)))
		{
			return null;	// not found
		}
		if (!$this->check_perms(EGW_ACL_READ,$data))
		{
			return false;	// no view perms
		}
		// determine the file-as type
		$data['fileas_type'] = $this->fileas_type($data);

		// Update country name from code
		if($data['adr_one_countrycode'] != null) {
			$data['adr_one_countryname'] = $GLOBALS['egw']->country->get_full_name($data['adr_one_countrycode'], true);
		}
		if($data['adr_two_countrycode'] != null) {
			$data['adr_two_countryname'] = $GLOBALS['egw']->country->get_full_name($data['adr_two_countrycode'], true);
		}

		return $data;
	}

	/**
	 * Checks if the current user has the necessary ACL rights
	 *
	 * If the access of a contact is set to private, one need a private grant for a personal addressbook
	 * or the group membership for a group-addressbook
	 *
	 * @param int $needed necessary ACL right: EGW_ACL_{READ|EDIT|DELETE}
	 * @param mixed $contact contact as array or the contact-id
	 * @param boolean $deny_account_delete=false if true never allow to delete accounts
	 * @param int $user=null for which user to check, default current user
	 * @return boolean true permission granted, false for permission denied, null for contact does not exist
	 */
	function check_perms($needed,$contact,$deny_account_delete=false,$user=null)
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

		// allow the user to edit his own account
		if (!$owner && $needed == EGW_ACL_EDIT && $contact['account_id'] == $user && $this->own_account_acl)
		{
			$access = true;
		}
		// dont allow to delete own account (as admin handels it too)
		elseif (!$owner && $needed == EGW_ACL_DELETE && ($deny_account_delete || $contact['account_id'] == $user))
		{
			$access = false;
		}
		// for reading accounts (owner == 0) and account_selection == groupmembers, check if current user and contact are groupmembers
		elseif ($owner == 0 && $needed == EGW_ACL_READ &&
			$GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'groupmembers' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$access = !!array_intersect($memberships,$GLOBALS['egw']->accounts->memberships($contact['account_id'],true));
		}
		else
		{
			$access = ($grants[$owner] & $needed) &&
				(!$contact['private'] || ($grants[$owner] & EGW_ACL_PRIVATE) || in_array($owner,$memberships));
		}
		//error_log(__METHOD__."($needed,$contact[id],$deny_account_delete,$user) returning ".array2string($access));
		return $access;
	}

	/**
	 * Check access to the file store
	 *
	 * @param int|array $id id of entry or entry array
	 * @param int $check EGW_ACL_READ for read and EGW_ACL_EDIT for write or delete access
	 * @param string $rel_path=null currently not used in InfoLog
	 * @param int $user=null for which user to check, default current user
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path=null,$user=null)
	{
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
			list($value,$num) = each($values);
			//echo "<p>$name: '$value' $num/".count($contacts)."=".($num / (double) count($contacts))." >= $this->org_common_factor = ".($num / (double) count($contacts) >= $this->org_common_factor ? 'true' : 'false')."</p>\n";
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
				$criteria[$name] = $fields[$name];
			}
		}
		return parent::search($criteria,false,'n_family,n_given','','',false,'OR',false,array('org_name'=>$org_name));
	}

	/**
	 * Return the changed fields from two versions of a contact (not modified or modifier)
	 *
	 * @param array $from original/old version of the contact
	 * @param array $to changed/new version of the contact
	 * @param boolean $onld_org_fields=true check and return only org_fields, default true
	 * @return array with field-name => value from $from
	 */
	function changed_fields($from,$to,$only_org_fields=true)
	{
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
	 * @param array $members=null org-members to change, default null --> function queries them itself
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
				foreach($this->customfields as $name => $data)
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
					//echo "<p>$member[n_family], $member[n_given]: $name='{$to[$name]}'</p>\n";
					++$fields;
				}
			}
			if ($fields)
			{
				if (!$this->check_perms(EGW_ACL_EDIT,$member) || !$this->save($member))
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
	 * @param int/string/array $contact int/string id or array with contact
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
		return $this->fileas($contact,$type);
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
		if (($contacts =& $this->search(array('contact_id' => $ids),false)))
		{
			foreach($contacts as $contact)
			{
				$titles[$contact['id']] = $this->link_title($contact);
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
		$filter = $result = $criteria = array();
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
		if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) $filter['account_id'] = null;
		if (($contacts =& parent::search($criteria,false,'org_name,n_family,n_given,cat_id,contact_email','','%',false,'OR', $limit, $filter)))
		{
			foreach($contacts as $contact)
			{
				$result[$contact['id']] = $this->link_title($contact).
					($options['type'] === 'email' ? ' <'.$contact['email'].'>' : '');
				// show category color
				if ($contact['cat_id'] && ($color = etemplate::cats2color($contact['cat_id'])))
				{
					$result[$contact['id']] = array(
						'label' => $result[$contact['id']],
						'style.backgroundColor' => $color,
					);
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
		$options['filter'][] = "contact_email LIKE '%@%'";

		// let link query know, to append email to list
		$options['type'] = 'email';

		return $this->link_query($pattern,$options);
	}

	/**
	 * returns info about contacts for calender
	 *
	 * @param int/array $ids single contact-id or array of id's
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
				'rights' => EGW_ACL_READ_FOR_PARTICIPANTS,
				'name' => $this->link_title($contact),
				'cn' => trim($contact['n_given'].' '.$contact['n_family']),
			);
		}
		//echo "<p>calendar_info(".print_r($ids,true).")="; _debug_array($data);
		return $data;
	}

	/**
	 * Read the next and last event of given contacts
	 *
	 * @param array $ids contact_id's
	 * @param boolean $extra_title=true if true, use a short date only title and put the full title as extra_title (tooltip)
	 * @return array
	 */
	function read_calendar($ids,$extra_title=true)
	{
		if (!$GLOBALS['egw_info']['user']['apps']['calendar']) return array();

		$uids = array();
		foreach($ids as $id)
		{
			if (is_numeric($id)) $uids[] = 'c'.$id;
		}
		if (!$uids) return array();

		$bocal = new calendar_bo();
		$events = $bocal->search(array(
			'users' => $uids,
			'enum_recuring' => true,
		));
		if (!$events) return array();

		//_debug_array($events);
		$calendars = array();
		foreach($events as $event)
		{
			foreach($event['participants'] as $uid => $status)
			{
				if ($uid[0] != 'c' || ($status == 'R' && !$GLOBALS['egw_info']['user']['preferences']['calendar']['show_rejected']))
				{
					continue;
				}
				$id = (int)substr($uid,1);

				if ($event['start'] < $this->now_su)	// past event --> check for last event
				{
					if (!isset($calendars[$id]['last_event']) || $event['start'] > $calendars[$id]['last_event'])
					{
						$calendars[$id]['last_event'] = $event['start'];
						$link = array(
							'id' => $event['id'],
							'app' => 'calendar',
							'title' => $bocal->link_title($event),
							'extra_args' => array(
								'date' => date('Ymd',$event['start']),
							),
						);
						if ($extra_title)
						{
							$link['extra_title'] = $link['title'];
							$link['title'] = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],$event['start']);
						}
						$calendars[$id]['last_link'] = $link;
					}
				}
				else	// future event --> check for next event
				{
					if (!isset($calendars[$id]['next_event']) || $event['start'] < $calendars[$id]['next_event'])
					{
						$calendars[$id]['next_event'] = $event['start'];
						$link = array(
							'id' => $event['id'],
							'app' => 'calendar',
							'title' => $bocal->link_title($event),
							'extra_args' => array(
								'date' => date('Ymd',$event['start']),
							),
						);
						if ($extra_title)
						{
							$link['extra_title'] = $link['title'];
							$link['title'] = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],$event['start']);
						}
						$calendars[$id]['next_link'] = $link;
					}
				}
			}
		}
		return $calendars;
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
			$cats = new categories('', 'addressbook');
			$cat_ids = $cats->return_all_children($data['cat_id']);
		}
		else
		{
			$cat_ids = array($data['cat_id']);
		}

		// Get addresses that use the category
		@set_time_limit( 0 );
		$ids = array();
		foreach($cat_ids as $cat_id)
		{
			$ids = $this->search(array('cat_id' => $cat_id), false);
			foreach($ids as &$info)
			{
				$info['cat_id'] = implode(',',array_diff(explode(',',$info['cat_id']), $cat_ids));
				$this->save($info);
			}
		}
	}

	/**
	 * Called by edit-account hook, when an account get edited --> not longer used
	 *
	 * This function is still there, to not give a fatal error, if the hook still exists.
	 * Can be removed after the next db-update, which also reloads the hooks. RalfBecker 2006/09/18
	 *
	 * @param array $data
	 */
	function editaccount($data)
	{
		// just force a new registration of the addressbook hooks
		include(EGW_INCLUDE_ROOT.'/addressbook/setup/setup.inc.php');
		$GLOBALS['egw']->hooks->register_hooks('addressbook',$setup_info['addressbook']['hooks']);
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
		$custom_fields = config::get_customfields('addressbook', true);
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
		if (!$this->check_perms(EGW_ACL_EDIT,$target))
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
		}
		if (!$this->save($target)) return 0;

		$success = 1;
		foreach($contacts as $contact)
		{
			if (!$this->check_perms(EGW_ACL_DELETE,$contact))
			{
				continue;
			}
			foreach(egw_link::get_links('addressbook',$contact['id']) as $data)
			{
				//_debug_array(array('function'=>__METHOD__,'line'=>__LINE__,'app'=>'addressbook','id'=>$contact['id'],'data:'=>$data,'target'=>$target['id']));
				// info_from and info_link_id (main link)
				$newlinkID = egw_link::link('addressbook',$target['id'],$data['app'],$data['id'],$data['remark'],$target['owner']);
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
			if ($this->delete($contact['id'])) $success++;
		}
		return $success;
	}

	/**
	 * Check if user has required rights for a list or list-owner
	 *
	 * @param int $list
	 * @param int $required
	 * @param int $owner=null
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
	 * @param array $contacts=array() contacts to add (only for not yet existing lists!)
	 * @param array &$data=array() values for keys 'list_uid', 'list_carddav_name', 'list_name'
	 * @return int|boolean integer list_id or false on error
	 */
	function add_list($keys,$owner,$contacts=array(),array &$data=array())
	{
		if (!$this->check_list(null,EGW_ACL_ADD|EGW_ACL_EDIT,$owner)) return false;

		return parent::add_list($keys,$owner,$contacts,$data);
	}

	/**
	 * Adds contacts to a distribution list
	 *
	 * @param int|array $contact contact_id(s)
	 * @param int $list list-id
	 * @param array $existing=null array of existing contact-id(s) of list, to not reread it, eg. array()
	 * @return false on error
	 */
	function add2list($contact,$list,array $existing=null)
	{
		if (!$this->check_list($list,EGW_ACL_EDIT)) return false;

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
		if ($list && !$this->check_list($list,EGW_ACL_EDIT)) return false;

		return parent::remove_from_list($contact,$list);
	}

	/**
	 * Deletes a distribution list (incl. it's members)
	 *
	 * @param int/array $list list_id(s)
	 * @return number of members deleted or false if list does not exist
	 */
	function delete_list($list)
	{
		if (!$this->check_list($list,EGW_ACL_DELETE)) return false;

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
		static $cache;

		if (isset($cache[$list])) return $cache[$list];

		return $cache[$list] = parent::read_list($list);
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
		$code = $GLOBALS['egw']->country->country_code($country);

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
		//echo "<p>bocontacts::addr_format_by_country('$country'='$code') = '$adr_format'</p>\n";
		return $adr_format;
	}

	/**
	 * Find existing categories in database by name or add categories that do not exist yet
	 * currently used for vcard import
	 *
	 * @param array $catname_list names of the categories which should be found or added
	 * @param int $contact_id=null match against existing contact and expand the returned category ids
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
					if (!$this->categories->check_perms(EGW_ACL_READ, $cat_id))
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
			$this->categories = new categories($this->user,'addressbook');
		}

		if (!is_array($cat_id_list))
		{
			$cat_id_list = explode(',',$cat_id_list);
		}
		$cat_list = array();
		foreach($cat_id_list as $cat_id)
		{
			if ($cat_id && $this->categories->check_perms(EGW_ACL_READ, $cat_id) &&
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
	 * @param boolean $relax=false if asked to relax, we only match against some key fields
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
		$addr_one_fields = array('adr_one_street',
					 'adr_one_locality', 'adr_one_region',
					 'adr_one_postalcode', 'adr_one_countryname');
		$addr_two_fields = array('adr_two_street',
					 'adr_two_locality', 'adr_two_region',
					 'adr_two_postalcode', 'adr_two_countryname');

		if (!empty($contact['owner']))
		{
			$columns_to_search += array('owner');
		}

		$result = false;

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
	 * @param int|array $owner=null 0=accounts, null=all addressbooks or integer account_id of user or group
	 * @return string
	 */
	public function get_ctag($owner=null)
	{
		$filter = array('tid' => null);	// tid=null --> use all entries incl. deleted (tid='D')
		// show addressbook of a single user?
		if (!is_null($owner)) $filter['contact_owner'] = $owner;

		// should we hide the accounts addressbook
		if (!$owner && $GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'])
		{
			$filter['account_id'] = null;
		}
		$result = $this->search(array(),'MAX(contact_modified) AS contact_modified','','','',false,'AND',false,$filter);

		if (!$result || !isset($result[0]['contact_modified']))
		{
			$ctag = 'empty';	// ctag for empty addressbook
		}
		else
		{
			$ctag = $result[0]['contact_modified'];
		}
		//error_log(__METHOD__.'('.array2string($owner).') returning '.array2string($ctag));
		return $ctag;
	}
}
