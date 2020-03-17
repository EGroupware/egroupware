<?php
/**
 * EGroupware - Anonymous images for login page
 *
 * Images are store in EGroupware files-directory in subdirectory "anon-images"
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb-at-egroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage login
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array('flags' => array(
	'disable_Template_class'  => True,
	'noheader'                => True,
	// misuse session creation callback to send the image, in case we have no session
	'autocreate_session_callback' => 'send_image',
	'currentapp'              => 'api',
));

require('../header.inc.php');

send_image();

function send_image()
{
	$path = $GLOBALS['egw_info']['server']['files_dir'] . '/anon-images';

	if (!file_exists($path) || empty($_GET['src']) ||
		basename($_GET['src']) !== $_GET['src'] ||    // make sure no directory traversal
		!preg_match('/^[a-z 0-9._-]+\.(jpe?g|png|gif|svg|ico)$/i', $_GET['src']) ||    // only allow images, not eg. Javascript!
		!file_exists($path .= '/' . $_GET['src']) ||
		!($fp = fopen($path, 'r')))
	{
		error_log(__FILE__ . ": _GET[src]='$_GET[src]', path=$path returning HTTP status 404 Not Found");
		http_response_code(404);
	}
	else
	{
		Api\Session::cache_control(864000);    // 10 days
		$size = filesize($path);
		header('ETag: "' . md5($_GET['src'] . $size . filemtime($path)) . '"');
		header('Content-Type: ' . Api\MimeMagic::filename2mime($_GET['src']));
		header('Content-Length: ' . $size);
		fpassthru($fp);
		fclose($fp);
	}
	exit;
}
