<?php
	/*******************************************************************\
	* phpGroupWare - GD Button                                          *
	* http://www.phpgroupware.org                                       *
	*                                                                   *
	* Written by by Bettina Gille [ceb@phpgroupware.org]                *
	*                                                                   *
	* Creates graphical buttons by using GD and TTF fonts               *
	* Copyright (C) 2002 Bettina Gille                                  *
	* ----------------------------------------------------------------- *
	* Some methods based on former class.graphics                       *
	* Copyright (C) 2001 Lars Kneschke                                  *
	* ----------------------------------------------------------------- *
	* This library is part of the phpGroupWare API                      *
	* ----------------------------------------------------------------- *
	* This library is free software; you can redistribute it and/or     *
	* modify it under the terms of the GNU General Public License as    *
	* published by the Free Software Foundation; either version 2 of    *
	* the License, or (at your option) any later version.               *
	*                                                                   *
	* This program is distributed in the hope that it will be useful,   *
	* but WITHOUT ANY WARRANTY; without even the implied warranty of    *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU  *
	* General Public License for more details.                          *
	*                                                                   *
	* You should have received a copy of the GNU General Public License *
	* along with this program; if not, write to the Free Software       *
	* Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.         *
	\*******************************************************************/
	/* $Id$ */

	class gdbutton
	{
		var $ttf_font;
		var $image;

		var $bg_color;
		var $font_color;

		var $font_size;
		var $font_text;

		var $filename;

		var $url_base;

		function gdbutton()
		{
			$this->image		= 0;
			$this->font_size	= 0;
			$this->font_text	= '';
			$this->ttf_font		= PHPGW_SERVER_ROOT . SEP . 'phpgwapi' . SEP . 'fonts' . SEP . 'FreeSans.ttf';
			$this->filename		= '';

			$this->xspace		= 4;
			$this->yspace		= 4;

			$this->save_dir		= $GLOBALS['HTTP_SERVER_VARS']['DOCUMENT_ROOT'] . PHPGW_IMAGES_DIR . SEP;
			$this->img_dir		= PHPGW_IMAGES_DIR . SEP;
		}

		function button_init()
		{
			$this->image = ImageCreate($this->width,$this->height) or die;
			ImageColorAllocate($this->image,0x2c,0x6D,0xAF);
			return True;
		}

		function file_name()
		{
			$this->filename = 'phpgw_button_' . md5($this->font_text) . '.png';
			$this->filename = strtolower($this->filename);

			return True;
		}

		function gd_button()
		{
			if (file_exists($this->save_dir . $this->filename))
			{
				return $this->filename;
			}

			$this->font_size = 5;

			$text_width		= ImageFontWidth($this->font_size) * strlen($this->font_text);
			$text_height	= ImageFontHeight($this->font_size);

			$this->width	= ($this->xspace*2) + $text_width;
			$this->height	= $this->yspace + $text_height;

			$this->xpos = ($this->width/2) - ($text_width/2);

			if ($this->xpos < 0)
			{
				$this->xpos = $this->xspace;
			}

			$this->ypos = ($this->height/2) - ($text_height/2);

			if ($this->ypos < 0)
			{
				$this->ypos = $this->yspace;
			}

			$this->button_init();

			$black = ImageColorAllocate($this->image, 0,0,0);
			ImageRectangle($this->image,0,0,$this->width-1,$this->height-1,$black);

			$white = ImageColorAllocate($this->image, 255,255,255);
			ImageRectangle($this->image,0,0,$this->width,$this->height,$white);

			ImageString($this->image, $this->font_size, intval($this->xpos+1), intval($this->ypos), $this->font_text, $black);
			ImageString($this->image, $this->font_size, intval($this->xpos), intval($this->ypos-1), $this->font_text, $white);

			return $this->save_button();
		}

		function ttf_button()
		{
			if (file_exists($this->save_dir . $this->filename))
			{
				return $this->filename;
			}
			$this->font_size = 11;

			$size = imagettfbbox($this->font_size,0,$this->ttf_font,$this->font_text);
			$dx = abs($size[2]-$size[0]);
			$dy = abs($size[5]-$size[3]);

			$xpad = 10;
			$ypad = 10;

			$this->width	= ($xpad/2) + $xpad + $dx;
			$this->height	= $dy + $ypad;

			$this->button_init();

			$black = ImageColorAllocate($this->image, 0,0,0);
			ImageRectangle($this->image,0,0,$this->width-1,$this->height-1,$black);

			$white = ImageColorAllocate($this->image, 255,255,255);
			ImageRectangle($this->image,0,0,$this->width,$this->height,$white);

			ImageTTFText($this->image, $this->font_size, 0, intval($xpad/2)+1, $dy+intval($ypad/2), -$black, $this->ttf_font, $this->font_text);
			ImageTTFText($this->image, $this->font_size, 0, intval($xpad/2), $dy+intval($ypad/2)-1, -$white, $this->ttf_font, $this->font_text);

			return $this->save_button();
		}

		function save_button()
		{
			ImagePNG($this->image,$this->save_dir . $this->filename);
			ImageDestroy($this->image);

			return $this->filename;
		}

		function input_button($data)
		{
			if (is_array($data))
			{
				$this->font_text	= $data['font_text'];
				$button_name		= $data['button_name'];
			}

			$this->file_name();

			if (extension_loaded('gd') && $config['ttf'] == 'yes')
			{
				if (dl('gd.so'))
				{
					return '<input type="image" src="' . $this->img_dir . $this->ttf_button() . '" border="0" name="' . $button_name . '" value="' . $button_name . '">';
				}
			}
			elseif(extension_loaded('gd'))
			{
					return '<input type="image" src="' . $this->img_dir . $this->gd_button() . '" border="0" name="' . $button_name . '" value="' . $button_name . '">';
			}
			else
			{
				return '<input type="submit" value="' . $this->font_text . '" name="' . $button_name.'">';
			}
		}

		// this function checks, if there is a variable $aaa_x and $aaa_y
		// if so, it will create a new variable $aaa
		function parseHTTPPostVars()
		{
			// execute only if libgd support is enabled
			if (!extension_loaded('gd'))
			{
				return;
			}

			if (is_array($GLOBALS['HTTP_POST_VARS']))
			{
				while( list($key, $val) = each($GLOBALS['HTTP_POST_VARS']))
				{
					if (ereg("(.*)_x",$key,$varName) && $HTTP_POST_VARS[$varName[1]."_y"])
					{
						$name = $varName[1];
						global $$name;
						$$name = "content generated by parseHTTPPostVars()";
					}
				}
			}
		}
	}
