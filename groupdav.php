<?php
/**
 * eGroupWare - GroupDAV access
 *
 * Using the PEAR HTTP/WebDAV/Server class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

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
require_once('phpgwapi/inc/class.egw_digest_auth.inc.php');
include(dirname(__FILE__).'/header.inc.php');

$headertime = microtime(true);

$groupdav = new groupdav();
$groupdav->ServeRequest();
//error_log(sprintf("GroupDAV %s request took %5.3f s (header include took %5.3f s)",$_SERVER['REQUEST_METHOD'],microtime(true)-$starttime,$headertime-$starttime));
