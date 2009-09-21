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

foreach(array('width'=>1,'height'=>1,'color1'=>'000080','color2'=>'ffffff') as $name => $default)
{
	$$name = isset($_GET[$name]) ? $_GET[$name] : $default;
}

foreach(array('color1','color2') as $name)
{
	preg_match('/^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/',$$name,$rgb) or
		die("Wrong value '".htmlspecialchars($$name)."' for $name, should be something like #80FFFF' !!!");

	$$name = array('r'=>hexdec($rgb[1]),'g'=>hexdec($rgb[2]),'b'=>hexdec($rgb[3]));
}
$image = @imagecreate(abs($width),abs($height))
	or die("Cannot Initialize new GD image stream");

$length = max($width,$height);
$dist = $length / 256;
if ($dist < 1) $dist = 1;
$anz = round($length / $dist);
foreach ($color1 as $c => $val)
{
	$c_step[$c] = ($color2[$c] - $val) / $anz;
}

$rgb = $color1;
for ($l = 0; $l < $length; $l += $dist)
{
	$color = imagecolorallocate($image,(int)$rgb['r'],(int)$rgb['g'],(int)$rgb['b']);
	foreach($rgb as $c => $val)
	{
		$rgb[$c] += $c_step[$c];
	}
	if ($width > $height)
	{
		imagefilledrectangle($image,(int)$l,0,(int) ($l+$dist),$height-1,$color);
	}
	else
	{
		imagefilledrectangle($image,0,(int)$l,$width-1,(int) ($l+$dist),$color);
	}
}

// allow caching for 7 days
header('Cache-Control: public');
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+7*24*60*60) . ' GMT');

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
