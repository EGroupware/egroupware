<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	// Delete all records for a user
	if (floor(phpversion()) == 4)
	{
		global $account_id, $new_owner;
	}

	$contacts = CreateObject('phpgwapi.contacts');

	if(intval($new_owner)==0)
	{
		$contacts->delete_all(intval($account_id));
	}
	else
	{
		$contacts->change_owner(intval($account_id),intval($new_owner));
	}
?>
