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

	if ($GLOBALS['egw']->acl->check('changepassword',1))
	{
		$file['Change your Password'] = $GLOBALS['egw']->link('/index.php','menuaction=preferences.uipassword.change');
	}
	$file['common preferences'] = $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname=preferences');

	display_section('preferences',$file);
?>
