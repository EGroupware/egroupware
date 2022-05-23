<?php
/**
 * API: loading styles for TinyMCE incl. users preferred font and -size
 *
 * @link www.egroupware.org
 * @author Ralf Becker <rb-at-egroupware.org>
 * @package api
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

// switch evtl. set output-compression off, as we can't calculate a Content-Length header with transparent compression
ini_set('zlib.output_compression', 0);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'api',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

// release session, as we don't need it, and it blocks parallel requests
$GLOBALS['egw']->session->commit_session();

// use an etag over user prefs and modification time of HtmlArea
$etag = '"'.md5(json_encode(array_intersect_key($GLOBALS['egw_info']['user']['preferences']['common'],
	array_flip(['rte_font', 'rte_font_size', 'rte_font_unit']))).filemtime(__DIR__.'/src/Etemplate/Widget/HtmlArea.php')).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
Api\Session::cache_control(86400);	// cache for 1 day
Header('Content-Type: text/css');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag)
{
	header("HTTP/1.1 304 Not Modified");
	exit;
}

$content = Api\Etemplate\Widget\HtmlArea::contentCss();

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;