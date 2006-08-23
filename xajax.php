<?php
	/**************************************************************************\
	* eGroupWare xmlhttp server                                                *
	* http://www.egroupware.org                                                *
	* This file written by Lars Kneschke <lkneschke@egroupware.org>            *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License.              *
	\**************************************************************************/

	/* $Id$ */

	require_once('./phpgwapi/inc/xajax.inc.php');

	/**
	 * callback if the session-check fails, redirects via xajax to login.php
	 * 
	 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
	 * @return boolean/string true if we allow anon access and anon_account is set, a sessionid or false otherwise
	 */
	function xajax_redirect(&$anon_account)
	{
		$response =& new xajaxResponse();
		$response->addScript("location.href='".$GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10'."';");

		header('Content-type: text/xml; charset='.$GLOBALS['egw']->translation->charset());
		echo $response->getXML();
		$GLOBALS['egw']->common->egw_exit();
	}

	function doXMLHTTP()
	{
		$numargs = func_num_args(); 
		if($numargs < 1) 
			return false;

		$argList	= func_get_args();
		$arg0		= array_shift($argList);
			
		if(get_magic_quotes_gpc()) {
			foreach($argList as $key => $value) {
				if(is_array($value)) {
					foreach($argList as $key1 => $value1) {
						$argList[$key][$key1] = stripslashes($value1);
					}
				} else {
					$argList[$key] = stripslashes($value);
				}
			}
		}

		list($appName, $className, $functionName) = explode('.',$arg0);
		
		if(substr($className,0,4) != 'ajax' && $arg0 != 'etemplate.etemplate.process_exec' && substr($functionName,0,4) != 'ajax')
		{
			// stopped for security reasons
			error_log($_SERVER['PHP_SELF']. ' stopped for security reason. '.$arg0.' is not valid. class- or function-name must start with ajax!!!');
			exit;
		}
		
		$GLOBALS['egw_info'] = array(
			'flags' => array(
				'currentapp'			=> $appName,
				'noheader'			=> True,
				'disable_Template_class'	=> True,
				'autocreate_session_callback' => 'xajax_redirect',
			)
		);
		include('./header.inc.php');
		
		$ajaxClass =& CreateObject($appName.'.'.$className);
		$argList = $GLOBALS['egw']->translation->convert($argList, 'utf-8');

		return call_user_func_array(array(&$ajaxClass, $functionName), $argList );			
	}
	
	$xajax = new xajax($_SERVER['PHP_SELF']);
	$xajax->registerFunction('doXMLHTTP');	
	$xajax->processRequests();
