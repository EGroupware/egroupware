<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@mail.com>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class soaddressbook
	{
		var $contacts;
		var $rights;
		var $grants;
		var $owner;

		function soaddressbook()
		{
			global $phpgw,$phpgw_info,$owner;

			if(!isset($owner)) { $owner = 0; } 

			$grants = $phpgw->acl->get_grants('addressbook');
			if(!isset($owner) || !$owner)
			{
				$owner = $phpgw_info['user']['account_id'];
				$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
			}
			else
			{
				if($grants[$owner])
				{
					$rights = $grants[$owner];
					if (!($rights & PHPGW_ACL_READ))
					{
						$owner = $phpgw_info['user']['account_id'];
						$rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
					}
				}
			}
			$this->rights   = $rights;
			$this->grants   = $grants;
			$this->owner    = $owner;
			$this->contacts = CreateObject('phpgwapi.contacts');
		}

		function read_entries($start,$offset,$qcols,$query,$qfilter,$sort,$order)
		{
			$readrights = $this->rights & PHPGW_ACL_READ;
			return $this->contacts->read($start,$offset,$qcols,$query,$qfilter,$sort,$order,$readrights);
		}

		function read_entry($id,$fields)
		{
			if ($this->rights & PHPGW_ACL_READ)
			{
				return $this->contacts->read_single_entry($id,$fields);
			}
			else
			{
				$rtrn = array(0 => array('No access' => 'No access'));
				return $rtrn;
			}
		}

		function read_last_entry($fields)
		{
			if ($this->rights & PHPGW_ACL_READ)
			{
				return $this->contacts->read_last_entry($fields);
			}
			else
			{
				$rtrn = array(0 => array('No access' => 'No access'));
				return $rtrn;
			}
		}

		function add_entry($fields)
		{
			$fields['tid'] = trim($fields['tid']);
			if(empty($fields['tid']))
			{
				$fields['tid'] = 'n';
			}
			if ($this->rights & PHPGW_ACL_ADD)
			{
				$id = $this->contacts->add($fields['owner'],$fields,$fields['access'],$fields['cat_id'],$fields['tid']);
			}
			return $id;
		}

		function get_lastid()
		{
		 	$entry = $this->contacts->read_last_entry();
			$id = $entry[0]['id'];
			return $id;
		}

		function update_entry($fields)
		{
			if ($this->rights & PHPGW_ACL_EDIT)
			{
				$this->contacts->update($fields['ab_id'],$fields['owner'],$fields,$fields['access'],$fields['cat_id']);
			}
			return;
		}

		function delete_entry($data)
		{
			if ($this->rights & PHPGW_ACL_DELETE)
			{
				$this->contacts->delete($data['id']);
			}
			return;
		}
	}
?>
