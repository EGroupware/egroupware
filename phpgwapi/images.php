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

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'home',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

$image_map = common::image_map(preg_match('/^[a-z0-9_-]+$/i',$_GET['template']) ? $_GET['template'] : null);

// use an etag over the image mapp
$etag = '"'.md5(serialize($image_map)).'"';

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

echo 'egw.set_images('.json_encode($image_map).");\n";

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.ob_get_length());