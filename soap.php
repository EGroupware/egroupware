<?php
	/**************************************************************************\
	* phpGroupWare - SOAP Server                                               *
	* http://www.phpgroupware.org                                              *
	* Written by Miles Lott <milosch@phpgroupware.org>                         *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'disable_Template_class' => True,
		'currentapp' => 'login',
		'noheader'   => True
	);

	include('./header.inc.php');

	$GLOBALS['server'] = CreateObject('phpgwapi.soap_server');
	/* _debug_array($GLOBALS['server']);exit; */
	/* include(PHPGW_API_INC . '/soaplib.soapinterop.php'); */

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
			$GLOBALS['server']->authed = True;
		}
		elseif($GLOBALS['phpgw']->session->verify_server($sessionid,$kp3))
		{
			$GLOBALS['server']->authed = True;
		}
	}

	$GLOBALS['server']->add_to_map(
		'system_login',
		array('soapstruct'),
		array('soapstruct')
	);
	$GLOBALS['server']->add_to_map(
		'system_logout',
		array('soapstruct'),
		array('soapstruct')
	);

	if(function_exists('system_listapps'))
	{
		$GLOBALS['server']->add_to_map(
			'system_listApps',
			array(),
			array('soapstruct')
		);
	}

	$GLOBALS['server']->service($HTTP_RAW_POST_DATA);
?>
