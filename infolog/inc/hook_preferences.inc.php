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
	$file = array(
		'Preferences'     => $GLOBALS['phpgw']->link('/index.php','menuaction=infolog.uiinfolog.preferences'),
		'Grant Access'    => $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
		'Edit Categories' => $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
	);
//Do not modify below this line
	list($ver,$mayor,$minor,$ref) = explode('.',$GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
	if ($minor > 14)
		display_section($appname,$file);
	else
		display_section($appname,$appname,$file);
}

?>
