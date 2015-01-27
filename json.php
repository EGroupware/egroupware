<?php
/**
 * EGroupware - general JSON handler for EGroupware
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

/**
 * callback if the session-check fails, redirects to login.php
 *
 * @param array &$anon_account anon account_info with keys 'login', 'passwd' and optional 'passwd_type'
 * @return boolean|string true if we allow anon access and anon_account is set, a sessionid or false otherwise
 */
function login_redirect(&$anon_account)
{
	unset($anon_account);
	egw_json_request::isJSONRequest(true);	// because egw_json_request::parseRequest() is not (yet) called
	$response = egw_json_response::get();
	$response->redirect($GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=10', true);

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
	// handle redirects without logging
	if (is_a($e, 'egw_exception_redirect'))
	{
		egw::redirect($e->url, $e->app);
	}
	// logging all exceptions to the error_log
	$message = null;
	if (function_exists('_egw_log_exception'))
	{
		_egw_log_exception($e,$message);
	}
	$response = egw_json_response::get();
	$message .= ($message ? "\n\n" : '').$e->getMessage();

	// only show trace (incl. function arguments) if explicitly enabled, eg. on a development system
	if ($GLOBALS['egw_info']['server']['exception_show_trace'])
	{
		$message .= "\n\n".$e->getTraceAsString();
	}
	$response->alert($message);

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
			'autocreate_session_callback' => 'login_redirect',
			'no_exception_handler' => true,	// we already installed our own
			// only log ajax requests which represent former GET requests or submits
			// cuts down updates to egw_access_log table
			'no_dla_update' => !preg_match('/(\.etemplate_new\.ajax_process_content\.etemplate|\.jdots_framework\.ajax_exec\.template)$/', $_GET['menuaction']),
		)
	);
	include_once('./header.inc.php');


	//Create a new json handler
	$json = new egw_json_request();

	//Check whether the request data is set
	if (isset($GLOBALS['egw_unset_vars']['_POST[json_data]']))
	{
		$json->isJSONRequest(true);	// otherwise exception is not send back to client, as we have not yet called parseRequest()
		throw new egw_exception_assertion_failed("JSON Data contains script tags. Aborting...");
	}
	$json->parseRequest($_GET['menuaction'], $_REQUEST['json_data']);
	egw_json_response::get();
	common::egw_exit();
}

throw new Exception($_SERVER['PHP_SELF'] . ' Invalid AJAX JSON Request');
