<?php
/**
 * API: loading user preferences and data
 *
 * Usage: /egroupware/phpgwapi/lang.php?user=123
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'home',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

// use an etag over config and link-registry
$preferences = json_encode($GLOBALS['egw_info']['user']['preferences']['common']);
$ab_preferences = json_encode($GLOBALS['egw_info']['user']['preferences']['addressbook']);
$user = $GLOBALS['egw']->accounts->json($GLOBALS['egw_info']['user']['account_id']);
$etag = '"'.md5($preferences.$ab_preferences.$user).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
egw_session::cache_control(86400);	// cache for 1 day
Header('Content-Type: text/javascript; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	common::egw_exit();
}

$content = 'egw.set_preferences('.$preferences.", 'common', egw && egw.window !== window);\n";
$content .= 'egw.set_preferences('.$ab_preferences.", 'addressbook', egw && egw.window !== window);\n";
$content .= 'egw.set_user('.$user.", egw && egw.window !== window);\n";

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;
