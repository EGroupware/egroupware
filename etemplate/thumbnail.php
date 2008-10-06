<?php
/**
* eGroupWare - eTemplates
*
* @link http://www.egroupware.org
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
* @author Nathan Gray
* @package etemplate
* @version $Id$
*/

if (isset($_GET['app']))
{
	$app = $_GET['app'];
}
elseif(isset($_GET['path']))
{
	list(,$apps,$app) = explode('/',$_GET['path']);
	if ($apps !== 'apps') $app = 'filemanager';
}
if (!preg_match('/^[a-z0-9_-]+$/i',$app)) die('Stop');	// just to prevent someone doing nasty things

$GLOBALS['egw_info']['flags'] = array(
	'currentapp'	=>	$app,
	'noheader'	=>	true,
	'nonavbar'	=>	true
);
include ('../header.inc.php');

// strip slashes from _GET parameters, if someone still has magic_quotes_gpc on
if (get_magic_quotes_gpc() && $_GET)
{
	$_GET = etemplate::array_stripslashes($_GET);
}

if (isset($_GET['path']))
{
	$g_srcfile = egw_vfs::PREFIX.$_GET['path'];
}
else
{
	$g_srcfile = egw_link::vfs_path($_GET['app'],$_GET['id'],$_GET['file']);
}
$g_dstfile = $GLOBALS['egw_info']['server']['temp_dir'] . '/egw-thumbs'.parse_url($g_srcfile,PHP_URL_PATH);

// Check for existing thumbnail
if(file_exists($g_dstfile) && filemtime($g_dstfile) >= filemtime($g_srcfile)) {
	header('Content-Type: image/png');
	readfile($g_dstfile);
	return;
}

$thumbnail = get_thumbnail($file, true);

if($thumbnail) {
	header('Content-Type: image/png');
	imagepng( $thumbnail );
	imagedestroy($thumbnail);
}

/**
* Private function to get a thumbnail image for a linked image file.
*
* This function creates a thumbnail of the given image, if possible, and stores it in $GLOBALS['egw_info']['server']['temp_dir'].
* Returns the image, or false if the file could not be thumbnailed.  Thumbnails are PNGs.
*
* @param array $file VFS File array to thumbnail
* @return image or false
*
* @author Nathan Gray
*/
function get_thumbnail($file, $return_data = true)
{
	global $g_srcfile,$g_dstfile;

	$max_width = $max_height = (string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] == '' ? 32 :
		$GLOBALS['egw_info']['server']['link_list_thumbnail'];

	//error_log(__METHOD__."() src=$g_srcfile, dst=$g_dstfile, size=$max_width");

	if($max_width == 0) {
		// thumbnailing disabled
		return false;
	} elseif( !gdVersion() ) {
		// GD disabled or not installed
		return false;
	}

	// Quality
	$g_imgcomp=55;

	$dst_dir = dirname($g_dstfile);
	// files dont exist, if you have no access permission
	if((file_exists($dst_dir) || mkdir($dst_dir, 0700, true)) && file_exists($g_srcfile)) {
		$g_is=getimagesize($g_srcfile);
		if($g_is[0] < $max_width && $g_is[1] < $max_height) {
			$g_iw = $g_is[0];
			$g_ih = $g_is[1];
		} elseif(($g_is[0]-$max_width)>=($g_is[1]-$max_height)) {
			$g_iw=$max_width;
			$g_ih=($max_width/$g_is[0])*$g_is[1];
		} else {
			$g_ih=$max_height;
			$g_iw=($g_ih/$g_is[1])*$g_is[0];
		}

		// Get mime type
		list($type, $image_type) = explode('/',egw_vfs::mime_content_type($g_srcfile));
		if($type != 'image') {
			return false;
		}

		switch ($image_type) {
			case 'png':
				$img_src = imagecreatefrompng($g_srcfile);
				break;
			case 'jpg':
			case 'jpeg':
				$img_src = imagecreatefromjpeg($g_srcfile);
				break;
			case 'gif':
				$img_src = imagecreatefromgif($g_srcfile);
				break;
			case 'bmp':
				$img_src = imagecreatefromwbmp($g_srcfile);
				break;
			default:
				return false;
		}
		if(!($gdVersion = gdVersion())) {
			return false;
		} elseif ($gdVersion >= 2) {
			$img_dst=imagecreatetruecolor($g_iw,$g_ih);
			imageSaveAlpha($img_dst, true);
			$trans_color = imagecolorallocatealpha($img_dst, 0, 0, 0, 127);
			imagefill($img_dst, 0, 0, $trans_color);
		} else {
			$img_dst = imagecreate($g_iw, $g_ih);
		}

		imagecopyresampled($img_dst, $img_src, 0, 0, 0, 0, $g_iw, $g_ih, $g_is[0], $g_is[1]);
		imagepng($img_dst, $g_dstfile);
		return $return_data ? $img_dst : $g_dstfile;
	} else {
		return false;
	}
}

/**
* Get which version of GD is installed, if any.
*
* Returns the version (1 or 2) of the GD extension.
* Off the php manual page, thanks Hagan Fox
*/
function gdVersion($user_ver = 0)
{
	if (! extension_loaded('gd')) { return; }
	static $gd_ver = 0;

	// Just accept the specified setting if it's 1.
	if ($user_ver == 1) { $gd_ver = 1; return 1; }

	// Use the static variable if function was called previously.
	if ($user_ver !=2 && $gd_ver > 0 ) { return $gd_ver; }

	// Use the gd_info() function if possible.
	if (function_exists('gd_info')) {
		$ver_info = gd_info();
		preg_match('/\d/', $ver_info['GD Version'], $match);
		$gd_ver = $match[0];
		return $match[0];
	}

	// If phpinfo() is disabled use a specified / fail-safe choice...
	if (preg_match('/phpinfo/', ini_get('disable_functions'))) {
		if ($user_ver == 2) {
			$gd_ver = 2;
			return 2;
		} else {
			$gd_ver = 1;
			return 1;
		}
	}
	// ...otherwise use phpinfo().
	ob_start();
	phpinfo(8);
	$info = ob_get_contents();
	ob_end_clean();
	$info = stristr($info, 'gd version');
	preg_match('/\d/', $info, $match);
	$gd_ver = $match[0];
	return $match[0];
}
