<?
  /**************************************************************************\
  * phpGroupWare API - Graphical                                             *
  * This file written by Lars Kneschke <knecke@phpgroupware.org>             *
  * Allows the creation of graphical buttons                                 *
  * Copyright (C) 2001 Lars Kneschke                                         *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

class graphics
{
	// put a valid font here
	var $font="/opt/future-project/src/management-server/ttf/arial.ttf";
	
	function createButton($_text, $_fontsize=11)
	{
		// create filename
		$filename="button_".md5($_text).".png";
		$filename=strtolower($filename);
		
		// see if file exists already, we cache it
		if (file_exists(PHPGW_IMAGES_FILEDIR.'/'.$filename)) return $filename;
		
		$size = imagettfbbox($_fontsize,0,$this->font,$_text);
		$dx = abs($size[2]-$size[0]);
		$dy = abs($size[5]-$size[3]);
		$xpad=9;
		$ypad=9;
		$im = imagecreate($dx+$xpad,$dy+$ypad);
		$blue = ImageColorAllocate($im, 0x2c,0x6D,0xAF);
		$black = ImageColorAllocate($im, 0,0,0);
		$white = ImageColorAllocate($im, 255,255,255);
		ImageRectangle($im,0,0,$dx+$xpad-1,$dy+$ypad-1,$black);
		ImageRectangle($im,0,0,$dx+$xpad,$dy+$ypad,$white);
		ImageTTFText($im, $_fontsize, 0, (int)($xpad/2)+1, $dy+(int)($ypad/2), -$black, $this->font, $_text);
		ImageTTFText($im, $_fontsize, 0, (int)($xpad/2), $dy+(int)($ypad/2)-1, -$white, $this->font, $_text);
		ImagePNG($im,PHPGW_IMAGES_FILEDIR.'/'.$filename);
		ImageDestroy($im);
		
		return $filename;
	}
}
