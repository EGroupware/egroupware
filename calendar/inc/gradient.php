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

foreach(array('width'=>1,'height'=>1,'color1'=>'000080','color2'=>'ffffff') as $name => $default)
{
	$$name = isset($_GET[$name]) ? $_GET[$name] : $default;
}

foreach(array('color1','color2') as $name)
{
	preg_match('/^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/',$$name,$rgb) or
		die("Wrong value '".$$name."' for $name, should be something like #80FFFF' !!!");

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
