<?php
/**
 * eGroupWare - JSON gateway
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel
 * @version $Id$
 */

require_once('./phpgwapi/inc/class.egw_json.inc.php');

/**
 * Exception handler for xajax, return the message (and trace, if enabled) as alert() to the user
 *
 * Does NOT return!
 *
 * @param Exception $e
 */
function ajax_exception_handler(Exception $e)
{
	//Exceptions should be returned
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
	//error_log("xajax.php: appName=$appName, className=$className, functionName=$functionName, handler=$handler");

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
	include('./header.inc.php');


	//Create a new json handler
	$json = new egw_json_request();

	//Check whether the request data is set
	$json->parseRequest($_GET['menuaction'], (array)$_POST['json_data']);
	exit;
}

throw new Exception($_SERVER['PHP_SELF'] . "Invalid AJAX JSON Request");


