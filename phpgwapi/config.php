<?php
/**
 * API: loading translation from from browser
 *
 * Usage: /egroupware/phpgwapi/lang.php?app=infolog&lang=de
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
$config = json_encode(config::clientConfigs());
$link_registry = egw_link::json_registry();
$etag = '"'.md5($config.$link_registry).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
egw_session::cache_control(86400);	// cache for one day
Header('Content-Type: text/javascript; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	common::egw_exit();
}

$content = 'egw.set_configs('.$config.");\n";
$content .= 'egw.set_link_registry('.$link_registry.");\n";

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;
