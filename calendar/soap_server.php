<?php
/**************************************************************************\
* phpGroupWare - calendar                                                  *
* http://www.phpgroupware.org                                              *
* Written by Mark A Peters <skeeter@phpgroupware.org>                      *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

	/* $Id$ */

//	$login  = 'skeeter';
//	$passwd = 'Mast\!Mage';
//	$sessionid = 'c849d2572fe94cbccdf67c5a33ef7d15';
//	$kp3 = 'dc6d2b287cce75e8794fec51ee78c3cb';
//	$domain = 'default';

	$phpgw_info['flags'] = array(
		'disable_template_class' => True,
//		'login'                  => True,
		'currentapp'             => 'calendar',
		'noheader'               => True,
		'nofooter'               => True);

	include('../header.inc.php');
	include('../soap/vars.php');

// 1. include client and server
// 2. instantiate server object

//	function read_entry($id)
//	{
//		$cal = CreateObject('calendar.bocalendar');
//		return CreateObject('soap.soapval',"event","array",$cal->assoc_array($cal->read_entry($id)));
//	}
	$server = CreateObject('phpgwapi.soap_server');
//	CreateObject('phpgwapi.soap_client');

//	$server->add_to_map(
//		"read_entry",
//		array("int"),
//		array("array")
//	);
	$server->service($HTTP_RAW_POST_DATA);

//	$cal = CreateObject('calendar.soap_calendar');
?>
