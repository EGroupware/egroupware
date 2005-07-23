<?php
	/**************************************************************************\
	* eGroupWare - preferences                                                 *
	* http://www.egroupware.org                                                *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'preferences'
	);
	include('../header.inc.php');

	$obj = CreateObject('preferences.uichangepassword');
	$obj->index();

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
