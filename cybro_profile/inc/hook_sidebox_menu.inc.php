<?php
    /**************************************************************************\
    * eGroupWare - Skeleton Application                                        *
    * http://www.egroupware.org                                                *
    * -----------------------------------------------                          *
    *  This program is free software; you can redistribute it and/or modify it *
    *  under the terms of the GNU General Public License as published by the   *
    *  Free Software Foundation; either version 2 of the License, or (at your  *
    *  option) any later version.                                              *
    \**************************************************************************/

	/* $Id: hook_sidebox_menu.inc.php,v 1.2 2005/05/02 13:13:56 milosch Exp $ */
{
	/*
		This hookfile is for generating an app-specific side menu used in the idots
		template set.

		$menu_title speaks for itself
		$file is the array with link to app functions

		display_sidebox can be called as much as you like
	*/

	/*
		$menu_title = 'Preferences';
		$file = Array(

		);
		display_sidebox($appname,$menu_title,$file);
	*/

	if($GLOBALS['egw_info']['user']['apps']['admin'])
	{
		$menu_title = 'Administration';
		$file = array();

		display_sidebox($appname,$menu_title,$file);
	}
}
?>
