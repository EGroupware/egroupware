<?php
/**
 * EGroupware API loader
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
 */

use EGroupware\Api\Session;
use EGroupware\Api\Egw;

// E_STRICT in PHP 5.4 gives various strict warnings in working code, which can NOT be easy fixed in all use-cases :-(
// Only variables should be assigned by reference, eg. soetemplate::tree_walk()
// Declaration of <extended method> should be compatible with <parent method>, various places where method parameters change
// --> switching it off for now, as it makes error-log unusable
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$egw_min_php_version = '8.0';
if (!function_exists('version_compare') || version_compare(PHP_VERSION,$egw_min_php_version) < 0)
{
	die("EGroupware requires PHP $egw_min_php_version or greater.<br />Please contact your System Administrator to upgrade PHP!");
}

if (!defined('EGW_API_INC') && defined('PHPGW_API_INC')) define('EGW_API_INC',PHPGW_API_INC);	// this is to support the header upgrade

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

require_once(__DIR__.'/loader/common.php');

// init eGW's sessions-handler and check if we can restore the eGW enviroment from the php-session
if (Session::init_handler())
{
	if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' && $GLOBALS['egw_info']['flags']['currentapp'] != 'logout')
	{
		if (is_array($_SESSION[Session::EGW_INFO_CACHE]) && $_SESSION[Session::EGW_OBJECT_CACHE] && $_SESSION[Session::EGW_REQUIRED_FILES])
		{
			// marking the context as restored from the session, used by session->verify to not read the data from the db again
			$GLOBALS['egw_info']['flags']['restored_from_session'] = true;

			// restoring the egw_info-array
			$GLOBALS['egw_info'] = array_merge($_SESSION[Session::EGW_INFO_CACHE],array('flags' => $GLOBALS['egw_info']['flags']));

			// include required class-definitions
			if (is_array($_SESSION[Session::EGW_REQUIRED_FILES]))	// all classes, which can not be autoloaded
			{
				foreach($_SESSION[Session::EGW_REQUIRED_FILES] as $file)
				{
					require_once($file);
				}
			}
			$GLOBALS['egw'] = unserialize($_SESSION[Session::EGW_OBJECT_CACHE]);

			if (is_object($GLOBALS['egw']) && ($GLOBALS['egw'] instanceof Egw))	// only egw object has wakeup2, setups egw_minimal eg. has not!
			{
				$GLOBALS['egw']->wakeup2();	// adapt the restored egw-object/environment to this request (eg. changed current app)

				$GLOBALS['egw_info']['flags']['session_restore_time'] = microtime(true) - $GLOBALS['egw_info']['flags']['page_start_time'];
				if (is_object($GLOBALS['egw']->translation)) return;	// exit this file, as the rest of the file creates a new egw-object and -enviroment
			}
			// egw object could NOT be restored from the session, create a new one
			unset($GLOBALS['egw']);
			$GLOBALS['egw_info'] = array('flags'=>$GLOBALS['egw_info']['flags']);
			unset($GLOBALS['egw_info']['flags']['restored_from_session']);
			unset($_SESSION[Session::EGW_INFO_CACHE]);
			unset($_SESSION[Session::EGW_REQUIRED_FILES]);
			unset($_SESSION[Session::EGW_OBJECT_CACHE]);
		}
	}
	else	// destroy the session-cache if called by login or logout
	{
		unset($_SESSION[Session::EGW_INFO_CACHE]);
		unset($_SESSION[Session::EGW_REQUIRED_FILES]);
		unset($_SESSION[Session::EGW_OBJECT_CACHE]);
	}
}

/****************************************************************************\
* Multi-Domain support                                                       *
\****************************************************************************/

$GLOBALS['egw_info']['user']['domain'] = Session::search_instance(
	isset($_POST['login']) ? $_POST['login'] : (isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : $_SERVER['REMOTE_USER']),
	Session::get_request('domain'),$GLOBALS['egw_info']['server']['default_domain'],
	array($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']),$GLOBALS['egw_domain']);

$GLOBALS['egw_info']['server'] += $GLOBALS['egw_domain'][$GLOBALS['egw_info']['user']['domain']];

// the egw-object instanciates all sub-classes (eg. $GLOBALS['egw']->db) and the egw_info array
$GLOBALS['egw'] = new Egw(array_keys($GLOBALS['egw_domain']));

if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' && !$GLOBALS['egw_info']['server']['show_domain_selectbox'])
{
	unset($GLOBALS['egw_domain']); // we kill this for security reasons
}

// saving the the egw_info array and the egw-object in the session
if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login')
{
	$_SESSION[Session::EGW_INFO_CACHE] = $GLOBALS['egw_info'];
	unset($_SESSION[Session::EGW_INFO_CACHE]['flags']);	// dont save the flags, they change on each request

	// dont save preferences, as Session::verify restores them from instance cache anyway
	$_SESSION[Session::EGW_INFO_CACHE]['user']['preferences'] = array(
		// we need user language as it is used before preferences get restored!
		'common' => array('lang' => $GLOBALS['egw_info']['user']['preferences']['common']['lang']),
	);

	// dont save apps, as Session::verify restores them from instance cache anyway
	unset($_SESSION[Session::EGW_INFO_CACHE]['apps']);

	// store only which apps user has, Session::verify restores it from egw_info[apps]
	$_SESSION[Session::EGW_INFO_CACHE]['user']['apps'] = array_keys((array)$_SESSION[Session::EGW_INFO_CACHE]['user']['apps']);

	$_SESSION[Session::EGW_OBJECT_CACHE] = serialize($GLOBALS['egw']);
}