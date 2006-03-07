<?php
/**************************************************************************\
* eGroupWare - Adressbook - General business object                        *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Cornelius_weiss <egw@von-und-zu-weiss.de>        *
* --------------------------------------------                             *
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
* @copyright (c) 2005 by Cornelius Weiss <egw@von-und-zu-weiss.de>
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
*/
class bocontacts extends socontacts
{
	/**
	* @var $grants array with grants
	*/
	var $grants;
	
	/**
	* @var $user userid of current user
	*/
	var $user;
	
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
	var $timestamps = array('last_mod');
	
	function bocontacts($contact_app='addressbook')
	{
		$this->socontacts($contact_app);
		$this->grants = $GLOBALS['egw']->acl->get_grants($contact_app);
		$this->user = $GLOBALS['egw_info']['user']['account_id'];
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
		return $data;
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
	* @param mixed &contact contact array from etemplate::exec or id
	* @return bool false if all went right
	*/
	function delete(&$contact)
	{
		// multiple delete from advanced search
		if(isset($contact[0]))
		{
			foreach($contact as $single)
			{
				if($this->check_perms(EGW_ACL_DELETE,$single['id']))
				{
					if(parent::delete($single))
					{
						$msg .= lang('Something went wrong by deleting %1', $single['n_given'].$single['n_family']);
					}
					else
					{
						$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $single['id'], 'delete', time());
					}
				}
				else
				{
					$msg .= lang('You are not permitted to delete contact %1', $single['n_given'].$single['n_family']);
				}
			}
			return $msg;
		}
		if((isset($contact['id']) && !$this->check_perms(EGW_ACL_DELETE,$contact['id'])) ||
			(is_numeric($contact) && !$this->check_perms(EGW_ACL_DELETE,$contact)))
		{
			$contact['msg'] = lang('You are not permittet to delete this contact');
			return 1;
		}
		if(is_numeric($contact)) $contact = array('id' => $contact);
		if(parent::delete($contact))
		{
			$contact['msg'] = lang('Something went wrong by deleting this contact');
			return 1;
		}
		$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $contact['id'], 'delete', time());

		return;
	}
	
	/**
	* saves contact to db
	*
	* @param array &contact contact array from etemplate::exec
	* @return array $contact
	* TODO make fullname format choosable at best with selectbox and javascript in edit dialoge
	*/
	function save(&$contact)
	{
		// stores if we add or update a entry
		$isUpdate = true;
		
		if($contact['id'] && !$this->check_perms(EGW_ACL_EDIT,$contact['id']))
		{
			$contact['msg'] = lang('You are not permittet to edit this contact');
			return $contact;
		}
		
		// new contact
		if($contact['id'] == 0)
		{
			$contact['owner'] = $this->user;
			// we create a normal contact
			$contact['tid'] = 'n';
			
			$isUpdate = false;
		}
		
		// convert categories
		$contact['cat_id'] = $contact['cat_id'] ? implode(',',$contact['cat_id']) : '';
		// last modified
		$contact['last_mod'] = $this->now_su;
		// only owner can set access status
		$contact['access'] = $contact['owner'] == $this->user ? ($contact['private'] ? 'private': 'public') : $contact['access'];
		// create fullname
		$contact['fn'] = $contact['prefix'].
			($contact['n_given'] ? ' '.$contact['n_given'] : '').
			($contact['n_middle'] ? ' '.$contact['n_middle'] : '').
			($contact['n_family'] ? ' '.$contact['n_family'] : '').
			($contact['n_suffix'] ? ' '.$contact['n_suffix'] : '');
		// for some bad historical reasons we mainfileds saved in cf :-(((
		$contact['#ophone'] = $contact['ophone']; unset($contact['ophone']);
		$contact['#address2'] = $contact['address2']; unset($contact['address2']);
		$contact['#address3'] = $contact['address3']; unset($contact['address3']);
	
		$error_nr = parent::save($contact);

		if(!$error_nr)
		{
			if($isUpdate) {
				$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $contact['id'], 'modify', time());
			} else {
				$GLOBALS['egw']->contenthistory->updateTimeStamp('contacts', $contact['id'], 'add', time());
			}
		}
		
		// for some bad historical reasons we mainfileds saved in cf :-(((
		$contact['ophone'] = $contact['#ophone']; unset($contact['#ophone']);
		$contact['address2'] = $contact['#address2']; unset($contact['#address2']);
		$contact['address3'] = $contact['#address3']; unset($contact['#address3']);
		
		$contact['msg'] = $error_nr ?
			lang('Something went wrong by saving this contact. Errorcode %1',$error_nr) :
			lang('Contact saved');
		
		return $contact;
	}
	
	/**
	* reads contacts matched by key and puts all cols in the data array
	*
	* @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	* @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	* @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	* @return array with data or errormessage
	*/
	function read($keys,$extra_cols='',$join='')
	{
		$data = parent::read($keys,$extra_cols,$join);
		if (!$data)
		{
			return $content['msg'] = lang('something went wrong by reading this contact');
		}
		if(!$this->check_perms(EGW_ACL_READ,$data))
		{
			return $content['msg'] = lang('you are not permittet to view this contact');
		}
		
		// convert access into private for historical reasons
		$data['private'] = $data['access'] == 'private' ? 1 : 0;
		// for some bad historical reasons we mainfileds saved in cf :-(((
		$data['ophone'] = $data['#ophone']; unset($data['#ophone']);
		$data['address2'] = $data['#address2']; unset($data['#address2']);
		$data['address3'] = $data['#address3']; unset($data['#address3']);
		
		return $data;
	}
	/**
	* searches contacts for rows matching searchcriteria
	*
	* @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	* @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	* @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	* @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	* @param string $wildcard='' appended befor and after each criteria
	* @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	* @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	* @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	* @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	* @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	*	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	* @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	* @return array of matching rows (the row is an array of the cols) or False
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		$filter = array(
			'owner' => array_keys($this->grants),
		);
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}
	
	/**
	* Checks if the current user has the necessary ACL rights
	*
	* @param int $needed necessary ACL right: EGW_ACL_{READ|EDIT|DELETE}
	* @param mixed $contact contact as array or the contact-id
	* @return boolean true permission granted or false for permission denied
	*/
	function check_perms($needed,&$contact)
	{
		if (is_array($contact))
		{
			$owner = $contact['owner'];
			$access = $contact['access'];
		}
		elseif (is_numeric($contact))
		{
			$read_contact = parent::read($contact);
			$owner = $read_contact['owner'];
			$access = $read_contact['access'];
		}
		if($owner == $this->user)
		{
			return true;
		}
		$access = $access ? $access : 'public';
		return $access == 'private' ? false : $this->grants[$owner] & $needed;
	}
}
