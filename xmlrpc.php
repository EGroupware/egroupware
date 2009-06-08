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
	/*list($usec, $sec) = explode(" ", microtime());
	$GLOBALS['concisus']['script_start'] = ((float)$usec + (float)$sec);*/

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'            => 'login',
			'noheader'              => True,
			'disable_Template_class' => True
		)
	);
	include('./header.inc.php');

	include_once(EGW_API_INC . '/xml_functions.inc.php');
	include_once(EGW_API_INC . '/soap_functions.inc.php');	// not sure that's neccessary, but I have no way to test

	//viniciuscb: a secure way to know if we're in a xmlrpc call...
	$GLOBALS['egw_info']['server']['xmlrpc'] = true;

	$server = CreateObject('phpgwapi.xmlrpc_server');

	/* uncomment here if you want to show all of the testing functions for compatibility */
	//include(EGW_API_INC . '/xmlrpc.interop.php');

	if (!$GLOBALS['egw_info']['server']['xmlrpc_enabled'])
	{
		$server->xmlrpc_error(9999,'xmlrpc service is not enabled in the eGroupWare system configuration');
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

	//print_r($headers);
	$isodate = $headers['isoDate'] ? $headers['isoDate'] : $headers['isodate'];
	$isodate = ($isodate == 'simple') ? True : False;
	$server->setSimpleDate($isodate);
	$auth_header = $headers['Authorization'] ? $headers['Authorization'] : $headers['authorization'];

	if(preg_match('/Basic *([^ ]*)/i',$auth_header,$auth))
	{
		list($sessionid,$kp3) = explode(':',base64_decode($auth[1]));
		//echo "auth='$auth[1]', sessionid='$sessionid', kp3='$kp3'\n";
	}
	else
	{
		$sessionid = get_var('sessionid',array('COOKIE','GET'));
		$kp3 = get_var('kp3',array('COOKIE','GET'));
	}
	$server->authed = $GLOBALS['egw']->session->verify($sessionid,$kp3);

	if (!$server->authed and isset($_SERVER['PHP_AUTH_USER']) and isset($_SERVER['PHP_AUTH_PW']))
	{
		$authed = $GLOBALS['egw']->session->create($login.'@'.$domain, $_SERVER['PHP_AUTH_PW'], 'text');

		if ($authed)
		{
			$server->authed = true;
		}
	}

	$server->service($_SERVER['HTTP_RAW_POST_DATA']);
