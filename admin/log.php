<?php
	/**************************************************************************\
	* phpGroupWare - log                                                       *
	* http://www.phpgroupware.org                                              *
	* Written by jerry westrick [jerry@westrick.com]                           *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$phpgw_info['flags'] = array
	(
		'currentapp' => 'admin'
	);
	include('../header.inc.php');
	$obj = CreateObject('admin.uilog');
	$obj->list_log();
?>
