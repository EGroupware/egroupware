<?php
/**
 * API: loading categories and setting styles
 *
 * Usage: /egroupware/phpgwapi/categories.php[?app=calendar]
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @package API
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

// Get appname
$appname = $_GET['app'] && $GLOBALS['egw_info']['apps'][$_GET['app']] ? $_GET['app'] : categories::GLOBAL_APPNAME;

$cats = new categories('', $appname);
$categories = $cats->return_array('all',0, false, '', 'ASC','',$appname==categories::GLOBAL_APPNAME);

$content = "/* Category CSS for $appname */\n\n";

foreach($categories as $cat)
{
	if($cat['data']['color'])
	{
		// Use slightly more specific selector that just class, to allow defaults
		// if the category has no color
		$content .= "tr.cat_{$cat['id']}, div.cat_{$cat['id']}, span.cat_{$cat['id']} { background-color: {$cat['data']['color']};} /*{$cat['name']}*/\n";
	}
	if($cat['data']['icon'])
	{
		$content .= ".cat_{$cat['id']} .cat_icon { background-image: url('". admin_categories::icon_url($cat['data']['icon']) ."');} /*{$cat['name']}*/\n";
	}
}

// use an etag over categories
$etag = '"'.md5($content).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
egw_session::cache_control(86400);	// cache for 1 day
Header('Content-Type: text/css; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	common::egw_exit();
}

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;
