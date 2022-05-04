<?php
/**
 * EGroupware Addressbook: incomming CTI / open CRM view for a given telephon-number and user
 *
 * Usage: curl --user "<user>:<password>" "https://example.com/egroupware/addressbook/crm.php?from=<phone-number>"
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = [
	'flags' => [
		'disable_Template_class' => true,
		'noheader'  => true,
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
	]
];

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
if (empty($auth) && empty($_SERVER['PHP_AUTH_USER']))
{
	http_response_code(401);
	header('WWW-Authenticate: Basic realm="EGroupware"');
	header('WWW-Authenticate: Bearer realm="EGroupware CTI"', false);
	exit;
}

// basic auth
if (!empty($_SERVER['PHP_AUTH_USER']) || stripos($auth, 'Basic') === 0)
{
	$GLOBALS['egw_info']['flags'] += [
		'currentapp' => 'addressbook',
		'autocreate_session_callback' => 'EGroupware\\Api\\Header\\Authenticate::autocreate_session_callback',
	];
}
// bearer token
else
{
	$GLOBALS['egw_info']['flags'] += [
		'currentapp' => 'login',
	];
}

include dirname(__DIR__).'/header.inc.php';

//print_r($_SERVER);
//echo substr(print_r($GLOBALS['egw_info']['user'], true), 0, 256)." ...\n";

if (stripos($auth, 'Bearer') === 0)
{
	http_response_code(500);
	error_log("crm.php: ToDo: Bearer token support");
	die("ToDo: Bearer token support\n");
}
else
{
	$account_id = $GLOBALS['egw_info']['user']['account_id'];
	$user = $GLOBALS['egw_info']['user']['account_lid'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET')
{
	if (empty($_GET['from']))
	{
		http_response_code(400);
		error_log("crm.php: Missing 'from' GET parameter");
		die("Missing 'from' GET parameter\n");
	}
	$from = $_GET['from'];

	// fix missing url-encoding of +49...
	if (preg_match('/^ [\d]+/', $from))
	{
		$from[0] = '+';
	}
}
else
{
	switch($_SERVER['REQUEST_METHOD'].'-'.$_SERVER['HTTP_CONTENT_TYPE'])
	{
		default:
			http_response_code(500);
			error_log("crm.php: No support for $_SERVER[REQUEST_METHOD]-$_SERVER[HTTP_CONTENT_TYPE]");
			die("No support for $_SERVER[REQUEST_METHOD]-$_SERVER[HTTP_CONTENT_TYPE]\n");
	}
}

try {
	$contacts = new Api\Contacts();
	$contacts->openCrmView($from);
}
catch (\Exception $e) {
	error_log("crm.php: No contact for from=$from found!");
	die("No contact for from=$from found!\n");
}