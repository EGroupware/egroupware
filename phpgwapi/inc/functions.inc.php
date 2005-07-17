<?php
/**************************************************************************\
* eGroupWare API loader                                                    *
* This file was originaly written by Dan Kuykendall and Joseph Engo        *
* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
* -------------------------------------------------------------------------*
* Rewritten by RalfBecker@outdoor-training.de to store the eGW enviroment  *
* (egw-object and egw_info-array) in a php-session and restore it from     *
* there instead of creating it completly new on each page-request.         *
* The enviroment gets now created by the egw-class                         *
* -------------------------------------------------------------------------*
* This library is part of the eGroupWare API http://www.egroupware.org     *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

error_reporting(E_ALL & ~E_NOTICE);
magic_quotes_runtime(false);

if (!function_exists('version_compare'))//version_compare() is only available in PHP4.1+
{
	echo 'eGroupWare requires PHP 4.1 or greater.<br>';
	echo 'Please contact your System Administrator';
	exit;
}

if (!defined('EGW_API_INC')) define('EGW_API_INC',PHPGW_API_INC);	// this is to support the header upgrade

/* Make sure the header.inc.php is current. */
if (!isset($GLOBALS['egw_domain']) || $GLOBALS['egw_info']['server']['versions']['header'] < $GLOBALS['egw_info']['server']['versions']['current_header'])
{
	echo '<center><b>You need to port your settings to the new header.inc.php version by running <a href="setup/manageheader.php">setup/headeradmin</a>.</b></center>';
	exit;
}

/* Make sure the developer is following the rules. */
if (!isset($GLOBALS['egw_info']['flags']['currentapp']))
{
	echo "<p><b>!!! YOU DO NOT HAVE YOUR \$GLOBALS['egw_info']['flags']['currentapp'] SET !!!<br>\n";
	echo '!!! PLEASE CORRECT THIS SITUATION !!!</b></p>';
}

//define('NO_RESTORE',true);	// uncomment to see the difference ;-)

// check if we can restore the eGW enviroment from the php-session
if (!defined('NO_RESTORE') && $GLOBALS['egw_info']['server']['sessions_type'] == 'php4' && $_REQUEST['sessionid'] &&	
	$GLOBALS['egw_info']['flags']['currentapp'] != 'login' && $GLOBALS['egw_info']['flags']['currentapp'] != 'logout')
{
	session_name('sessionid');
	session_start();
	
	if (is_array($_SESSION['egw_info_cache']) && is_array($_SESSION['egw_included_files']) && $_SESSION['egw_object_cache'])
	{
		// marking the context as restored from the session, used by session->verify to not read the date from the db again
		$GLOBALS['egw_info']['flags']['restored_from_session'] = true;
		
		// restoring the egw_info-array
		$flags = $GLOBALS['egw_info']['flags'];
		$GLOBALS['egw_info'] = $_SESSION['egw_info_cache'];
		$GLOBALS['egw_info']['flags'] = $flags;
		unset($flags);
		
		// including the necessary class-definitions
		foreach($_SESSION['egw_included_files'] as $file)
		{
			//echo "<p>about to include $file</p>\n";
			include_once($file);
		}
		$GLOBALS['egw'] = unserialize($_SESSION['egw_object_cache']);
		
		$GLOBALS['egw']->wakeup2();	// adapt the restored egw-object/enviroment to this request (eg. changed current app)
		
		//printf("<p style=\"position: absolute; right: 0px; top: 0px;\">egw-enviroment restored in %d ms</p>\n",1000*(perfgetmicrotime()-$GLOBALS['egw_info']['flags']['page_start_time']));

		return;	// exit this file, as the rest of the file creates a new egw-object and -enviroment
	}
	//echo "<p>could not restore egw_info and the egw-object!!!</p>\n";
}
include(EGW_API_INC.'/common_functions.inc.php');

print_debug('sane environment','messageonly','api');

/****************************************************************************\
* Multi-Domain support                                                       *
\****************************************************************************/

if (!isset($GLOBALS['egw_info']['server']['default_domain']) ||	// allow to overwrite the default domain
	!isset($GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]))
{
	reset($GLOBALS['egw_domain']);
	list($GLOBALS['egw_info']['server']['default_domain']) = each($GLOBALS['egw_domain']);
}
if (isset($_POST['login']))	// on login
{
	$GLOBALS['login'] = $_POST['login'];
	if (strstr($GLOBALS['login'],'@') === False || count($GLOBALS['egw_domain']) == 1)
	{
		$GLOBALS['login'] .= '@' . get_var('logindomain',array('POST'),$GLOBALS['egw_info']['server']['default_domain']);
	}
	$parts = explode('@',$GLOBALS['login']);
	$GLOBALS['egw_info']['user']['domain'] = array_pop($parts);
}
else	// on "normal" pageview
{
	$GLOBALS['egw_info']['user']['domain'] = $_REQUEST['domain'];
}

if (@isset($GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]))
{
	$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_host'];
	$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_port'];
	$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_name'];
	$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_user'];
	$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_pass'];
	$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_type'];
}
else
{
	$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_host'];
	$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_port'];
	$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_name'];
	$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_user'];
	$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_pass'];
	$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['server']['default_domain']]['db_type'];
}
print_debug('domain',@$GLOBALS['egw_info']['user']['domain'],'api');

// the egw-object instanciates all sub-classes (eg. $GLOBALS['egw']->db) and the egw_info array
$GLOBALS['egw'] =& CreateObject('phpgwapi.egw',array_keys($GLOBALS['egw_domain']));

if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login')
{
	if (!$GLOBALS['egw_info']['server']['show_domain_selectbox'])
	{
		unset ($GLOBALS['egw_domain']); // we kill this for security reasons
	}
	//printf("<p style=\"position: absolute; right: 0px; top: 0px;\">egw-enviroment new created in %d ms</p>\n",1000*(perfgetmicrotime()-$GLOBALS['egw_info']['flags']['page_start_time']));
}

// saving the the egw_info array and the egw-object in the session
if (!defined('NO_RESTORE') && $GLOBALS['egw_info']['server']['sessions_type'] == 'php4' && $GLOBALS['egw_info']['flags']['currentapp'] != 'login')
{
	$_SESSION['egw_info_cache'] = $GLOBALS['egw_info'];
	unset($_SESSION['egw_info_cache']['flags']);	// dont save the flags, they change on each request

	// exclude 1: caller, 2: the header.inc.php, 3: phpgwapi/setup/setup.inc.php, 4: phpgwapi/inc/functions.inc.php (this file) 
	$_SESSION['egw_included_files'] = array();
	foreach(array_slice(get_included_files(),4) as $file)
	{
		if (!strstr($file,'phpgwapi')) continue;

		switch(basename($file))
		{
			case 'head.inc.php':	// needs EGW_TEMPLATE_DIR and is included anyway by common::egw_header()
			case 'functions.inc.php': // not needed/wanted at all
				break;
			default:
				$_SESSION['egw_included_files'][] = $file;
		}
	}
	$_SESSION['egw_object_cache'] = serialize($GLOBALS['egw']);
}
