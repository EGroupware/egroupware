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
			if (!is_object($GLOBALS['phpgw']->contacts))
			{
				$GLOBALS['phpgw']->contacts = CreateObject('phpgwapi.contacts');
			}
			$this->contacts = &$GLOBALS['phpgw']->contacts;
			$this->grants = &$this->contacts->grants;

			/* _debug_array($GLOBALS['phpgw_info']); */
			/* _debug_array($grants); */
		}

		function read_entries($data)
		{
			return $this->contacts->read(
				$data['start'],
				$data['limit'],
				$data['fields'],
				$data['query'],
				$data['filter'],
				$data['sort'],
				$data['order']
			);
		}

		function read_entry($id,$fields)
		{
			return $this->contacts->read_single_entry($id,$fields);
		}

		function read_last_entry($fields)
		{
			return $this->contacts->read_last_entry($fields);
		}

		function add_entry($fields)
		{
			$owner  = $fields['owner'];
			$access = $fields['access'];
			$cat_id = $fields['cat_id'];
			$tid    = $fields['tid'];
			unset($fields['owner']);
			unset($fields['access']);
			unset($fields['cat_id']);
			unset($fields['ab_id']);
			unset($fields['tid']);

			return $this->contacts->add($owner,$fields,$access,$cat_id,$tid);
		}

		function get_lastid()
		{
		 	$entry = $this->contacts->read_last_entry();
			return $entry[0]['id'];
		}

		function update_entry($fields)
		{
			$ab_id  = isset($fields['ab_id']) ? $fields['ab_id'] : $fields['id'];
			$owner  = $fields['owner'];
			unset($fields['owner']);
			unset($fields['ab_id']);
			unset($fields['id']);

			return $this->contacts->update($ab_id,$owner,$fields);
		}

		function delete_entry($id)
		{
			return $this->contacts->delete($id);
		}
	}
?>
