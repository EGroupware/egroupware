<?php
  /**************************************************************************\
  * phpGroupWare xmlrpc server                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Dan Kuykendall <dan@kuykendall.org>                           *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp' => 'login',
		'noheader'   => True
	);

	include('./header.inc.php');

	$server = CreateObject('phpgwapi.xmlrpc_server');
	$server->authed = False;
	/* _debug_array($server);exit; */
	include(PHPGW_API_INC . '/xmlrpc.interop.php');

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
		elseif($GLOBALS['phpgw']->session->verify_server($sessionid,$kp3))
		{
			$server->authed = True;
		}
	}

	$server->service($HTTP_RAW_POST_DATA);
?>
