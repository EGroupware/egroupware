<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	if((int)$GLOBALS['hook_values']['account_id'] > 0)
	{
		$contacts = CreateObject('phpgwapi.contacts');

		if((int)$_POST['new_owner'] == 0)
		{
			$contacts->delete_all((int)$GLOBALS['hook_values']['account_id']);
		}
		else
		{
			$contacts->change_owner((int)$GLOBALS['hook_values']['account_id'],(int)$_POST['new_owner']);
		}
	}
?>
