<?php
/**
 * eGroupWare Calendar: rounded corners
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package calendar
 * @copyright (c) 2004-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once('../../phpgwapi/inc/common_functions.inc.php');
check_load_extension('gd',true);	// true = throw exception if not loadable

foreach(array('width'=>-20,'height'=>40,'border'=>1,'color'=>'000080','bgcolor'=>'0000FF') as $name => $default)
{
	$$name = isset($_GET[$name]) ? $_GET[$name] : $default;
}

$image = @imagecreate(abs($width),abs($height))
	or die("Cannot Initialize new GD image stream");

$white = imagecolorallocate($image, 254, 254, 254);
imagecolortransparent($image, $white);

foreach(array('color','bgcolor') as $name)
{
	preg_match('/^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/',$$name,$rgb) or
		die("Wrong value '".htmlspecialchars($$name)."' for $name, should be something like #80FFFF' !!!");

	$$name = imagecolorallocate($image,hexdec($rgb[1]),hexdec($rgb[2]),hexdec($rgb[3]));
}
$radius = min(abs($width),abs($height));
$center_x = $width > 0 ? abs($width)-$radius-1 : $radius;
$center_y = $height < 0 ? abs($height)-$radius-1 : $radius;
//echo "width=$width, height=$height => radius=$radius: center_x=$center_x, center_y=$center_y";
if ($border) imagefilledellipse($image,$center_x,$center_y,2*$radius,2*$radius,$color);
imagefilledellipse($image,$center_x,$center_y,2*($radius-$border),2*($radius-$border),$bgcolor);

if (abs($height) > abs($width))
{
	if ($height < 0)	// lower corners
	{
		$y1 = 0;
		$y2 = abs($height)-$radius-1;
	}
	else
	{
		$y1 = $radius;
		$y2 = abs($height)-1;
	}
	imagefilledrectangle($image,0,$y1,abs($width),$y2,$bgcolor);
	if ($border)
	{
		$x1 = $width < 0 ? 0 : abs($width)-$border;
		$x2 = $width < 0 ? $border-1 : abs($width)-1;
		imagefilledrectangle($image,$x1,$y1,$x2,$y2,$color);
	}
}

session_cache_limiter('public');	// allow caching
if (function_exists('imagegif'))
{
	header("Content-type: image/gif");
	imagegif($image);
}
else
{
	header("Content-type: image/png");
	imagepng($image);
}
imagedestroy($image);
