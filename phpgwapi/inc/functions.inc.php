<?php
/**
 * eGroupWare API loader
 *
 * Rewritten by RalfBecker@outdoor-training.de to store the eGW enviroment
 * (egw-object and egw_info-array) in a php-session and restore it from
 * there instead of creating it completly new on each page-request.
 * The enviroment gets now created by the egw-class
 *
 * This file was originaly written by Dan Kuykendall and Joseph Engo
 * Copyright (C) 2000, 2001 Dan Kuykendall
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

error_reporting(E_ALL & ~E_NOTICE);
if (function_exists('get_magic_quotes_runtime') && get_magic_quotes_runtime())
{
	set_magic_quotes_runtime(false);
}

$egw_min_php_version = '5.2';
if (!function_exists('version_compare') || version_compare(PHP_VERSION,$egw_min_php_version) < 0)
{
	die("eGroupWare requires PHP $egw_min_php_version or greater.<br />Please contact your System Administrator to upgrade PHP!");
}
// check if eGW's pear repository is installed and prefer it over the other ones
if (is_dir(EGW_SERVER_ROOT.'/egw-pear'))
{
	set_include_path(EGW_SERVER_ROOT.'/egw-pear'.PATH_SEPARATOR.get_include_path());
	//echo "<p align=right>include_path='".get_include_path()."'</p>\n";
}

if (!defined('EGW_API_INC')) define('EGW_API_INC',PHPGW_API_INC);	// this is to support the header upgrade

/* Make sure the header.inc.php is current. */
if (!isset($GLOBALS['egw_domain']) || $GLOBALS['egw_info']['server']['versions']['header'] < $GLOBALS['egw_info']['server']['versions']['current_header'])
{
	echo '<center><b>You need to update your header.inc.php file to version '.
	$GLOBALS['egw_info']['server']['versions']['current_header'].
	' by running <a href="setup/manageheader.php">setup/headeradmin</a>.</b></center>';
	exit;
}

/* Make sure the developer is following the rules. */
if (!isset($GLOBALS['egw_info']['flags']['currentapp']))
{
	echo "<p><b>!!! YOU DO NOT HAVE YOUR \$GLOBALS['egw_info']['flags']['currentapp'] SET !!!<br>\n";
	echo '!!! PLEASE CORRECT THIS SITUATION !!!</b></p>';
}

require_once(EGW_API_INC.'/common_functions.inc.php');

// init eGW's sessions-handler and check if we can restore the eGW enviroment from the php-session
if (egw_session::init_handler())
{
	if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' && $GLOBALS['egw_info']['flags']['currentapp'] != 'logout')
	{
		if (is_array($_SESSION[egw_session::EGW_INFO_CACHE]) && $_SESSION[egw_session::EGW_OBJECT_CACHE] && $_SESSION[egw_session::EGW_REQUIRED_FILES])
		{
			// marking the context as restored from the session, used by session->verify to not read the data from the db again
			$GLOBALS['egw_info']['flags']['restored_from_session'] = true;

			// restoring the egw_info-array
			$GLOBALS['egw_info'] = array_merge($_SESSION[egw_session::EGW_INFO_CACHE],array('flags' => $GLOBALS['egw_info']['flags']));

			// include required class-definitions
			if (is_array($_SESSION[egw_session::EGW_REQUIRED_FILES]))	// all classes, which can not be autoloaded
			{
				foreach($_SESSION[egw_session::EGW_REQUIRED_FILES] as $file)
				{
					require_once($file);
				}
			}
			$GLOBALS['egw'] = unserialize($_SESSION[egw_session::EGW_OBJECT_CACHE]);

			if (is_object($GLOBALS['egw']))
			{
				$GLOBALS['egw']->wakeup2();	// adapt the restored egw-object/enviroment to this request (eg. changed current app)

				//printf("<p style=\"position: absolute; right: 0px; top: 0px;\">egw-enviroment restored in %d ms</p>\n",1000*(perfgetmicrotime()-$GLOBALS['egw_info']['flags']['page_start_time']));
				$GLOBALS['egw_info']['flags']['session_restore_time'] = microtime(true) - $GLOBALS['egw_info']['flags']['page_start_time'];
				if (is_object($GLOBALS['egw']->translation)) return;	// exit this file, as the rest of the file creates a new egw-object and -enviroment
			}
			// egw object could NOT be restored from the session, create a new one
			unset($GLOBALS['egw']);
			$GLOBALS['egw_info'] = array('flags'=>$GLOBALS['egw_info']['flags']);
			unset($GLOBALS['egw_info']['flags']['restored_from_session']);
			unset($_SESSION[egw_session::EGW_INFO_CACHE]);
			unset($_SESSION[egw_session::EGW_REQUIRED_FILES]);
			unset($_SESSION[egw_session::EGW_OBJECT_CACHE]);
		}
		//echo "<p>could not restore egw_info and the egw-object!!!</p>\n";
	}
	else	// destroy the session-cache if called by login or logout
	{
		unset($_SESSION[egw_session::EGW_INFO_CACHE]);
		unset($_SESSION[egw_session::EGW_REQUIRED_FILES]);
		unset($_SESSION[egw_session::EGW_OBJECT_CACHE]);
	}
}
print_debug('sane environment','messageonly','api');

/****************************************************************************\
* Multi-Domain support                                                       *
\****************************************************************************/

$GLOBALS['egw_info']['user']['domain'] = egw_session::search_instance(
	isset($_POST['login']) ? $_POST['login'] : (isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : $_SERVER['REMOTE_USER']),
	egw_session::get_request('domain'),$GLOBALS['egw_info']['server']['default_domain'],$_SERVER['SERVER_NAME'],$GLOBALS['egw_domain']);

$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_host'];
$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_port'];
$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_name'];
$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_user'];
$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_pass'];
$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['db_type'];
print_debug('domain',@$GLOBALS['egw_info']['user']['domain'],'api');

// the egw-object instanciates all sub-classes (eg. $GLOBALS['egw']->db) and the egw_info array
$GLOBALS['egw'] = new egw(array_keys($GLOBALS['egw_domain']));

// store domain config user&pw as a hash (originals get unset)
$GLOBALS['egw_info']['server']['config_hash'] = egw_session::user_pw_hash($GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['config_user'],
	$GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']]['config_passwd'],true);

if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' && !$GLOBALS['egw_info']['server']['show_domain_selectbox'])
{
	unset($GLOBALS['egw_domain']); // we kill this for security reasons
	unset($GLOBALS['egw_info']['server']['header_admin_user']);
	unset($GLOBALS['egw_info']['server']['header_admin_password']);
}

// saving the the egw_info array and the egw-object in the session
if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login')
{
	$_SESSION[egw_session::EGW_INFO_CACHE] = $GLOBALS['egw_info'];
	unset($_SESSION[egw_session::EGW_INFO_CACHE]['flags']);	// dont save the flags, they change on each request

	$_SESSION[egw_session::EGW_OBJECT_CACHE] = serialize($GLOBALS['egw']);
}
