<?php
	/**************************************************************************\
	* eGroupWare xmlrpc server                                                 *
	* http://www.egroupware.org                                                *
	* This file written by Miles Lott <milos@groupwhere.org>                   *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	/* $Source$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp'            => 'login',
		'noheader'              => True,
		'disable_Template_class' => True
	);
	include('header.inc.php');

	$server = CreateObject('phpgwapi.xmlrpc_server');
	/* uncomment here if you want to show all of the testing functions for compatibility */
	//include(PHPGW_API_INC . '/xmlrpc.interop.php');

	/* Note: this command only available under Apache */
	$headers = getallheaders();
	//print_r($headers);
	$auth_header = $headers['Authorization'] ? $headers['Authorization'] : $headers['authorization'];

	if(eregi('Basic *([^ ]*)',$auth_header,$auth))
	{
		list($sessionid,$kp3) = explode(':',base64_decode($auth[1]));
		//echo "auth='$auth[1]', sessionid='$sessionid', kp3='$kp3'\n";
	}
	else
	{
		$sessionid = get_var('sessionid',array('COOKIE','GET'));
		$kp3 = get_var('kp3',array('COOKIE','GET'));
	}
	$server->authed = $GLOBALS['phpgw']->session->verify($sessionid,$kp3);

	$server->service($_SERVER['HTTP_RAW_POST_DATA']);
?>
