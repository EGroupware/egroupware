<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* -------------------------------------------------------                  *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

{
// Only Modify the $file and $title variables.....
	$title = $appname;
	$file = array(
		'Preferences'     => $GLOBALS['phpgw']->link('/index.php','menuaction=infolog.uiinfolog.preferences'),
		'Grant Access'    => $GLOBALS['phpgw']->link('/preferences/acl_preferences.php','acl_app='.$appname),
		'Edit Categories' => $GLOBALS['phpgw']->link('/preferences/categories.php','cats_app='.$appname)
	);
//Do not modify below this line
	display_section($appname,$title,$file);
}

?>
