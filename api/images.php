<?php
/**
 * API: loading available images by application and image-name (without extension)
 *
 * Usage:
 * - /egroupware/api/images.php?template=idots
 * - /egroupware/api/images.php?template=pixelegg&debug=1 gives pretty-printed JSON
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
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

// release session, as we dont need it and it blocks parallel requests
$GLOBALS['egw']->session->commit_session();

$content = json_encode(Api\Image::map(preg_match('/^[a-z0-9_-]+$/i',$_GET['template']) ? $_GET['template'] : null),
	JSON_FORCE_OBJECT |	// export empty php-arrays as empty objects, not empty arrays
	JSON_UNESCAPED_SLASHES | // do not escape slashes, smaller and better readable
	(!empty($_GET['debug']) ? JSON_PRETTY_PRINT : 0));

// use an etag over the image mapp
$etag = '"'.md5($content).'"';

// headers to allow caching, egw_framework specifies etag on url to force reload, even with Expires header
Api\Session::cache_control(86400);	// cache for one day
Header('Content-Type: '.(empty($_GET['debug'])?'text/javascript':'application/json').'; charset=utf-8');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	exit;
}

if (empty($_GET['debug'])) $content = "\n\negw.set_images(".$content.", egw && egw.window !== window);\n";

// we run our own gzip compression, to set a correct Content-Length of the encoded content
if (in_array('gzip', explode(',',$_SERVER['HTTP_ACCEPT_ENCODING'])) && function_exists('gzencode'))
{
	$content = gzencode($content);
	header('Content-Encoding: gzip');
}

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.bytes($content));
echo $content;