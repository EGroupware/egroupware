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

	$contacts = CreateObject('phpgwapi.contacts');

	if(intval($GLOBALS['new_owner'])==0)
	{
		$contacts->delete_all(intval($GLOBALS['account_id']));
	}
	else
	{
		$contacts->change_owner(intval($GLOBALS['account_id']),intval($GLOBALS['new_owner']));
	}
?>
