<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	// Delete all records for a user
	if(intval($GLOBALS['HTTP_POST_VARS']['new_owner'])==0)
	{
		ExecMethod('calendar.bocalendar.delete_calendar',intval($GLOBALS['HTTP_POST_VARS']['account_id']));
	}
	else
	{
		ExecMethod('calendar.bocalendar.change_owner',
			Array(
				'old_owner'	=> intval($GLOBALS['HTTP_POST_VARS']['account_id']),
				'new_owner'	=> intval($GLOBALS['HTTP_POST_VARS']['new_owner'])
			)
		);
	}
?>
