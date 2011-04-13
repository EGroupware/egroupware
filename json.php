<?php
/**
 * eGroupWare - general JSON handler for EGroupware
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/**
 * callback if the session-check fails, redirects via xajax to login.php
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean/string true if we allow anon access and anon_account is set, a sessionid or false otherwise
 */
function xajax_redirect(&$anon_account)
{
	$response = new egw_json_response();
	$response->redirect($GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10', true);
	$response->printOutput();

	common::egw_exit();
}

/**
 * Exception handler for xajax, return the message (and trace, if enabled) as alert() to the user
 *
 * Does NOT return!
 *
 * @param Exception $e
 */
function ajax_exception_handler(Exception $e)
{
	// logging all exceptions to the error_log
	if (function_exists('_egw_log_exception'))
	{
		_egw_log_exception($e,$message);
	}
	$response = new egw_json_response();
	$message .= ($message ? "\n\n" : '').$e->getMessage();

	// only show trace (incl. function arguments) if explicitly enabled, eg. on a development system
	if ($GLOBALS['egw_info']['server']['exception_show_trace'])
	{
		$message .= "\n\n".$e->getTraceAsString();
	}
	$response->alert($message);
	$response->printOutput();

	if (is_object($GLOBALS['egw']))
	{
		common::egw_exit();
	}
	exit;
}

// set our own exception handler, to not get the html from eGW's default one
set_exception_handler('ajax_exception_handler');

if (isset($_GET['menuaction']))
{
	if (strpos($_GET['menuaction'],'::') !== false && strpos($_GET['menuaction'],'.') === false)	// static method name app_something::method
	{
		@list($className,$functionName,$handler) = explode('::',$_GET['menuaction']);
		list($appName) = explode('_',$className);
	}
	else
	{
		@list($appName, $className, $functionName, $handler) = explode('.',$_GET['menuaction']);
	}
	//error_log("json.php: appName=$appName, className=$className, functionName=$functionName, handler=$handler");

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp'			=> $appName,
			'noheader'		=> True,
			'disable_Template_class'	=> True,
			'autocreate_session_callback' => 'xajax_redirect',
			'no_exception_handler' => true,	// we already installed our own
			'no_dla_update' => $appName == 'notifications',	// otherwise session never time out
		)
	);
	//if ($_GET['menuaction'] !='notifications.notifications_ajax.get_notifications') error_log(__METHOD__.__LINE__.' Appname:'.$appName.' Action:'.print_r($_GET['menuaction'],true));
	if 	($_GET['menuaction']=='felamimail.ajaxfelamimail.refreshMessageList' ||
			$_GET['menuaction']=='felamimail.ajaxfelamimail.refreshFolderList') 
	{
		$GLOBALS['egw_info']['flags']['no_dla_update']=true;
	}
	include('./header.inc.php');


	//Create a new json handler
	$json = new egw_json_request();

	//Check whether the request data is set
	if (isset($GLOBALS['egw_unset_vars']['_POST[json_data]']))
	{
		throw new egw_exception_assertion_failed("JSON Data contains script tags. Aborting...");
	}
	$json->parseRequest($_GET['menuaction'], (array)$_POST['json_data']);
	common::egw_exit();
}

throw new Exception($_SERVER['PHP_SELF'] . ' Invalid AJAX JSON Request');
