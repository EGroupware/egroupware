<?php
	/**************************************************************************\
	* phpGroupWare - Administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['menuData'][] = array(
		'description' => 'Login History',
		'url'         => '/index.php',
		'extradata'   => 'menuaction=admin.uiaccess_history.list_history'
	);

	$GLOBALS['menuData'][] = array(
		'description' => 'ACL Rights',
		'url'         => '/index.php',
		'extradata'   => 'menuaction=admin.uiaclmanager.list_apps'
	);

	//Do not modify below this line
//	global $menuData;

//	$GLOBALS['menuData'][] = $data;
?>