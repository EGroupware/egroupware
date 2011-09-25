<?php
/**
 * EGroupware - CalDAV/CardDAV/GroupDAV server
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

// switching off output compression for Lighttpd and HTTPS, as it makes problems with TB Lightning
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' &&
	strpos($_SERVER['SERVER_SOFTWARE'],'lighttpd/1.4') === 0 &&
	strpos($_SERVER['HTTP_USER_AGENT'],'Lightning') !== false)
{
	ini_set('zlib.output_compression',0);
}
//error_log("HTTPS='$_SERVER[HTTPS]', SERVER_SOFTWARE='$_SERVER[SERVER_SOFTWARE]', HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]', REQUEST_METHOD='$_SERVER[REQUEST_METHOD]' --> zlib.output_compression=".ini_get('zlib.output_compression'));

$starttime = microtime(true);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'groupdav',
		'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
		'autocreate_session_callback' => array('egw_digest_auth','autocreate_session_callback'),
		'auth_realm' => 'EGroupware CalDAV/CardDAV/GroupDAV server',	// cant use groupdav::REALM as autoloading and include path not yet setup!
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(__FILE__);
require_once($egw_dir.'/phpgwapi/inc/class.egw_digest_auth.inc.php');
include($egw_dir.'/header.inc.php');

$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();

$headertime = microtime(true);

$groupdav = new groupdav();
$groupdav->ServeRequest();
error_log(sprintf('GroupDAV %s request: status "%s", took %5.3f s'.($headertime?' (header include took %5.3f s)':''),$_SERVER['REQUEST_METHOD'].($_SERVER['REQUEST_METHOD']=='REPORT'?' '.$groupdav->propfind_options['root']['name']:'').' '.$_SERVER['PATH_INFO'],$groupdav->_http_status,microtime(true)-$starttime,$headertime-$starttime));
