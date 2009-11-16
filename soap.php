<?php
	/**************************************************************************\
	* eGroupWare - SOAP Server                                                 *
	* http://www.egroupware.org                                                *
	* Written by Miles Lott <milos@groupwhere.org>                             *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'disable_Template_class' => True,
			'currentapp' => 'login',
			'noheader'   => True,
			'disable_Template_class' => True
		)
	);
	include('./header.inc.php');

	include_once(EGW_API_INC . '/xml_functions.inc.php');	// not sure that's neccessary, but I have no way to test
	include_once(EGW_API_INC . '/soap_functions.inc.php');

	$GLOBALS['server'] = CreateObject('phpgwapi.soap_server');
	/* _debug_array($GLOBALS['server']);exit; */
	/* include(EGW_API_INC . '/soaplib.soapinterop.php'); */

	if (!$GLOBALS['egw_info']['server']['soap_enabled'])
	{
		$GLOBALS['server']->make_fault(9999,'soap service is not enabled in the eGroupWare system configuration');
		$GLOBALS['server']->service($GLOBALS['server']->fault());
		exit;
	}

	/* Note: this command only available natively in Apache (Netscape/iPlanet/SunONE in php >= 4.3.3) */
	if(!function_exists('getallheaders'))
	{
		function getallheaders()
		{
			settype($headers,'array');
			foreach($_SERVER as $h => $v)
			{
				if(preg_match('/HTTP_(.+)/',$h,$hp))
				{
					$headers[$hp[1]] = $v;
				}
			}
			return $headers;
		}
	}
	$headers = getallheaders();

	if(ereg('Basic',$headers['Authorization']))
	{
		$tmp = $headers['Authorization'];
		$tmp = str_replace(' ','',$tmp);
		$tmp = str_replace('Basic','',$tmp);
		$auth = base64_decode(trim($tmp));
		list($sessionid,$kp3) = explode(':',$auth);

		if($GLOBALS['egw']->session->verify($sessionid,$kp3))
		{
			$GLOBALS['server']->authed = True;
		}
		elseif($GLOBALS['egw']->session->verify_server($sessionid,$kp3))
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
