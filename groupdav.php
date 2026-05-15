<?php
/**
 * EGroupware - CalDAV/CardDAV/GroupDAV server
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;

// check we either have a session cookie, or an Authorization header, otherwise directly return 401 Unauthorized
if (!preg_match('#/groupdav.php/openapi.json($|\?)#', $_SERVER['REQUEST_URI']) &&
	empty($_COOKIE['sessionid']) && empty($_SERVER['PHP_AUTH_DIGEST']))
{
	error_log($_SERVER['REQUEST_METHOD'].' '.(empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'].
		(isset($_SERVER['HTTP_AUTHORIZATION']) ? ': Authorization: '.$_SERVER['HTTP_AUTHORIZATION'] : ': sessionid='.($_COOKIE['sessionid']??'NULL')).
		': '.($_SERVER['X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'NULL').' --> 401 Unauthorized');

	header('WWW-Authenticate: Basic realm="EGroupware CalDAV/CardDAV/GroupDAV server"');   // cant use CalDAV::REALM as autoloading and include path not yet setup!
	http_response_code(401);
	die("Unauthorized, you need to authenticate first!\n");
}

$starttime = microtime(true);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => $_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('#/groupdav.php/openapi.json($|\?)#', $_SERVER['REQUEST_URI']) ? 'login' : 'groupdav',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'autocreate_session_callback' => 'EGroupware\\Api\\Header\\Authenticate::autocreate_session_callback',
		'auth_realm' => 'EGroupware CalDAV/CardDAV/GroupDAV server',	// cant use CalDAV::REALM as autoloading and include path not yet setup!
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
include(__DIR__.'/header.inc.php');

$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();

$headertime = microtime(true);

$caldav = new Api\CalDAV();
$caldav->ServeRequest();
//error_log(sprintf('GroupDAV %s: status "%s", took %5.3f s'.($headertime?' (header include took %5.3f s)':''),$_SERVER['REQUEST_METHOD'].($_SERVER['REQUEST_METHOD']=='REPORT'?' '.$groupdav->propfind_options['root']['name']:'').' '.$_SERVER['PATH_INFO'],$groupdav->_http_status,microtime(true)-$starttime,$headertime-$starttime));