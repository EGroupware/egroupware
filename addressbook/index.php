<?php
	/**************************************************************************\
	* phpGroupWare - Addressbook                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	* This application written by Joseph Engo <jengo@phpgroupware.org>         *
	*  and Miles Lott<milosch@phpgroupware.org>                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['phpgw_info'] = array();

	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'addressbook'
	);
	include('../header.inc.php');

	$obj = CreateObject('addressbook.uiaddressbook');
	$obj->index();
?>
