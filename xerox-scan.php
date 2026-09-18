<?php
/**
 * Xerox (and similar MFP) "Scan to HTTP/HTTPS" CGI-script bridge
 *
 * NOT WebDAV, NOT a generic file-upload form - this implements the CGI-script protocol Xerox
 * WorkCentre/AltaLink/VersaLink devices expect when their Workflow/Network Scanning is configured
 * with an "HTTP/HTTPS" file repository. On the device, set:
 * - Server/Address: this EGroupware server
 * - Script Name and Path: /xerox-scan.php/home/<user>/<optional-subpath>/ (like webdav.php's own
 *   home-directory URLs)
 * - Document Path: a real destination FOLDER under that (relative), NOT a filename
 *
 * @see EGroupware\Api\Vfs\XeroxScan for the actual protocol implementation and field names.
 *
 * For Apache FCGI you need the following rewrite rule (same reason as webdav.php):
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 */

use EGroupware\Api;
use EGroupware\Api\Vfs;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'disable_Template_class' => true,
		'noheader'  => true,
		'currentapp' => 'filemanager',
		'autocreate_session_callback' => static function(&$account)
		{
			if (isset($_GET['auth']))
			{
				list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode($_GET['auth']), 2);
			}
			return Api\Header\Authenticate::autocreate_session_callback($account);
		},
		'no_exception_handler' => 'basic_auth',
		'auth_realm' => 'EGroupware Scan-to-HTTP',
	)
);

require_once __DIR__.'/header.inc.php';

// stateless, like webdav.php: don't keep the session open across scanner requests
$GLOBALS['egw']->session->commit_session();

(new Vfs\XeroxScan())->run();
