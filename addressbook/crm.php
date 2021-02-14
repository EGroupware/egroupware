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
	$found = $contacts->phone_search($from);
	// ToDo: select best match from multiple matches containing the number
	$contact = $found[0];
	$push = new Api\Json\Push($account_id);
	$extras = [
		//'index': ToDo: what's that used for?
		'crm_list' => count($found) > 1 && $contact['org_name'] ? 'infolog-organisation' : 'infolog',
	];
	$params = [(int)$contact['id'], 'addressbook', 'view', $extras, [
		'displayName' => count($found) > 1 && $contact['org_name'] ?
			$contact['org_name'] : $contact['n_fn'].' ('.lang($extras['crm_list']).')',
		'icon' => $contact['photo'],
		'refreshCallback' => 'app.addressbook.view_refresh',
		'id' => $contact['id'].'-'.$extras['crm_list'],
	]];
	/* ToDo: allow refreshCallback to be a "app.<appname>.<func>" string resolving also private / non-global apps
	$push->apply('egw.openTab', $params);
	*/
	$params = str_replace('"app.addressbook.view_refresh"', 'function(){
	let et2 = etemplate2.getById("addressbook-view-"+this.appName);
	if (et2) et2.app_obj.addressbook.view_set_list();
}', json_encode($params, JSON_UNESCAPED_SLASHES));
	$push->script('egw.openTab.apply(egw, '.$params.')');
	if (!is_string($params)) $params = json_encode($params, JSON_UNESCAPED_SLASHES);
	error_log("crm.php: calling push($user/#$account_id)->apply('egw.openTab', $params)");
	die("calling push($user/#$account_id)->apply('egw.openTab', $params)\n");
}
catch (\Exception $e) {
	error_log("crm.php: No contact for from=$from found!");
	die("No contact for from=$from found!\n");
}
