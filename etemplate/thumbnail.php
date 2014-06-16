<?php
/**
* eGroupWare - eTemplates
*
* @link http://www.egroupware.org
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
* @author Nathan Gray
* @author Andreas Stöckel
* @package etemplate
* @version $Id$
*/


//Set all necessary info and fire up egroupware
$GLOBALS['egw_info']['flags'] = array(
	'currentapp'	=>	get_app(),
	'noheader'	=>	true,
	'nonavbar'	=>	true
);
include ('../header.inc.php');

// strip slashes from _GET parameters, if someone still has magic_quotes_gpc on
if (get_magic_quotes_gpc() && $_GET)
{
	$_GET = array_stripslashes($_GET);
}

// no need to keep the session open (it stops other parallel calls)
$GLOBALS['egw']->session->commit_session();

if (!read_thumbnail(get_srcfile()))
{
	header('404 Not found');
}

/**
 * Reads the source file from the path parameters
 */
function get_srcfile()
{
	if (isset($_GET['path']))
	{
		$g_srcfile = $_GET['path'];
	}
	else
	{
		$g_srcfile = egw_link::vfs_path($_GET['app'], $_GET['id'], $_GET['file'], true);
	}

	return egw_vfs::PREFIX.$g_srcfile;
}

/**
 * Returns the currently active app.
 */
function get_app()
{
	if (isset($_GET['app']))
	{
		$app = $_GET['app'];
	}
	elseif (isset($_GET['path']))
	{
		list(, $apps, $app) = explode('/', $_GET['path']);
		if ($apps !== 'apps' || !isset($GLOBALS['egw_info']['user']['apps'][$app]))
		{
			$app = 'filemanager';
		}
	}

	if (!preg_match('/^[a-z0-9_-]+$/i',$app))
	{
		die('Stop');	// just to prevent someone doing nasty things
	}

	return $app;
}

/**
 * Returns the maximum width/height of a thumbnail
 */
function get_maxsize()
{
	$preset = (string)$GLOBALS['egw_info']['server']['link_list_thumbnail'] == '' ? 32 :
		$GLOBALS['egw_info']['server']['link_list_thumbnail'];

	// Another maximum size may be passed if thumbnails are turned on
	if ($preset != 0 && isset($_GET['thsize']) && is_numeric($_GET['thsize']))
	{
		$preset = (int)$_GET['thsize'];
	}

	return $preset;
}

/**
 * Either loads the thumbnail for the given file form cache or generates a new
 * one
 *
 * @param string $file is the file of which a thumbnail should be created
 * @returns false if the file doesn't exist or any other error occured.
 */
function read_thumbnail($src)
{
	//Check whether the source file is readable and exists
	if (!file_exists($src) || !egw_vfs::is_readable($src))
	{
		return false;
	}

	// Get the maxsize of an thumbnail. If thumbnailing is turned off, the value
	// will be 0
	$maxsize = get_maxsize();

	// Generate the destination filename and check whether the destination directory
	// had been successfully created (the cache class used in gen_dstfile does that).
	$dst = gen_dstfile($src, $maxsize);
	$dst_dir = dirname($dst);
	if(file_exists($dst_dir))
	{
		// Check whether the destination file already exists and is newer than
		// the source file. Assume the file doesn't exist if thumbnailing is turned off.
		$exists = ($maxsize > 0) && (file_exists($dst) && filemtime($dst) >= filemtime($src));

		// Only generate the thumbnail if the destination file does not match the
		// conditions mentioned above. Abort if $maxsize is 0.
		$gen_thumb = ($maxsize > 0) && (!$exists);
		if ($gen_thumb && ($thumb = gd_image_thumbnail($src, $maxsize, $maxsize)))
		{
			// Save the file to disk...
			imagepng($thumb, $dst);

			// Previous versions generated a new copy of the png to output it -
			// as encoding pngs is quite cpu-intensive I think it might
			// be better to just read it from the temp dir again - as it is probably
			// still in the fs-cache
			$exists = true;

			imagedestroy($thumb);
		}

		$output_mime = 'image/png';

		// If some error occured during thumbnail generation or thumbnailing is turned off,
		// simply output the mime type icon
		if (!$exists)
		{
			$mime = egw_vfs::mime_content_type($src);
			list($app, $icon) = explode('/', egw_vfs::mime_icon($mime), 2);
			list(, $path) = explode($GLOBALS['egw_info']['server']['webserver_url'],
				$GLOBALS['egw']->common->image($app, $icon), 2);
			$dst = EGW_SERVER_ROOT.$path;
			$output_mime = mime_content_type($dst);
		}

		if ($dst)
		{
			header('Content-Type: '.$output_mime);
			readfile($dst);
			return true;
		}
	}

	return false;
}

