<?php
/**
 * Load holidays from server
 *
 * Called from calendar_view widget to get cachable holiday list.
 * It's broken out into a separate file to get around anonymous user not having
 * calendar run rights in sitemgr.
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'api',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';

$cal_bo = new calendar_bo();
$holidays = json_encode($cal_bo->read_holidays((int)$_GET['year']));

// use an etag over holidays
$etag = '"'.md5($holidays).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
Api\Session::cache_control(86400);	// cache for one day
Header('Content-Type: application/json; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	exit;
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($holidays));
echo $holidays;