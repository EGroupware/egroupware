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
	/* $Source$ */

	require_once('./phpgwapi/inc/xajax.inc.php');

	function doXMLHTTP()
	{
		$numargs = func_num_args(); 
		if($numargs < 1) 
			return false;

		$argList	= func_get_args();
		$arg0		= array_shift($argList);
			
		list($appName, $className, $functionName) = explode('.',$arg0);
		
		if(substr($className,0,4) != 'ajax' && $arg0 != 'etemplate.etemplate.process_exec' && substr($functionName,0,4) != 'ajax')
		{
			// stopped for security reasons
			error_log($_SERVER["PHP_SELF"]. ' stopped for security reason. className '.$className.' is not valid. className must start with ajax!!!');
			exit;
		}
		
		$GLOBALS['egw_info'] = array();
		$GLOBALS['egw_info']['flags'] = array(
			'currentapp'			=> $appName,
			'noheader'			=> True,
			'disable_Template_class'	=> True,
		);

		include('./header.inc.php');

		$ajaxClass = CreateObject("$appName.$className");
		$argList = $GLOBALS['egw']->translation->convert($argList, 'utf-8');
		return call_user_func_array(array(&$ajaxClass, $functionName), $argList );
			
	}
	
	$xajax = new xajax($_SERVER["PHP_SELF"]);
	$xajax->registerFunction("doXMLHTTP");	
	$xajax->processRequests();
?>
