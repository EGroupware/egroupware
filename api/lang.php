<?php
/**
 * API: loading translation from server
 *
 * Usage: /egroupware/api/lang.php?app=infolog&lang=de
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

// just to be sure, noone tries something nasty ...
if (!preg_match('/^[a-z0-9_]+$/i', $_GET['app'])) die('No valid application-name given!');
if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $_GET['lang'])) die('No valid lang-name given!');

// switch evtl. set output-compression off, as we cant calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'api',
		'noheader' => true,
		'load_translations' => false,	// do not automatically load translations
		'nocachecontrol' => true,
	)
);

try
{
	include('../header.inc.php');
}
catch (\EGroupware\Api\Exception\NoPermission\App $e)
{
	// ignore missing run rights for an app, as translations of other apps are loaded sometimes without run rights
}
// release session, as we dont need it and it blocks parallel requests
$GLOBALS['egw']->session->commit_session();

// use an etag with app, lang and a hash over the creation-times of all lang-files
$etag = '"'.$_GET['app'].'-'.$_GET['lang'].'-'.  Api\Translation::etag($_GET['app'], $_GET['lang']).'"';

// headers to allow caching, we specify etag on url to force reload, even with Expires header
Api\Session::cache_control(864000);	// cache for 10 days
Header('Content-Type: text/javascript; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	exit;
}

Api\Translation::init(false);
Api\Translation::add_app($_GET['app'], $_GET['lang']);
if (!count(Api\Translation::$lang_arr))
{
	Api\Translation::add_app($_GET['app'], 'en');
}

$content = "";
// fix for phrases containing \n
$content .= 'egw.set_lang_arr("'.$_GET['app'].'", '.str_replace('\\\\n', '\\n',
	json_encode(Api\Translation::$lang_arr, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).
	', egw && egw.window !== window);';

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;
