<?php
//
// SourceForge Knowledge Base Module v.1.0.0
// 
// Created by Patrick Walsh (pjw@users.sourceforge.net) 6/00
// Copyright (c) ... aw, hell, copy all the code you want
//
// $Id$

/*
	This code was adapted from Rasmus Lerdorf's article on PHPBuilder
	http://www.phpbuilder.com/columns/rasmus19990124.php3
*/

function openGif($filename) {

	if (!$filename) { $filename = "navbar.gif"; }
        $im = @imagecreatefromgif($filename);
	if ($im == "") { /* test for success of file creation */
		$im = imagecreate(300,15); /* Create a blank image */
		$bgc = imagecolorallocate($im, 255, 255, 255);
		$tc = imagecolorallocate($im, 0, 0, 0);
		imagefilledrectangle($im, 0, 0, 300, 15, $bgc);
		imagestring($im,1,2,2,"Error loading $filename", $tc);
	}
	return $im;
}

function getRGB($web_color) {
	if (strlen($web_color) != 6) {
		return false;
	} else {
		$retval["r"] = hexdec(substr($web_color,0,2));
		$retval["g"] = hexdec(substr($web_color,2,2));
		$retval["b"] = hexdec(substr($web_color,4,2));
		return $retval;
	}
}
  $phpgw_info = array();
  $phpgw_info["flags"]["currentapp"] = "admin";
  $phpgw_info["flags"]["nonavbar"] = True;
  $phpgw_info["flags"]["noheader"] = True;
  include("../header.inc.php");

	
  Header( "Content-type: image/gif");

  $border = 1;

//echo $filename;
  $im = openGif($filename); /* Open the provided file */
  $bg = getRGB($phpgw_info["theme"]["navbar_bg"]); /* get navbar theme */
  $fg = getRGB($phpgw_info["theme"]["navbar_text"]);
  $navbar_bg = ImageColorAllocate($im, $bg["r"], $bg["g"], $bg["b"]);
  $navbar_fg = ImageColorAllocate($im, $fg["r"], $fg["g"], $fg["b"]);

  $dk_gray = ImageColorAllocate($im, 128, 128, 128);
  $lt_gray = ImageColorAllocate($im, 192, 192, 192);

  $dx = ImageSX($im);  /* get image size */
  $dy = ImageSY($im);

  ImageFilledRectangle($im,0, 0, $dx, $border,$dk_gray); /* top */
  ImageFilledRectangle($im,0, 0, $border, $dy,$dk_gray); /* left */
  ImageFilledRectangle($im,$dx-$border-1, 0, $dx, $dy,$lt_gray); /* right */
  ImageFilledRectangle($im,0, $dy-$border-1, $dx, $dy,$lt_gray); /* bottom */

  //ImageGif($im,"$DOCUMENT_ROOT/kb/xml/$filename");

  ImageGif($im);
	
  ImageDestroy($im);
?>

