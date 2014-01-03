<?php
/**
 * API: loading available images by application and image-name (without extension)
 *
 * Usage: /egroupware/phpgwapi/images.php?template=idots
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package phpgwapi
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

$content = common::image_map(preg_match('/^[a-z0-9_-]+$/i',$_GET['template']) ? $_GET['template'] : null, $_GET['svg']);

// use an etag over the image mapp
$etag = '"'.md5(serialize($content)).'"';

// headers to allow caching
Header('Content-Type: text/javascript; charset=utf-8');
Header('Cache-Control: public, no-transform');
Header('Pragma: cache');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	common::egw_exit();
}

$content = 'egw.set_images('.json_encode($content).");\n";

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;