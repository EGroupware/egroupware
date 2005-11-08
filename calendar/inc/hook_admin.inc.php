<?php
	/**************************************************************************\
	* eGroupWare - Calendar                                                    *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
{
// Only Modify the $file and $title variables.....
	$title = $appname;
	$file = Array(
		'Site Configuration' => $GLOBALS['egw']->link('/index.php','menuaction=admin.uiconfig.index&appname=calendar'),
		'Custom fields' => $GLOBALS['egw']->link('/index.php','menuaction=admin.customfields.edit&appname=calendar'),
		'Calendar Holiday Management' => $GLOBALS['egw']->link('/index.php','menuaction=calendar.uiholiday.admin'),
		'Global Categories' => $GLOBALS['egw']->link('/index.php','menuaction=admin.uicategories.index&appname=calendar')
	);
//Do not modify below this line
	display_section($appname,$title,$file);
}
?>
