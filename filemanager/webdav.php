<?php
/**************************************************************************\
* eGroupWare - FileManger - WebDAV access                                  *
* http://www.egroupware.org                                                *
* Written and (c) 2006 by  Ralf Becker <RalfBecker-AT-outdoor-training.de> *
* ------------------------------------------------------------------------ *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id: class.socontacts_sql.inc.php 21634 2006-05-24 02:28:57Z ralfbecker $ */

/**
 * FileManger - WebDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 * 
 * @package filemanger
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * check if the given user has access
 * 
 * Create a session or if the user has no account return authenticate header and 401 Unauthorized
 *
 * @param array &$account
 * @return int session-id
 */
function check_access(&$account)
{
	$account = array(
		'login'  => $_SERVER['PHP_AUTH_USER'],
		'passwd' => $_SERVER['PHP_AUTH_PW'],
		'passwd_type' => 'text',
	);
	if (!($sessionid = $GLOBALS['egw']->session->create($account)))
	{
		header('WWW-Authenticate: Basic realm="eGroupWare WebDAV"');
        header("HTTP/1.1 401 Unauthorized");
        header("X-WebDAV-Status: 401 Unauthorized", true);
        exit;
	}
	return $sessionid;
}
// uncomment the next line if dav should use a eGW domain different from the first one defined in your header.inc.php
// and of cause change the name accordingly ;-)
// $GLOBALS['egw_info']['user']['domain'] = $GLOBALS['egw_info']['server']['default_domain'] = 'developers';

$GLOBALS['egw_info']['flags'] = array(
	'disable_Template_class' => True,
	'noheader'  => True,
	'currentapp' => 'filemanager',
	'autocreate_session_callback' => 'check_access',
);
// if you move this file somewhere else, you need to adapt the path to the header!
include('../header.inc.php');

ExecMethod('phpgwapi.vfs_webdav_server.ServeRequest');
