<?php
	/**************************************************************************\
	* eGroupWare xmlrpc server                                                 *
	* http://www.phpgroupware.org                                              *
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
	$server->authed = False;
//	debug($server);exit;

	/* uncomment here if you want to show all of the testing functions for compatibility */
//	include(PHPGW_API_INC . '/xmlrpc.interop.php');

	/* Note: this command only available under Apache */
	$headers = getallheaders();

	if(ereg('Basic',$headers['Authorization']))
	{
		$tmp = $headers['Authorization'];
		$tmp = ereg_replace(' ','',$tmp);
		$tmp = ereg_replace('Basic','',$tmp);
		$auth = base64_decode(trim($tmp));
		list($sessionid,$kp3) = split(':',$auth);

		if($GLOBALS['phpgw']->session->verify($sessionid,$kp3))
		{
			$server->authed = True;
		}
	}

	$server->service($HTTP_SERVER_VARS['HTTP_RAW_POST_DATA']);
?>
