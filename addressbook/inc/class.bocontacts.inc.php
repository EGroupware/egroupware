<?php
/**************************************************************************\
* eGroupWare - Adressbook - General business object                        *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Cornelius_weiss <egw@von-und-zu-weiss.de>        *
* and Ralf Becker <RalfBecker-AT-outdoor-training.de>                      *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.socontacts.inc.php');

/**
 * General business object of the adressbook
 *
 * @package addressbook
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005/6 by Cornelius Weiss <egw@von-und-zu-weiss.de> and Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class bocontacts extends socontacts
{
	/**
	 * @var int $tz_offset_s offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 */
	var $tz_offset_s;

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
		'n_family, n_given: org_name',
		'n_family, n_prefix: org_name',
		'n_given n_family: org_name',
		'n_prefix n_family: org_name',
		'n_fn: org_name',
		'org_name',
		'n_given n_family',
		'n_prefix n_family',
		'n_family, n_given',
		'n_family, n_prefix',
		'n_fn',
	);

	function bocontacts($contact_app='addressbook')
	{
		$this->socontacts($contact_app);
		
		$this->tz_offset_s = 3600 * $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
		$this->now_su = time() + $this->tz_offset_s;

/*		foreach(array(
			'so'    => $appname. 'soadb',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['egw']->$class))
			{
				$GLOBALS['egw']->$class =& CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['egw']->$class;
		}*/
		
	}

	/**
	 * calculate the file_as string from the contact and the file_as type
	 *
	 * @param array $contact
	 * @param string $type=null file_as type, default null to read it from the contact, unknown/not set type default to the first one
	 * @return string
	 */
	function fileas($contact,$type=null)
	{
		if (is_null($type)) $type = $contact['fileas_type'];
		if (!$type || !in_array($type,$this->fileas_types)) $type = $this->fileas_types[0];
		
		if (strstr($type,'n_fn')) $contact['n_fn'] = $this->fullname($contact);
		
		return str_replace(array_keys($contact),array_values($contact),$type);
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
		return $this->fileas_types[0];
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
	 * get full name from the name-parts
	 *
	 * @param array $contact
	 * @return string full name
	 */
	function fullname($contact)
	{
		$parts = array();
		foreach(array('n_prefix','n_given','n_middle','n_family','n_suffix') as $n)
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
	 */
	function db2data($data)
	{
		// convert timestamps from server-time in the db to user-time
		foreach($this->timestamps as $name)
		{
			if(isset($data[$name]))
			{
				$data[$name] += $this->tz_offset_s;
			}
		}
		$data['photo'] = $this->photo_src($data['id'],$data['jpegphoto']);

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
			'menuaction' => 'addressbook.uicontacts.photo',
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
	 */
	function data2db($data)
	{
		// convert timestamps from user-time to server-time in the db
		foreach($this->timestamps as $name)
		{
			if(isset($data[$name]))
			{
				$data[$name] -= $this->tz_offset_s;
			}
		}
		return $data;
	}

	/**
	* deletes contact in db
	*
	* @param mixed &$contact contact array with key id or (array of) id(s)
	* @return boolean true on success or false on failiure
	*/
	function delete($contact)
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

			if ($this->check_perms(EGW_ACL_DELETE,$c) && parent::delete($id))
			{
				$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $id, 'delete', time());
			}
			else
			{
				return false;
			}
		}
		return true;
	}
	
	/**
	* saves contact to db
	*
	* @param array &$contact contact array from etemplate::exec
	* @return boolean true on success, false on failure, an error-message is in $contact['msg']
	*/
	function save(&$contact)
	{
		// stores if we add or update a entry
		if (!($isUpdate = $contact['id']))
		{
			if (!isset($contact['owner'])) $contact['owner'] = $this->user;	// write to users personal addressbook
			$contact['creator'] = $this->user;
			$contact['created'] = $this->now_su;
		}
		if($contact['id'] && !$this->check_perms(EGW_ACL_EDIT,$contact))
		{
			return false;
		}
		// convert categories
		$contact['cat_id'] = is_array($contact['cat_id']) ? implode(',',$contact['cat_id']) : $contact['cat_id'];
		// last modified
		$contact['modifier'] = $this->user;
		$contact['modified'] = $this->now_su;
		// set access if not set or bogus
		if ($contact['access'] != 'private') $contact['access'] = 'public';
		// set full name and fileas from the content
		$contact['n_fn'] = $this->fullname($contact);
		$contact['n_fileas'] = $this->fileas($contact);

		if(!($error_nr = parent::save($contact)))
		{
			$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $contact['id'],$isUpdate ? 'modify' : 'add', time());
		}
		return !$error_nr;
	}
	
	/**
	* reads contacts matched by key and puts all cols in the data array
	*
	* @param int/string $contact_id 
	* @return array/boolean contact data or false on error
	*/
	function read($contact_id)
	{
		$data = parent::read($contact_id);
		if (!$data || !$this->check_perms(EGW_ACL_READ,$data))
		{
			return false;
		}
		// determine the file-as type
		$data['fileas_type'] = $this->fileas_type($data);
		
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
	* @return boolean true permission granted or false for permission denied
	*/
	function check_perms($needed,&$contact)
	{
		if (!is_array($contact) && !($contact = parent::read($contact)))
		{
			return false;
		}
		$owner = $contact['owner'];
		
		if (!$owner && $needed == EGW_ACL_EDIT && $contact['account_id'] == $this->user)
		{
			return true;
		}
		return ($this->grants[$owner] & $needed) && 
			(!$contact['private'] || ($this->grants[$owner] & EGW_ACL_PRIVATE) || in_array($owner,$this->memberships));
	}

	/**
	 * get title for a contact identified by $contact
	 * 
	 * Is called as hook to participate in the linking
	 *
	 * @param int/string/array $contact int/string id or array with contact
	 * @param string the title
	 */
	function link_title($contact)
	{
		if (!is_array($contact) && $contact)
		{
			$contact = $this->read($contact);
		}
		if (!is_array($contact))
		{
			return False;
		}
		return $contact['n_fileas'];
	}

	/**
	 * query addressbook for contacts matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @return array with id - title pairs of the matching entries
	 */
	function link_query($pattern)
	{
		$result = $criteria = array();
		if ($pattern)
		{
			foreach($this->columns_to_search as $col)
			{
				$criteria[$col] = $pattern;
			}
		}
		foreach((array) $this->regular_search($criteria,false,'org_name,n_family,n_given','','%',false,'OR') as $contact)
		{
			$result[$contact['id']] = $this->link_title($contact);
		}
		return $result;
	}
	
	/**
	 * Hook called by link-class to include calendar in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	function search_link($location)
	{
		return array(
			'query' => 'addressbook.bocontacts.link_query',
			'title' => 'addressbook.bocontacts.link_title',
			'view' => array(
				'menuaction' => 'addressbook.uicontacts.view'
			),
			'view_id' => 'contact_id',
		);
	}

	/**
	 * Delete contact linked to account, called by delete-account hook, when an account get deleted
	 *
	 * @param array $data
	 */
	function deleteaccount($data)
	{
		// delete/move personal addressbook
		parent::deleteaccount($data);
		
		// delete contact linked to account
		if (($contact_id = $GLOBALS['egw']->accounts->id2name($data['account_id'],'person_id')))
		{
			$this->delete($contact_id);
		}
	}

	/**
	 * Update contact if linked account get updated, called by edit-account hook, when an account get edited
	 *
	 * @param array $data
	 */
	function editaccount($data)
	{
		//echo "bocontacts::editaccount()"; _debug_array($data);
		
		// check if account is linked to a contact
		if (($contact_id = $GLOBALS['egw']->accounts->id2name($data['account_id'],'person_id')) &&
			($contact = $this->read($contact_id)))
		{
			$need_update = false;
			foreach(array(
				'n_family' => 'lastname',
				'n_given'  => 'firstname',
				'email'    => 'email',
			) as $cname => $aname)
			{
				if ($contact[$cname] != $data[$aname]) $need_update = true;

				$contact[$cname] = $data[$aname];
			}
			if ($need_update)
			{
				$this->save($contact);
			}
		}
	}
}
