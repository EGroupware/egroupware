<?php
	/**************************************************************************\
	* phpGroupWare - Info Log administration                                   *
	* http://www.phpgroupware.org                                              *
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
		'Site configuration' => $GLOBALS['phpgw']->link('/index.php',array('menuaction' => 'infolog.uiinfolog.admin' )),
		'Global Categories'  => $GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index&appname=' . $appname . '&global_cats=True'),
		'CSV-Import' => $GLOBALS['phpgw']->link('/infolog/csv_import.php')
	);
//Do not modify below this line
	display_section($appname,$title,$file);
}
?>