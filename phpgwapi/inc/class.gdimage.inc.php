<?php
	/*******************************************************************\
	* phpGroupWare - GD Image                                           *
	* http://www.phpgroupware.org                                       *
	*                                                                   *
	* Written by by Bettina Gille [ceb@phpgroupware.org]                *
	*                                                                   *
	* Creates images using GD graphics library                          *
	* Copyright (C) 2003 Free Software Foundation, Inc                  *
	* ----------------------------------------------------------------- *
	* This class based on htmlGD.php3                                   *
	* Double Choco Latte - Source Configuration Management System       *
	* Copyright (C) 1999  Michael L. Dean & Tim R. Norman               *
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

	class gdimage
	{
		var $filename;
		var $type;
		var $cur_x;
		var $cur_y;
		var $width;
		var $height;
		var $hImage;
		var $colormap;
		var $hColor;
		var $font;

		function gdimage()
		{
			$this->gd = $this->check_gd();

			if ($this->gd == 0)
			{
				echo 'Your PHP installation does not seem to have the required GD library.
						Please see the PHP documentation on how to install and enable the GD library.';
				exit;
			}

			$this->cur_x = 0;
			$this->cur_y = 0;
			$this->width = 0;
			$this->height = 0;
			$this->hImage = 0;
			$this->colormap = array();
			$this->hColor = 0;
			$this->font = 0;
			$this->type = 'png';			
			$this->temp_file	= PHPGW_SERVER_ROOT . SEP . 'phpgwapi' . SEP . 'images' . SEP . 'draw_tmp.png';
		}

		function check_gd()
		{
    		ob_start();
    		phpinfo(8); // Just get the modules loaded
    		$a = ob_get_contents();
    		ob_end_clean();

			if(preg_match('/.*GD Version.*(1[0-9|\.]+).*/',$a,$m))
			{
				$r=1; //$v=$m[1];
    		}
			elseif(preg_match('/.*GD Version.*(2[0-9|\.]+).*/',$a,$m))
			{
				$r=2; //$v=$m[1];
    		}
    		else
			{
				$r=0; //$v=$m[1];
    		}
    		return $r;
		}

		function Init()
		{
			$this->hImage = ImageCreate($this->width, $this->height) or die;
			return True;
		}

		function Done()
		{
			ImageDestroy($this->hImage);
		}

		function MoveTo($x, $y)
		{
			if ($x >= 0 && $x <= $this->width && $y >= 0 && $y <= $this->height)
			{
				$this->cur_x = $x;
				$this->cur_y = $y;

				return true;
			}
			return false;
		}

		function LineTo($x, $y, $linestyle = 'solid')
		{
			if ($x >= 0 && $x <= $this->width && $y >= 0 && $y <= $this->height)
			{
				if ($linestyle == 'dashed')
					ImageDashedLine($this->hImage, $this->cur_x, $this->cur_y, $x, $y, $this->hColor);
				else
					ImageLine($this->hImage, $this->cur_x, $this->cur_y, $x, $y, $this->hColor);

				$this->cur_x = $x;
				$this->cur_y = $y;

				return true;
			}

			return false;
		}

		function Line($x1, $y1, $x2, $y2, $linestyle = 'solid')
		{
			if ($x1 >= 0 && $x1 <= $this->width && $y1 >= 0 && $y1 <= $this->height && $x2 >= 0 && $x2 <= $this->width && $y2 >= 0 && $y2 <= $this->height)
			{
				if ($linestyle == 'solid')
					ImageLine($this->hImage, $x1, $y1, $x2, $y2, $this->hColor);
				else
					ImageDashedLine($this->hImage, $x1, $y1, $x2, $y2, $this->hColor);

				$this->cur_x = $x2;
				$this->cur_y = $y2;

				return true;
			}

			return false;
		}

		function SetColor($r, $g, $b, $set_transparent=False)
		{
			$key = "$r,$g,$b";
			if (!IsSet($this->colormap[$key]))
			{
				$this->hColor = ImageColorAllocate($this->hImage, $r, $g, $b);
				$this->colormap[$key] = $this->hColor;
			}
			else
			{
				$this->hColor = $this->colormap[$key];
			}
			if ($set_transparent)
			{
				ImageColorTransparent($this->hImage,$this->hColor);
			}

			return true;
		}

		function SetColorByName($name)
		{
			$r = 0;
			$g = 0;
			$b = 0;
			switch ($name)
			{
				case 'red':
					$r = 180;
					break;
				case 'green':
					$g = 180;
					break;
				case 'blue':
					$b = 180;
					break;
				case 'bright red':
					$r = 255;
					break;
				case 'bright green':
					$g = 255;
					break;
				case 'bright blue':
					$b = 255;
					break;
				case 'dark red':
					$r = 80;
					break;
				case 'dark green':
					$g = 80;
					break;
				case 'dark blue':
					$b = 80;
					break;
				case 'yellow':
					$r = 255;
					$g = 215;
					break;
			}

			return $this->SetColor($r, $g, $b);
		}

		function SetFont($font)
		{
			if ($font < 1 || $font > 5)
				return false;

			$this->font = $font;

			return true;
		}

		function GetFontHeight()
		{
			return ImageFontHeight($this->font);
		}

		function GetFontWidth()
		{
			return ImageFontWidth($this->font);
		}

		function DrawText($params)
		{
			$text			= $params['text'];
			$direction		= (isset($params['direction'])?$params['direction']:'');
			$justification	= (isset($params['justification'])?$params['justification']:'center');
			$margin_left	= (isset($params['margin_left'])?$params['margin_left']:'');

			$textwidth = ImageFontWidth($this->font) * strlen($text);

			/*if (isset($margin_left) && $textwidth >= $margin_left)
			{
				$text = strlen($text) - 1 . '.';
			}*/

			if ($justification == 'center')
			{
				if ($direction == 'up')
				{
					$this->cur_y += $textwidth / 2;
					if ($this->cur_y > $this->height)
						$this->cur_y = $this->height;
				}
				else
				{
					$this->cur_x -= $textwidth / 2;
					if ($this->cur_x < 0)
						$this->cur_x = 0;
				}
			}
			else if ($justification == 'right')
				{
					if ($direction == 'up')
					{
						$this->cur_y += $textwidth;
						if ($this->cur_y > $this->height)
							$this->cur_y = $this->height;
					}
					else
					{
						$this->cur_x -= $textwidth;
						if ($this->cur_x < 0)
							$this->cur_x = 0;
					}
				}

			if ($direction == 'up')
				ImageStringUp($this->hImage, $this->font, $this->cur_x, $this->cur_y, $text, $this->hColor);
			else
				ImageString($this->hImage, $this->font, $this->cur_x, $this->cur_y, $text, $this->hColor);

			return true;
		}

		function ToBrowser()
		{
			//header('Content-type: image/' . $this->type);
			switch ($this->type)
			{
				case 'png':
					ImagePNG($this->hImage,$this->temp_file);
					break;
				case 'gif':
					ImageGIF($this->hImage);
					break;
				case 'jpeg':
					ImageJPEG($this->hImage);
					break;
			}
		}
	}
?>