function gen_dstfile($src, $maxsize)
{
	// Use the egroupware file cache to store the thumbnails on a per instance
	// basis
	$cachefile = new egw_cache_files(array());
	return $cachefile->filename(egw_cache::keys(egw_cache::INSTANCE, 'etemplate',
		'thumb_'.md5($src.$maxsize).'.png'), true);
}

/**
 * Function which calculates the sizes of an image with the width w and the height
 * h, which should be scaled to an thumbnail of the maximum dimensions maxw and
 * maxh
 *
 * @param int $w original width of the image
 * @param int $h original height of the image
 * @param int $maxw maximum width of the image
 * @param int $maxh maximum height of the image
 * @returns an array with two elements, w, h or "false" if the original dimensions
 *   of the image are that "odd", that one of the output sizes is smaller than one pixel.
 *
 * TODO: As this is a general purpose function, it might probably be moved
 *   to some other php file or an "image utils" class.
 */
function get_scaled_image_size($w, $h, $maxw, $maxh)
{
	//Set the output width to zero
	$wout = 0.0;
	$hout = 0.0;

	//Scale will contain the factor by which the image has to be scaled down
	$scale = 1.0;

	//Select the constraining dimension
	if ($w > $h) //The constraining factor will be $maxw
	{
		$scale = $maxw / $w;
	}
	else //The constraning factor will be $maxh
	{
		$scale = $maxh / $h;
	}

	// Don't scale images up
	if ($scale > 1.0)
	{
		$scale = 1.0;
	}

	$wout = $w * $scale;
	$hout = $h * $scale;

	//Return the calculated values
	if ($wout < 1 || $hout < 1)
	{
		return false;
	}
	else
	{
		return array(round($wout), round($hout));
	}
}

/**
 * Loads the given imagefile - returns "false" if the file wasn't an image,
 * otherwise the gd-image is returned.
 *
 * @param string $file the file which to load
 * @returns false or a gd_image
 */
function gd_image_load($file)
{
	// Get mime type
	list($type, $image_type) = explode('/', egw_vfs::mime_content_type($file));

	// Call the according gd constructor depending on the file type
	if($type == 'image')
	{
		switch ($image_type)
		{
			case 'png':
				return imagecreatefrompng($file);
			case 'jpg':
			case 'jpeg':
				return imagecreatefromjpeg($file);
			case 'gif':
				return imagecreatefromgif($file);
			case 'bmp':
				return imagecreatefromwbmp($file);
		}
	}

	return false;
}

/**
 * Create an gd_image with transparent background.
 *
 * @param int $w the width of the resulting image
 * @param int $h the height of the resutling image
 */
function gd_create_transparent_image($w, $h)
{
	if (!($gdVersion = gdVersion()))
	{
		//Looking up the gd version failed, return false
		return false;
	}
	elseif ($gdVersion >= 2)
	{
		//Create an 32-bit image and fill it with transparency.
		$img_dst = imagecreatetruecolor($w, $h);
		imageSaveAlpha($img_dst, true);
		$trans_color = imagecolorallocatealpha($img_dst, 0, 0, 0, 127);
		imagefill($img_dst, 0, 0, $trans_color);

		return $img_dst;
	}
	else
	{
		//Just crate a simple image
		return imagecreate($w, $h);
	}
}

/**
 * Creates a scaled version of the given image - returns the gd-image or false if the
 * process failed.
 *
 * @param string $file the filename of the file
 * @param int $maxw the maximum width of the thumbnail
 * @param int $maxh the maximum height of the thumbnail
 * @return the gd_image or false if one of the steps taken to produce the thumbnail
 *   failed.
 */
function gd_image_thumbnail($file, $maxw, $maxh)
{
	//Load the image
	if (($img_src = gd_image_load($file)) !== false)
	{
		//Get the constraints of the image
		$w = imagesx($img_src);
		$h = imagesy($img_src);

		//Calculate the actual size of the thumbnail
		$scaled = get_scaled_image_size($w, $h, $maxw, $maxh);
		if ($scaled !== false)
		{
			list($sw, $sh) = $scaled;

			//Now scale it down
			$img_dst = gd_create_transparent_image($sw, $sh);
			imagecopyresampled($img_dst, $img_src, 0, 0, 0, 0, $sw, $sh, $w, $h);
			return $img_dst;
		}
	}

	return false;
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
