<?php
	/**************************************************************************\
	* eGroupWare - calendar: rounded corners                                      *
	* http://www.egroupware.org                                                *
	* Written by RalfBecker@outdoor-training.de                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

// some constanst for pre php4.3
if (!defined('PHP_SHLIB_SUFFIX'))
{
	define('PHP_SHLIB_SUFFIX',strtoupper(substr(PHP_OS, 0,3)) == 'WIN' ? 'dll' : 'so');
}
if (!defined('PHP_SHLIB_PREFIX'))
{
	define('PHP_SHLIB_PREFIX',PHP_SHLIB_SUFFIX == 'dll' ? 'php_' : '');
}

if (!extension_loaded('gd') && !@dl(PHP_SHLIB_PREFIX.'gd.'.PHP_SHLIB_SUFFIX))
{
	die("Can't load the needed php-extension 'gd' !!!");
}

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
		die("Wrong value '".$$name."' for $name, should be something like #80FFFF' !!!");

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
?>
